<?php
namespace App\Services;
use App\Models\{Assessment,StudentTask,StudentSubmission,User,Participant,GradeReview};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Validator,Storage};
use Illuminate\Validation\ValidationException;
class StudentTasks {
    public function head(int $assessment): ?StudentTask { return StudentTask::where('assessment_id',$assessment)->orderByDesc('version')->first(); }
    public function snapshot(Assessment $a): array {
        app(AnswerIntake::class)->classroom($a); $questions=$a->questions()->get();
        if($questions->isEmpty() || $questions->count()>100) $this->fail('Tugas memerlukan 1–100 soal.');
        $rows=[]; foreach($questions as $q) { $q->setRelation('assessment',$a); $rows[]=['id'=>$q->id,'number'=>$q->number,'prompt'=>$q->prompt,'max_score'=>$q->max_score,'source_hash'=>RubricValidator::sourceHash($q->rubricSource())]; }
        $snapshot=['title'=>$a->title,'instructions'=>$a->instructions ?? '','questions'=>$rows];
        if(strlen(json_encode($snapshot))>200000) $this->fail('Paket tugas maksimal 200 KB.'); return $snapshot;
    }
    public function save(Assessment $assessment,int $actor,string $key,int $expected,string $action,?string $deadline,bool $confirmed): StudentTask {
        Validator::make(compact('key','expected','action','deadline'),['key'=>'required|uuid','expected'=>'required|integer|min:0','action'=>'required|in:open,close','deadline'=>'nullable|date'])->validate();
        if(!$confirmed) $this->fail('Konfirmasi pembukaan/penutupan tugas diperlukan.');
        $hash=EvaluationData::hash([$assessment->id,$actor,$expected,$action,$deadline]);
        return DB::transaction(function() use($assessment,$actor,$key,$expected,$action,$deadline,$hash) {
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id); abort_unless($a->owner_id===$actor && $a->owner->is_active && $a->owner->role==='teacher',403);
            $old=StudentTask::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $this->fail('Kunci tugas sudah dipakai.'); return $old; }
            $head=$this->head($a->id); if(($head?->version ?? 0)!==$expected) $this->fail('Tugas berubah di tab lain. Muat ulang.');
            $snapshot=null; $end=null;
            if($action==='open') {
                $snapshot=$this->snapshot($a); $end=$deadline?CarbonImmutable::parse($deadline)->utc():null;
                if(!$end || $end->lte(now()) || $end->gt(now()->addDays(90))) $this->fail('Batas waktu harus di masa depan, maksimal 90 hari.');
            } elseif(!$head || $head->action!=='open') $this->fail('Tidak ada tugas terbuka untuk ditutup.');
            return StudentTask::create(['assessment_id'=>$a->id,'classroom_id'=>$action==='open'?$a->classroom_id:$head->classroom_id,'created_by'=>$actor,'version'=>$expected+1,'action'=>$action,
                'snapshot'=>$snapshot,'snapshot_hash'=>$snapshot?EvaluationData::hash($snapshot):null,'closes_at'=>$end,'creation_key'=>$key,'request_hash'=>$hash]);
        });
    }
    public function participant(User $user): Participant {
        abort_unless($user->role==='student' && $user->is_active,403);
        return $user->studentProfile()->firstOrFail()->participant()->with('classroom')->firstOrFail();
    }
    public function visible(StudentTask $task,Participant $p): ?array {
        $a=$task->assessment;
        if($task->action!=='open' || !$task->closes_at?->isFuture() || $this->head($a->id)?->id!==$task->id || $task->classroom_id!==$p->classroom_id || $a->classroom_id!==$p->classroom_id || !$a->owner->is_active || $a->owner->role!=='teacher') return null;
        try {
            $fresh=$this->snapshot($a); if($fresh!==$task->snapshot || !hash_equals($task->snapshot_hash,EvaluationData::hash($fresh))) return null;
            $fresh['questions']=array_map(fn($q)=>array_intersect_key($q,array_flip(['id','number','prompt','max_score'])),$fresh['questions']);
            return $fresh+['id'=>$task->id,'version'=>$task->version,'closes_at'=>$task->closes_at];
        } catch(\Throwable) { return null; }
    }
    public function submit(User $user,int $taskId,int $question,string $key,int $expected,array $payload,bool $confirmed): StudentSubmission {
        Validator::make($payload,['content_type'=>'required|in:text,photo'])->validate();
        Validator::make(compact('key','expected'),['key'=>'required|uuid','expected'=>'required|integer|min:0'])->validate();
        if(!$confirmed) $this->fail('Konfirmasi pengiriman jawaban diperlukan.');
        $photo=($payload['content_type'] ?? '')==='photo'; $intake=app(AnswerIntake::class);
        if($photo) { $intake->photo($payload['photo']); $contentHash=hash_file('sha256',$payload['photo']->getRealPath()); }
        else $contentHash=hash('sha256',$intake->text($payload['answer_text'] ?? ''));
        $hash=EvaluationData::hash([$user->id,$taskId,$question,$expected,$photo,$contentHash]); $stored=null;
        try { return DB::transaction(function() use($user,$taskId,$question,$key,$expected,$payload,$hash,$intake,&$stored) {
            $task=StudentTask::findOrFail($taskId); $a=Assessment::lockForUpdate()->findOrFail($task->assessment_id); $task->setRelation('assessment',$a);
            $p=$this->participant($user); Participant::lockForUpdate()->findOrFail($p->id);
            $old=StudentSubmission::where('creation_key',$key)->first(); if($old) { if($old->created_by!==$user->id || $old->request_hash!==$hash) $this->fail('Kunci kirim sudah dipakai.'); return $old; }
            $visible=$this->visible($task,$p); if(!$visible || !collect($visible['questions'])->contains('id',$question)) $this->fail('Tugas ditutup, kedaluwarsa, berubah, atau soal tidak termasuk tugas.');
            if(StudentSubmission::where('student_task_id',$task->id)->where('participant_id',$p->id)->where('question_id',$question)->count()>=3) $this->fail('Maksimal tiga pengiriman per soal pada versi tugas ini.');
            $answer=$a->answers()->where('participant_id',$p->id)->where('question_id',$question)->first();
            if($answer && ($answer->created_by!==$user->id || $answer->latestRevision->created_by!==$user->id || GradeReview::where('answer_id',$answer->id)->exists())) $this->fail('Jawaban sudah dikelola/diperiksa guru. Hubungi pengajar untuk koreksi.');
            if(($answer?->current_version ?? 0)!==$expected) $this->fail('Jawaban berubah. Muat ulang sebelum mengirim.');
            $payload['origin']='student';
            $answer=$answer?$intake->revise($a,$user->id,$answer->id,$expected,'Revisi dikirim siswa.',$payload):$intake->create($a,$user->id,$p->id,$question,$key,$payload);
            $revision=$answer->fresh()->latestRevision; $stored=$revision->photo_path;
            return StudentSubmission::create(['student_task_id'=>$task->id,'participant_id'=>$p->id,'question_id'=>$question,'answer_revision_id'=>$revision->id,'created_by'=>$user->id,'creation_key'=>$key,'request_hash'=>$hash]);
        }); } catch(\Throwable $e) { if($stored) Storage::disk('answers')->delete($stored); throw $e; }
    }
    private function fail(string $text): never { throw ValidationException::withMessages(['task'=>$text]); }
}
