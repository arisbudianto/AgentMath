<?php
namespace App\Services;
use App\Models\{Assessment,Answer,GradePublication};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;
class GradePublishing {
    public function head(Answer $answer): ?GradePublication { return GradePublication::where('answer_id',$answer->id)->orderByDesc('version')->first(); }
    public function visible(Answer $answer): ?array {
        return DB::transaction(function() use($answer) {
        Assessment::lockForUpdate()->findOrFail($answer->assessment_id);
        $answer=$answer->fresh();
        $p=$this->head($answer); if(!$p || $p->action!=='publish') return null;
        try {
            if(!$answer->assessment->owner->is_active || $answer->assessment->owner->role!=='teacher' || $answer->participant->classroom_id!==$answer->assessment->classroom_id) return null;
            $snapshot=app(GradeReviewWorkflow::class)->approvedSnapshot($answer,$p->grade_review_id);
            if($snapshot!==$p->snapshot || !hash_equals($p->snapshot_hash,EvaluationData::hash([$snapshot,$p->message]))) return null;
            return $snapshot+['message'=>$p->message,'published_at'=>$p->created_at];
        } catch(\Throwable) { return null; }
        });
    }
    public function save(Assessment $assessment,int $actor,int $answerId,string $key,int $expected,string $action,?int $review,string $message,bool $confirmed): GradePublication {
        Validator::make(compact('key','expected','action','message'),['key'=>'required|uuid','expected'=>'required|integer|min:0','action'=>'required|in:publish,revoke','message'=>'present|string|max:1000'])->validate();
        if(!$confirmed) $this->fail('Konfirmasi terbit/cabut diperlukan.');
        $hash=EvaluationData::hash([$assessment->id,$actor,$answerId,$expected,$action,$review,$message]);
        return DB::transaction(function() use($assessment,$actor,$answerId,$key,$expected,$action,$review,$message,$hash) {
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id);
            abort_unless($a->owner_id===$actor && $a->owner->role==='teacher' && $a->owner->is_active,403);
            $answer=$a->answers()->lockForUpdate()->findOrFail($answerId);
            $old=GradePublication::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $this->fail('Kunci publikasi sudah dipakai.'); return $old; }
            $head=$this->head($answer); if(($head?->version ?? 0)!==$expected) $this->fail('Publikasi berubah di tab lain. Muat ulang.');
            $snapshot=null;
            if($action==='publish') {
                if(!$review || $answer->participant->classroom_id!==$a->classroom_id) $this->fail('Jawaban/peserta tidak sesuai kelas.');
                $snapshot=app(GradeReviewWorkflow::class)->approvedSnapshot($answer,$review);
            } elseif(!$head || $head->action!=='publish') $this->fail('Tidak ada publikasi untuk dicabut.');
            return GradePublication::create(['answer_id'=>$answer->id,'created_by'=>$actor,'grade_review_id'=>$action==='publish'?$review:null,
                'version'=>$expected+1,'action'=>$action,'snapshot'=>$snapshot,'snapshot_hash'=>$snapshot?EvaluationData::hash([$snapshot,$message]):null,
                'message'=>$message,'creation_key'=>$key,'request_hash'=>$hash]);
        });
    }
    private function fail(string $text): never { throw ValidationException::withMessages(['publication'=>$text]); }
}
