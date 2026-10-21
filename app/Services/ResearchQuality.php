<?php
namespace App\Services;
use App\Models\{Assessment,EvalDataset,EvalCase,EvalReference,ResearchAnnotation,AnswerRevision};
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
class ResearchQuality {
    public const KINDS=['human_a','human_b','ocr','adjudication'];
    public function latest(int $case,string $kind): ?ResearchAnnotation { return ResearchAnnotation::where('eval_case_id',$case)->where('kind',$kind)->orderByDesc('version')->first(); }
    private function body(array $attributes): array { $body=[]; foreach(['eval_case_id','created_by','eval_reference_id','kind','version','rater_code','data'] as $k) $body[$k]=$attributes[$k] ?? null; return $body; }
    public function valid(ResearchAnnotation $a,EvalCase $case): void {
        if($a->eval_case_id!==$case->id || !hash_equals($a->content_hash,EvaluationData::hash($this->body($a->toArray())))) app(EvaluationData::class)->fail('Checksum anotasi penelitian tidak sesuai.');
        if(!in_array($a->kind,self::KINDS,true) || ($a->data['independent'] ?? null)!==true) app(EvaluationData::class)->fail('Deklarasi independensi anotasi tidak valid.');
        if(in_array($a->kind,['human_a','human_b','adjudication'],true)) {
            $scores=app(EvaluationData::class)->scores($a->data['criteria'],$case->input_snapshot);
            if($scores['criteria']!==$a->data['criteria'] || $scores['total_score']!==$a->data['total_score']) app(EvaluationData::class)->fail('Skor anotasi tidak valid.');
        }
        if($a->kind==='ocr') {
            Validator::make($a->data,['text'=>'required|string|max:20000','source.confirmed_text'=>'required|string','source.ai_text'=>'nullable|string','source.photo_hash'=>'required|string|size:64'])->validate();
            app(AnswerIntake::class)->text($a->data['text']);
            if(!$case->answer_transcription_id || $a->data['source']['confirmed_text']!==$case->input_snapshot['answer_text'] || $a->data['source']['photo_hash']!==AnswerRevision::findOrFail($case->answer_revision_id)->content_hash) app(EvaluationData::class)->fail('Sumber acuan foto tidak sesuai kasus.');
            $source=$a->data['source'];
            if($source['ai_transcription_id']!==null) {
                $ai=\App\Models\AnswerTranscription::where('answer_revision_id',$case->answer_revision_id)->where('origin','ai')->where('id','<',$case->answer_transcription_id)->findOrFail($source['ai_transcription_id']);
                if($ai->transcript_text!==$source['ai_text']) app(EvaluationData::class)->fail('Snapshot draf transkripsi AI tidak sesuai sumber.');
            } elseif($source['ai_text']!==null) app(EvaluationData::class)->fail('Draf transkripsi AI tidak memiliki sumber.');
        }
    }
    public function save(Assessment $assessment,int $actor,int $dataset,int $caseId,string $kind,string $key,int $expected,array $input,bool $independent): ResearchAnnotation {
        Validator::make(compact('kind','key','expected'),['kind'=>'required|in:human_a,human_b,ocr,adjudication','key'=>'required|uuid','expected'=>'required|integer|min:0'])->validate();
        $ed=app(EvaluationData::class); if(!$independent) $ed->fail('Deklarasi acuan manusia independen tanpa menyalin AI diperlukan.');
        $hash=EvaluationData::hash([$assessment->id,$actor,$dataset,$caseId,$kind,$expected,$input]);
        return $ed->locked($assessment,$actor,$dataset,function($a,$d) use($actor,$caseId,$kind,$key,$expected,$input,$hash,$ed) {
            $case=$d->cases()->findOrFail($caseId);
            $old=ResearchAnnotation::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $ed->fail('Kunci anotasi sudah dipakai.'); return $old; }
            if($d->status!=='draft') $ed->fail('Anotasi dataset beku tidak dapat diubah. Buat dataset baru.');
            if(($this->latest($case->id,$kind)?->version ?? 0)!==$expected) $ed->fail('Anotasi berubah di tab lain. Muat ulang.');
            $ed->validCase($case); $data=[]; $code=null; $reference=null;
            Validator::make($input,['reason'=>'required|string|max:2000'])->validate();
            if(in_array($kind,['human_a','human_b'],true)) {
                Validator::make($input,['rater_code'=>'required|regex:/^[A-Za-z0-9_-]{1,40}$/D','criteria'=>'required|array'])->validate(); $code=strtoupper($input['rater_code']);
                $other=$this->latest($case->id,$kind==='human_a'?'human_b':'human_a'); if($other?->rater_code===$code) $ed->fail('Kode kedua penilai harus berbeda. Gunakan kode samaran, bukan identitas pribadi.');
                $data=$ed->scores($input['criteria'],$case->input_snapshot)+['reason'=>$input['reason'],'independent'=>true];
            } elseif($kind==='ocr') {
                if(!$case->answer_transcription_id) $ed->fail('Acuan transkripsi hanya untuk kasus foto.');
                Validator::make($input,['text'=>'required|string|max:20000'])->validate(); $text=app(AnswerIntake::class)->text($input['text']);
                if(preg_match('/\[(?:tidak terbaca|ragu|illegible|unclear)[^\]]*\]/iu',$text)) $ed->fail('Acuan harus dapat dipastikan manusia. Biarkan tidak berlabel bila foto tidak terbaca; jangan menebak.');
                $revision=AnswerRevision::findOrFail($case->answer_revision_id);
                $ai=$revision->transcriptions()->where('origin','ai')->where('id','<',$case->answer_transcription_id)->first();
                $data=['text'=>$text,'reason'=>$input['reason'],'independent'=>true,'source'=>['photo_hash'=>$revision->content_hash,'confirmed_text'=>$case->input_snapshot['answer_text'],'ai_text'=>$ai?->transcript_text,'ai_transcription_id'=>$ai?->id,
                    'ai_provider'=>$ai?->ai_metadata['provider'] ?? null,'ai_model'=>$ai?->ai_metadata['model'] ?? null,'ai_prompt_version'=>$ai?->ai_metadata['prompt_version'] ?? null]];
            } else {
                Validator::make($input,['a_id'=>'required|integer|min:1','b_id'=>'required|integer|min:1','reference_version'=>'required|integer|min:0','criteria'=>'required|array'])->validate();
                $pa=$this->latest($case->id,'human_a'); $pb=$this->latest($case->id,'human_b');
                if(!$pa || !$pb || $pa->id!==(int)$input['a_id'] || $pb->id!==(int)$input['b_id'] || $pa->rater_code===$pb->rater_code) $ed->fail('Adjudikasi memerlukan dua label terkini dengan kode berbeda.');
                $this->valid($pa,$case); $this->valid($pb,$case);
                $data=$ed->scores($input['criteria'],$case->input_snapshot)+['reason'=>$input['reason'],'independent'=>true,'a_id'=>$pa->id,'b_id'=>$pb->id,'a_hash'=>$pa->content_hash,'b_hash'=>$pb->content_hash];
                $reference=$ed->label($a,$actor,$d->id,$case->id,(string)Str::uuid(),(int)$input['reference_version'],$input['criteria'],$input['reason'],true,null);
            }
            $body=['eval_case_id'=>$case->id,'created_by'=>$actor,'eval_reference_id'=>$reference?->id,'kind'=>$kind,'version'=>$expected+1,'rater_code'=>$code,'data'=>$data];
            return ResearchAnnotation::create($body+['content_hash'=>EvaluationData::hash($body),'creation_key'=>$key,'request_hash'=>$hash]);
        });
    }
    public function freezeEntry(EvalDataset $d,EvalCase $case,EvalReference $reference): array {
        $quality=[]; foreach(self::KINDS as $kind) if($a=$this->latest($case->id,$kind)) { $this->valid($a,$case); $quality[$kind]=['id'=>$a->id,'hash'=>$a->content_hash]; }
        if(($d->protocol['reference_policy'] ?? '')==='two_humans_adjudicated' && !isset($quality['human_a'],$quality['human_b'],$quality['adjudication'])) app(EvaluationData::class)->fail('Protokol ini memerlukan dua penilai dan adjudikasi untuk setiap kasus.');
        if(isset($quality['adjudication'])) {
            if(!isset($quality['human_a'],$quality['human_b'])) app(EvaluationData::class)->fail('Pasangan adjudikasi tidak lengkap.');
            $adj=$this->latest($case->id,'adjudication');
            if($adj->eval_reference_id!==$reference->id || $reference->criteria!==$adj->data['criteria'] || $reference->total_score!==$adj->data['total_score'] || $adj->data['a_id']!==$quality['human_a']['id'] || $adj->data['b_id']!==$quality['human_b']['id'] || $adj->data['a_hash']!==$quality['human_a']['hash'] || $adj->data['b_hash']!==$quality['human_b']['hash'] || $this->latest($case->id,'human_a')->rater_code===$this->latest($case->id,'human_b')->rater_code) app(EvaluationData::class)->fail('Adjudikasi/acuan sudah tidak sesuai label manusia terkini.');
        }
        return $quality;
    }
    public function pinned(array $entry,EvalCase $case): array {
        $result=[]; foreach(($entry['quality'] ?? []) as $kind=>$pin) {
            if(!in_array($kind,self::KINDS,true)) app(EvaluationData::class)->fail('Jenis anotasi manifest tidak valid.');
            $a=ResearchAnnotation::findOrFail($pin['id']); $this->valid($a,$case);
            if($a->kind!==$kind || $a->content_hash!==$pin['hash']) app(EvaluationData::class)->fail('Anotasi tidak sesuai manifest.'); $result[$kind]=$a;
        }
        if(isset($result['adjudication'])) {
            $adj=$result['adjudication']; $a=$result['human_a'] ?? null; $b=$result['human_b'] ?? null; $ref=EvalReference::findOrFail($entry['reference_id']);
            if(!$a || !$b || $a->rater_code===$b->rater_code || $adj->eval_reference_id!==$ref->id || $adj->data['a_id']!==$a->id || $adj->data['b_id']!==$b->id || $adj->data['a_hash']!==$a->content_hash || $adj->data['b_hash']!==$b->content_hash || $adj->data['criteria']!==$ref->criteria || $adj->data['total_score']!==$ref->total_score) app(EvaluationData::class)->fail('Dasar adjudikasi tidak konsisten.');
        }
        return $result;
    }
    public function cer(?string $actual,string $reference): array {
        if($actual===null) return ['status'=>'missing','edits'=>null,'characters'=>null,'cer_percent'=>null];
        $a=preg_split('//u',str_replace(["\r\n","\r"],"\n",$actual),-1,PREG_SPLIT_NO_EMPTY); $r=preg_split('//u',str_replace(["\r\n","\r"],"\n",$reference),-1,PREG_SPLIT_NO_EMPTY); $n=count($r); $m=count($a);
        if(!$n || $n>2000 || $m>2000 || $n*$m>250000) return ['status'=>'limited','edits'=>null,'characters'=>null,'cer_percent'=>null];
        $previous=range(0,$m); foreach($r as $i=>$rc) { $current=[$i+1]; foreach($a as $j=>$ac) $current[$j+1]=min($previous[$j+1]+1,$current[$j]+1,$previous[$j]+($rc===$ac?0:1)); $previous=$current; }
        $edits=$previous[$m]; return ['status'=>'computed','edits'=>$edits,'characters'=>$n,'cer_percent'=>round(100*$edits/$n,4)];
    }
}
