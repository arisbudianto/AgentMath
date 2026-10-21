<?php
namespace App\Services;
use App\Jobs\ProcessGradingStage;
use App\Models\{Assessment, GradingRun};
use Illuminate\Support\Facades\{DB, Queue};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GradingOrchestrator
{
    public function __construct(private GradingStages $stages) {}
    public function start(Assessment $assessment,int $actor,int $answerId,string $key): GradingRun {
        return DB::transaction(function() use($assessment,$actor,$answerId,$key) {
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id); $this->owner($a,$actor);
            $answer=$a->answers()->with(['question.assessment','latestRevision.latestTranscription'])->lockForUpdate()->findOrFail($answerId);
            $existing=GradingRun::where('creation_key',$key)->first();
            if($existing) {
                if($existing->assessment_id!==$a->id || $existing->answer_id!==$answerId || $existing->created_by!==$actor) $this->fail('Kunci permintaan sudah dipakai untuk proses lain.');
                return $existing;
            }
            if(GradingRun::where('answer_id',$answerId)->whereIn('status',['queued','running','awaiting_agent'])->exists()) $this->fail('Jawaban ini masih memiliki proses aktif. Periksa atau batalkan proses tersebut dahulu.');
            $text=app(TranscriptionWorkflow::class)->readyText($answer);
            $rubric=$answer->question->currentApprovedRubric();
            if($text===null) $this->fail('Jawaban belum siap: periksa dasar soal dan konfirmasi transkripsi foto terkini.');
            if(!$rubric) $this->fail('Sahkan rubrik yang cocok dengan dasar soal terkini sebelum membuat proses.');
            $source=$answer->question->rubricSource();
            $rubricData=$rubric->only(['rules','criteria','grading_policy']); app(RubricValidator::class)->validate($rubricData,$source);
            $input=['schema_version'=>'m04_v1','source'=>$source,'rubric'=>$rubricData,'answer_text'=>$text];
            $revision=$answer->latestRevision;
            $run=GradingRun::create([
                'assessment_id'=>$a->id,'answer_id'=>$answerId,'answer_revision_id'=>$revision->id,
                'answer_transcription_id'=>$revision->content_type==='photo'?$revision->latestTranscription->id:null,
                'rubric_version_id'=>$rubric->id,'created_by'=>$actor,'creation_key'=>$key,
                'input_snapshot'=>$input,'input_hash'=>$this->hash($input),
                'config_snapshot'=>['pipeline_version'=>'m05_v1','provider'=>config('ai.provider'),'model'=>config('ai.model'),'enabled'=>(bool)config('ai.enabled'),'max_output_tokens'=>(int)config('ai.max_output_tokens',4000)],
                'status'=>'queued','stage'=>'preflight','generation'=>1,'queued_at'=>now(),
            ]);
            $this->event($run,'created',$actor); $this->enqueue($run); return $run;
        });
    }

    public function isCurrent(GradingRun $run): bool {
        $answer=$run->answer()->with(['latestRevision.latestTranscription','question.assessment'])->first();
        if(!$answer || !$answer->question || $answer->latestRevision?->id!==$run->answer_revision_id) return false;
        $revision=$answer->latestRevision;
        if($revision->source_hash!==RubricValidator::sourceHash($answer->question->rubricSource())) return false;
        if($revision->content_type==='photo' && ($revision->latestTranscription?->id!==$run->answer_transcription_id || $revision->latestTranscription?->status!=='confirmed')) return false;
        return $answer->question->currentApprovedRubric()?->id===$run->rubric_version_id;
    }

    public function process(int $id,int $generation): void {
        $claim=$this->locked($id,function($run) use($generation) {
            if($run->generation!==$generation || $run->status!=='queued') return null;
            if(!$run->assessment->owner->is_active || $run->assessment->owner->role!=='teacher') { $this->stop($run,'cancelled','owner_inactive'); return null; }
            if(!$this->isCurrent($run)) { $this->stop($run,'stale','source_changed'); return null; }
            if(!hash_equals($run->input_hash,$this->hash($run->input_snapshot))) { $this->stop($run,'failed','snapshot_invalid'); return null; }
            if(!$this->stages->supports($run->stage)) { $this->stop($run,'awaiting_agent','agent_unavailable'); return null; }
            $number=$run->attempts()->where('stage',$run->stage)->count()+1;
            if($number>3) { $this->stop($run,'failed','attempt_limit'); return null; }
            $token=(string)Str::uuid();
            $attempt=$run->attempts()->create(['stage'=>$run->stage,'number'=>$number,'lease_token'=>$token,'status'=>'running','started_at'=>now()]);
            $run->update(['status'=>'running','lease_token'=>$token,'lease_until'=>now()->addSeconds(120)]); $this->event($run,'started');
            return [$run,$attempt];
        });
        if(!$claim) return;
        [$run,$attempt]=$claim; $output=null; $error=null;
        try {
            // Marker A/B never receive the other marker's output. M05 owns scoring/arbitration validators.
            $previous=in_array($run->stage,['marker_a','marker_b','preflight'],true)?[]:$run->attempts()->where('status','succeeded')->get()->pluck('output','stage')->all();
            $output=$this->stages->validate($run->stage,$this->stages->execute($run->stage,$run->input_snapshot,$previous,$run->config_snapshot));
            if(strlen(json_encode($output,JSON_THROW_ON_ERROR))>1000000) throw new \UnexpectedValueException();
        } catch(\Throwable $e) { $error=match(true) { $e instanceof GradingConfigurationException=>'configuration_changed', $e instanceof \UnexpectedValueException=>'invalid_output', default=>'stage_failed' }; }
        $this->locked($id,function($current) use($generation,$attempt,$output,$error) {
            if($current->generation!==$generation || $current->status!=='running' || $current->lease_token!==$attempt->lease_token) return;
            $entry=$current->attempts()->findOrFail($attempt->id);
            if($current->lease_until?->isPast()) { $entry->update(['status'=>'failed','finished_at'=>now(),'error_code'=>'worker_interrupted']); $this->stop($current,'failed','worker_interrupted'); return; }
            if(!$this->isCurrent($current)) { $entry->update(['status'=>'stale','finished_at'=>now(),'error_code'=>'source_changed']); $this->stop($current,'stale','source_changed'); return; }
            if(!$current->assessment->owner->is_active || $current->assessment->owner->role!=='teacher') { $entry->update(['status'=>'cancelled','finished_at'=>now(),'error_code'=>'owner_inactive']); $this->stop($current,'cancelled','owner_inactive'); return; }
            $entry->update(['status'=>$error?'failed':'succeeded','output'=>$error?null:$output,'error_code'=>$error,'finished_at'=>now()]);
            if($error) { $this->stop($current,'failed',$error); return; }
            $this->event($current,'succeeded');
            $next=GradingStages::PLAN[array_search($current->stage,GradingStages::PLAN,true)+1] ?? null;
            if(!$next) { $this->stop($current,'completed','pipeline_completed'); return; }
            $current->update(['stage'=>$next,'status'=>'queued','generation'=>$current->generation+1,'lease_token'=>null,'lease_until'=>null,'queued_at'=>now()]);
            $this->enqueue($current);
        });
    }

    public function control(Assessment $assessment,int $actor,int $id,string $action): GradingRun {
        return $this->locked($id,function($run) use($assessment,$actor,$action) {
            abort_unless($run->assessment_id===$assessment->id,404); $this->owner($run->assessment,$actor);
            if($action==='cancel') {
                if(in_array($run->status,['cancelled','completed','stale'],true)) return $run;
                $this->closeAttempt($run,'cancelled','cancelled_by_teacher'); $this->stop($run,'cancelled','cancelled_by_teacher',$actor); return $run;
            }
            if($action!=='resume') $this->fail('Tindakan tidak dikenal.');
            if($run->status==='running' && $run->lease_until?->isFuture()) $this->fail('Worker masih memegang proses. Tunggu hingga batas waktu selesai.');
            if($run->status==='queued' && $run->queued_at->gt(now()->subMinutes(2))) $this->fail('Proses baru saja masuk antrean. Tunggu worker.');
            if(!in_array($run->status,['failed','awaiting_agent','running','queued'],true)) $this->fail('Proses ini tidak dapat dilanjutkan. Buat proses baru bila dasarnya berubah.');
            if(!$this->isCurrent($run)) $this->fail('Dasar proses berubah. Batalkan proses lama dan buat proses baru.');
            if(!$this->stages->supports($run->stage)) $this->fail('Tahap ini menunggu implementasi agent M05. Belum ada nilai yang dihasilkan.');
            if($run->attempts()->where('stage',$run->stage)->count()>=3) $this->fail('Batas tiga percobaan tahap tercapai. Periksa penyebab sebelum membuat proses baru.');
            $this->closeAttempt($run,'failed','worker_interrupted');
            $run->update(['status'=>'queued','generation'=>$run->generation+1,'lease_token'=>null,'lease_until'=>null,'queued_at'=>now(),'finished_at'=>null]);
            $this->event($run,'resumed',$actor); $this->enqueue($run); return $run;
        });
    }
    public function recoverExpired(): int {
        $count=0;
        foreach(GradingRun::where('status','running')->where('lease_until','<=',now())->limit(100)->pluck('id') as $id) {
            $count+=$this->locked($id,function($run) {
                if($run->status!=='running' || !$run->lease_until || $run->lease_until->isFuture()) return 0;
                $this->closeAttempt($run,'failed','worker_interrupted'); $this->stop($run,'failed','worker_interrupted'); return 1;
            });
        }
        return $count;
    }
    private function locked(int $id,callable $callback): mixed {
        $assessmentId=GradingRun::findOrFail($id)->assessment_id;
        return DB::transaction(function() use($id,$assessmentId,$callback) {
            Assessment::lockForUpdate()->findOrFail($assessmentId);
            return $callback(GradingRun::lockForUpdate()->findOrFail($id));
        });
    }
    private function closeAttempt(GradingRun $run,string $status,string $code): void {
        $entry=$run->attempts()->where('lease_token',$run->lease_token)->where('status','running')->first();
        $entry?->update(['status'=>$status,'error_code'=>$code,'finished_at'=>now()]);
    }
    private function stop(GradingRun $run,string $status,string $code,?int $actor=null): void {
        $run->update(['status'=>$status,'generation'=>$run->generation+1,'lease_token'=>null,'lease_until'=>null,'finished_at'=>$status==='awaiting_agent'?null:now()]); $this->event($run,$code,$actor);
    }
    private function enqueue(GradingRun $run): void {
        // Direct database queue INSERT shares the run transaction; no dispatch-after-commit crash gap.
        Queue::connection('grading')->push(new ProcessGradingStage($run->id,$run->generation),'','grading');
    }
    private function event(GradingRun $run,string $code,?int $actor=null): void { $run->events()->create(['actor_id'=>$actor,'code'=>$code,'stage'=>$run->stage,'created_at'=>now()]); }
    private function owner(Assessment $a,int $actor): void { abort_unless($a->owner_id===$actor && $a->owner->is_active && $a->owner->role==='teacher',403); }
    private function hash(array $data): string { return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
    private function fail(string $message): never { throw ValidationException::withMessages(['grading'=>$message]); }
}
