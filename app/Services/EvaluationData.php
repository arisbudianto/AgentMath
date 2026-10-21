<?php
namespace App\Services;
use App\Models\{Assessment,EvalCase,EvalDataset,EvalReference};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class EvaluationData {
    public static function hash(mixed $v): string { return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
    public function owner(Assessment $a,int $actor): void { abort_unless($a->owner_id===$actor && $a->owner->role==='teacher' && $a->owner->is_active,403); }
    public function fail(string $text): never { throw ValidationException::withMessages(['evaluation'=>$text]); }
    public function dataset(Assessment $assessment,int $actor,string $key,string $name,string $kind,string $goal,string $target,string $tolerance): EvalDataset {
        Validator::make(compact('key','name','kind','goal','target','tolerance'),[
            'key'=>'required|uuid','name'=>'required|string|max:160','kind'=>'required|in:synthetic,real',
            'goal'=>'required|string|max:2000','target'=>'required|string|max:2000','tolerance'=>'required|numeric|between:0,100',
        ])->validate();
        if(trim($name)==='' || trim($goal)==='' || trim($target)==='') $this->fail('Nama, tujuan dan sasaran penelitian wajib diisi.');
        $bps=RubricValidator::cents($tolerance);
        $protocol=['version'=>'m08_v1','goal'=>$goal,'target'=>$target,'tolerance_bps'=>$bps,'split_policy'=>'question_group','reference_policy'=>'human_independent_attested','baseline'=>'one_fresh_m05_marker','metrics'=>'m08_v1'];
        $hash=self::hash([$assessment->id,$actor,$name,$kind,$protocol]);
        return DB::transaction(function() use($assessment,$actor,$key,$name,$kind,$protocol,$hash) {
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id); $this->owner($a,$actor);
            $old=EvalDataset::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $this->fail('Kunci dataset sudah dipakai.'); return $old; }
            return EvalDataset::create(['assessment_id'=>$a->id,'created_by'=>$actor,'name'=>$name,'data_kind'=>$kind,'protocol'=>$protocol,'creation_key'=>$key,'request_hash'=>$hash,'status'=>'draft']);
        });
    }
    public function locked(Assessment $assessment,int $actor,int $dataset,callable $fn): mixed {
        return DB::transaction(function() use($assessment,$actor,$dataset,$fn) {
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id); $this->owner($a,$actor);
            $d=EvalDataset::where('assessment_id',$a->id)->lockForUpdate()->findOrFail($dataset); return $fn($a,$d);
        });
    }
    public function add(Assessment $a,int $actor,int $dataset,int $answerId,string $split,string $key): EvalCase {
        Validator::make(compact('key','split'),['key'=>'required|uuid','split'=>'required|in:tuning,test'])->validate();
        $hash=self::hash([$a->id,$actor,$dataset,$answerId,$split]);
        return $this->locked($a,$actor,$dataset,function($a,$d) use($actor,$answerId,$split,$key,$hash) {
            $old=EvalCase::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $this->fail('Kunci kasus sudah dipakai.'); return $old; }
            if($d->status!=='draft') $this->fail('Dataset telah dibekukan. Buat dataset baru untuk perubahan.');
            if($d->cases()->count()>=100) $this->fail('Maksimal 100 kasus per dataset.');
            $answer=$a->answers()->with(['question.assessment','latestRevision.latestTranscription'])->findOrFail($answerId);
            $text=app(TranscriptionWorkflow::class)->readyText($answer); $r=$answer->question->currentApprovedRubric();
            if($text===null || !$r) $this->fail('Kasus memerlukan jawaban terkini, rubrik disahkan dan transkripsi foto dikonfirmasi.');
            if($d->cases()->where('question_id',$answer->question_id)->where('split','!=',$split)->exists()) $this->fail('Satu soal tidak boleh tersebar ke tuning dan test dalam dataset yang sama.');
            if($d->cases()->where('answer_revision_id',$answer->latestRevision->id)->exists()) $this->fail('Revisi jawaban sudah ada dalam dataset.');
            $source=$answer->question->rubricSource(); $rubric=$r->only(['rules','criteria','grading_policy']); app(RubricValidator::class)->validate($rubric,$source);
            $input=['schema_version'=>'m04_v1','source'=>$source,'rubric'=>$rubric,'answer_text'=>$text];
            if(strlen(json_encode($input))>200000) $this->fail('Snapshot kasus terlalu besar (maksimal 200 KB).');
            return $d->cases()->create(['answer_id'=>$answerId,'answer_revision_id'=>$answer->latestRevision->id,
                'answer_transcription_id'=>$answer->latestRevision->content_type==='photo'?$answer->latestRevision->latestTranscription->id:null,
                'question_id'=>$answer->question_id,'rubric_version_id'=>$r->id,'split'=>$split,'input_snapshot'=>$input,'input_hash'=>self::hash($input),
                'created_by'=>$actor,'creation_key'=>$key,'request_hash'=>$hash]);
        });
    }
    public function scores(array $rows,array $input): array {
        Validator::make(['criteria'=>$rows],['criteria'=>'required|array|min:1|max:20','criteria.*'=>'array:criterion_id,score',
            'criteria.*.criterion_id'=>'required|string|distinct:strict','criteria.*.score'=>'required'])->validate();
        if(count($rows)!==count($input['rubric']['criteria'])) $this->fail('Lengkapi semua kriteria acuan manusia.');
        $map=[]; $sum=0;
        foreach($rows as $row) {
            $c=collect($input['rubric']['criteria'])->firstWhere('criterion_id',$row['criterion_id']); $score=RubricValidator::cents($row['score']);
            if(!$c || $score>RubricValidator::cents($c['max_score'])) $this->fail('Skor acuan di luar kriteria.');
            $map[$row['criterion_id']]=['criterion_id'=>$row['criterion_id'],'score'=>number_format($score/100,2,'.','')]; $sum+=$score;
        }
        return ['criteria'=>array_map(fn($c)=>$map[$c['criterion_id']],$input['rubric']['criteria']),'total_score'=>number_format($sum/100,2,'.','')];
    }
    public function label(Assessment $a,int $actor,int $dataset,int $case,string $key,int $expected,array $rows,string $reason,bool $independent,?int $seconds): EvalReference {
        Validator::make(compact('key','expected','reason','seconds'),['key'=>'required|uuid','expected'=>'required|integer|min:0','reason'=>'required|string|max:2000','seconds'=>'nullable|integer|between:1,86400'])->validate();
        if(!$independent || trim($reason)==='') $this->fail('Acuan harus diberi alasan dan dinyatakan dinilai manusia tanpa menyalin atau melihat hasil AI untuk kasus ini.');
        $hash=self::hash([$a->id,$actor,$dataset,$case,$expected,$rows,$reason,$independent,$seconds]);
        return $this->locked($a,$actor,$dataset,function($a,$d) use($actor,$case,$key,$expected,$rows,$reason,$independent,$seconds,$hash) {
            $old=EvalReference::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $this->fail('Kunci acuan sudah dipakai.'); return $old; }
            if($d->status!=='draft') $this->fail('Label dataset beku tidak dapat diubah.');
            $c=$d->cases()->findOrFail($case); $latest=$c->references()->first();
            if(($latest?->version ?? 0)!==$expected) $this->fail('Acuan berubah di tab lain. Muat ulang.');
            $this->validCase($c); $scores=$this->scores($rows,$c->input_snapshot);
            $body=$scores+['reason'=>$reason,'independent'=>true,'label_seconds'=>$seconds];
            return $c->references()->create($body+['created_by'=>$actor,'version'=>$expected+1,'creation_key'=>$key,'request_hash'=>$hash,'reference_hash'=>self::hash($body)]);
        });
    }
    public function freeze(Assessment $a,int $actor,int $dataset,bool $confirmed): EvalDataset {
        if(!$confirmed) $this->fail('Konfirmasi pembekuan protokol, pembagian soal dan acuan diperlukan.');
        return $this->locked($a,$actor,$dataset,function($a,$d) {
            if($d->status==='frozen') { $this->manifest($d); return $d; }
            $cases=$d->cases()->orderBy('id')->get(); if($cases->isEmpty()) $this->fail('Tambahkan kasus dan acuan sebelum membekukan.');
            $manifest=[];
            foreach($cases as $c) {
                $this->validCase($c); $r=$c->references()->first(); if(!$r || !$r->independent) $this->fail('Setiap kasus wajib memiliki acuan manusia independen yang dinyatakan.');
                $this->validReference($r,$c);
                $manifest[]=['case_id'=>$c->id,'reference_id'=>$r->id,'input_hash'=>$c->input_hash,'reference_hash'=>$r->reference_hash,'split'=>$c->split];
            }
            $body=['protocol'=>$d->protocol,'data_kind'=>$d->data_kind,'cases'=>$manifest];
            $d->update(['status'=>'frozen','manifest'=>$body,'manifest_hash'=>self::hash($body),'frozen_at'=>now()]); return $d;
        });
    }
    public function manifest(EvalDataset $d): array {
        if($d->status!=='frozen' || !is_array($d->manifest) || !hash_equals($d->manifest_hash ?? '',self::hash($d->manifest)) || ($d->manifest['protocol'] ?? null)!==$d->protocol || ($d->manifest['data_kind'] ?? null)!==$d->data_kind) $this->fail('Manifest evaluasi tidak valid.');
        return $d->manifest['cases'];
    }
    public function validCase(EvalCase $c): void {
        if(!hash_equals($c->input_hash,self::hash($c->input_snapshot))) $this->fail('Checksum kasus tidak sesuai.');
        app(RubricValidator::class)->validate($c->input_snapshot['rubric'],$c->input_snapshot['source']); app(AnswerIntake::class)->text($c->input_snapshot['answer_text']);
    }
    public function validReference(EvalReference $r,EvalCase $c): void {
        $scores=$this->scores($r->criteria,$c->input_snapshot);
        $body=$scores+['reason'=>$r->reason,'independent'=>$r->independent,'label_seconds'=>$r->label_seconds];
        if(!$r->independent || $scores['total_score']!==$r->total_score || !hash_equals($r->reference_hash,self::hash($body))) $this->fail('Acuan manusia tidak valid.');
    }
}
