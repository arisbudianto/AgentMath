<?php
namespace App\Services;
use App\Models\{Assessment,Classroom,GradeReview,GradingAttempt,GradingRun};
use Illuminate\Support\Facades\DB;

class ResultReport {
    public const MAX_CELLS=1000;
    public const PROPOSAL_STATUSES=['none'=>'Belum ada usulan','current'=>'Usulan AI terkini','historical'=>'Usulan historis','invalid'=>'Usulan ditahan'];
    public const GRADE_STATUSES=['none'=>'Belum disahkan','current'=>'Disahkan guru','historical'=>'Keputusan historis','invalid'=>'Keputusan ditahan'];

    public function build(Assessment $assessment,?int $participant=null,?int $question=null): array {
        return DB::transaction(function() use($assessment,$participant,$question) {
            // Same assessment-first order as grading/review; lock roster rows for a consistent export.
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id);
            \Illuminate\Support\Facades\Gate::authorize('view',$a);
            if($a->classroom_id) Classroom::lockForUpdate()->findOrFail($a->classroom_id);
            if($a->classroom && $a->classroom->owner_id!==$a->owner_id) abort(403);
            $participants=$a->classroom?->participants()->when($participant,fn($q)=>$q->whereKey($participant))->lockForUpdate()->get() ?? collect();
            $questions=$a->questions()->when($question,fn($q)=>$q->whereKey($question))->get();
            if($participant && $participants->isEmpty()) abort(404);
            if($question && $questions->isEmpty()) abort(404);
            $base=['participants'=>$participants,'questions'=>$questions,'generated_at'=>now()->toIso8601String(),
                'participant_filter'=>$participant,'question_filter'=>$question,'details'=>[],'summary'=>[],'limited'=>false];
            if($participants->count()*max(1,$questions->count())>self::MAX_CELLS || $questions->count()>100) return array_replace($base,['limited'=>true]);
            $meta=[]; $max=0; $sourceBytes=0;
            foreach($questions as $q) {
                $q->setRelation('assessment',$a); $source=$q->rubricSource(); $hash=RubricValidator::sourceHash($source);
                $rubric=$q->rubricVersions()->where('status','approved')->where('source_hash',$hash)->first();
                $meta[$q->id]=['source'=>$source,'hash'=>$hash,'rubric'=>$rubric]; $max+=RubricValidator::cents($q->max_score);
                $sourceBytes+=strlen(json_encode([$source,$rubric?->only(['rules','criteria','grading_policy'])]));
                if($sourceBytes>8388608) return array_replace($base,['limited'=>true]);
            }
            $answers=$a->answers()->whereIn('participant_id',$participants->pluck('id')->all())->whereIn('question_id',$questions->modelKeys())
                ->with(['latestRevision'=>fn($r)=>$r->select(array_map(fn($c)=>'answer_revisions.'.$c,['id','answer_id','version','content_type','source_hash','answer_text'])),
                    'latestRevision.latestTranscription'=>fn($t)=>$t->select(array_map(fn($c)=>'answer_transcriptions.'.$c,['id','answer_revision_id','version','status','transcript_text']))])->get();
            if($answers->sum(fn($answer)=>strlen($answer->latestRevision?->answer_text ?? '')+strlen($answer->latestRevision?->latestTranscription?->transcript_text ?? ''))>8388608) return array_replace($base,['limited'=>true]);
            $answerIds=$answers->modelKeys();
            $answerMap=$answers->keyBy(fn($v)=>$v->participant_id.':'.$v->question_id);
            $latestRuns=GradingRun::whereIn('id',GradingRun::selectRaw('MAX(id)')->where('assessment_id',$a->id)->whereIn('answer_id',$answerIds)->groupBy('answer_id'))
                ->get(['id','answer_id','status'])->keyBy('answer_id');
            $completed=GradingRun::whereIn('id',GradingRun::selectRaw('MAX(id)')->where('assessment_id',$a->id)->whereIn('answer_id',$answerIds)->where('status','completed')->groupBy('answer_id'))
                ->get(['id','answer_id','answer_revision_id','answer_transcription_id','rubric_version_id','status','stage'])->keyBy('answer_id');
            $approved=GradeReview::whereIn('id',GradeReview::selectRaw('MAX(id)')->whereIn('answer_id',$answerIds)->where('status','approved')->groupBy('answer_id'))
                ->get(['id','answer_id','grading_run_id','confidence_attempt_id','version','total_score','approved_at'])->keyBy('answer_id');
            $heads=GradeReview::whereIn('id',GradeReview::selectRaw('MAX(id)')->whereIn('answer_id',$answerIds)->groupBy('answer_id'))->get(['id','answer_id','status','version'])->keyBy('answer_id');
            $runs=$completed->keyBy('id');
            foreach(GradingRun::whereIn('id',$approved->pluck('grading_run_id'))->get(['id','answer_id','answer_revision_id','answer_transcription_id','rubric_version_id','status','stage']) as $run) $runs[$run->id]=$run;
            $current=[];
            foreach($runs as $run) {
                $answer=$answers->firstWhere('id',$run->answer_id);
                $current[$run->id]=$answer && $this->current($run,$answer,$meta[$answer->question_id]);
            }
            $integrity=[];
            foreach(GradingRun::whereIn('id',array_keys(array_filter($current)))->select(['id','input_snapshot','input_hash'])->lazyById(25) as $run) {
                $integrity[$run->id]=hash_equals($run->input_hash,hash('sha256',json_encode($run->input_snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)));
            }
            $proposals=[];
            foreach(GradingAttempt::whereIn('grading_run_id',array_keys(array_filter($current)))->where('stage','confidence')->where('status','succeeded')->lazyById(25) as $attempt) {
                $run=$runs[$attempt->grading_run_id]; $answer=$answers->firstWhere('id',$run->answer_id); $m=$meta[$answer->question_id];
                $data=$attempt->output['data'] ?? [];
                $valid=false;
                try {
                    if(!($integrity[$run->id] ?? false) || ($attempt->output['schema_version'] ?? '')!=='m05_v1' || ($data['final_grade'] ?? null)!==false || ($data['teacher_review_required'] ?? null)!==true || !in_array($data['effective_label'] ?? '',['High','Low'],true)) throw new \UnexpectedValueException();
                    $rows=$data['criteria']; foreach($rows as &$row) $row['score']=RubricValidator::cents($row['score'])/100; unset($row);
                    $canonical=app(GradingResultValidator::class)->marker(['criteria'=>$rows],[
                        'source'=>$m['source'],'rubric'=>$m['rubric']->only(['rules','criteria','grading_policy']),
                        'answer_text'=>$answer->latestRevision->content_type==='photo'?$answer->latestRevision->latestTranscription->transcript_text:$answer->latestRevision->answer_text,
                    ]);
                    $valid=$canonical['criteria']===$data['criteria'] && $canonical['total_score']===($data['total_score'] ?? null);
                } catch(\Throwable) {}
                $proposals[$run->id]=['valid'=>$valid,'attempt_id'=>$attempt->id,'score'=>$valid?$data['total_score']:null,'confidence'=>$valid?$data['effective_label']:null];
            }
            $validGrades=[];
            foreach(GradeReview::whereIn('id',$approved->pluck('id'))->lazyById(25) as $review) {
                $run=$runs[$review->grading_run_id] ?? null; $proposal=$proposals[$review->grading_run_id] ?? null;
                if(!$run || !($current[$run->id] ?? false)) continue;
                try {
                    if(!$review->approved_at || !($proposal['valid'] ?? false) || $review->confidence_attempt_id!==$proposal['attempt_id']) throw new \UnexpectedValueException();
                    $answer=$answers->firstWhere('id',$review->answer_id); $criteria=$meta[$answer->question_id]['rubric']->criteria;
                    if(count($review->criteria)!==count($criteria)) throw new \UnexpectedValueException();
                    $seen=[]; $total=0;
                    foreach($review->criteria as $row) {
                        $criterion=collect($criteria)->firstWhere('criterion_id',$row['criterion_id']);
                        $points=RubricValidator::cents($row['score']);
                        if(!$criterion || isset($seen[$row['criterion_id']]) || $points>RubricValidator::cents($criterion['max_score'])) throw new \UnexpectedValueException();
                        $seen[$row['criterion_id']]=true; $total+=$points;
                    }
                    if($total!==RubricValidator::cents($review->total_score)) throw new \UnexpectedValueException();
                    $validGrades[$review->id]=$review->total_score;
                } catch(\Throwable) {}
            }
            $details=[]; $summaries=[];
            foreach($participants as $p) {
                $summary=['participant_id'=>$p->id,'participant_code'=>$p->code,'questions'=>$questions->count(),'answers'=>0,'proposals'=>0,'approved'=>0,'historical'=>0,'drafts'=>0,'approved_sum'=>'0.00','final_total'=>null,'max_score'=>number_format($max/100,2,'.',''),'complete'=>false];
                $sum=0;
                foreach($questions as $q) {
                    $answer=$answerMap[$p->id.':'.$q->id] ?? null; $run=$answer?($completed[$answer->id] ?? null):null; $grade=$answer?($approved[$answer->id] ?? null):null;
                    $head=$answer?($heads[$answer->id] ?? null):null;
                    $proposal=$run?($proposals[$run->id] ?? null):null;
                    $proposalStatus=!$run?'none':(!($current[$run->id] ?? false)?'historical':(($proposal['valid'] ?? false)?'current':'invalid'));
                    $gradeStatus=!$grade?'none':(!($current[$grade->grading_run_id] ?? false)?'historical':(isset($validGrades[$grade->id])?'current':'invalid'));
                    $last=$answer?($latestRuns[$answer->id] ?? null):null;
                    $answerStatus=!$answer?'missing':($answer->latestRevision?->source_hash!==$meta[$q->id]['hash']?'source_changed':
                        ($answer->latestRevision->content_type==='photo' && $answer->latestRevision->latestTranscription?->status!=='confirmed'?'photo_unconfirmed':'ready'));
                    $row=['participant_id'=>$p->id,'participant_code'=>$p->code,'question_number'=>$q->number,'question_id'=>$q->id,'max_score'=>$q->max_score,
                        'answer_id'=>$answer?->id,'answer_revision'=>$answer?->current_version,'answer_status'=>$answerStatus,
                        'latest_run_id'=>$last?->id,'latest_run_status'=>$last?->status,'proposal_run_id'=>$run?->id,'proposal_status'=>$proposalStatus,
                        'proposal_score'=>$proposalStatus==='current'?$proposal['score']:null,'confidence'=>$proposalStatus==='current'?$proposal['confidence']:null,
                        'review_status'=>$head?->status,'review_version'=>$head?->version,'grade_status'=>$gradeStatus,
                        'approved_id'=>$grade?->id,'approved_version'=>$grade?->version,'approved_run_id'=>$grade?->grading_run_id,
                        'approved_score'=>$gradeStatus==='current'?$validGrades[$grade->id]:null];
                    $details[]=$row;
                    if($answer) $summary['answers']++;
                    if($proposalStatus==='current') $summary['proposals']++;
                    if($head?->status==='draft') $summary['drafts']++;
                    if($gradeStatus==='historical') $summary['historical']++;
                    if($gradeStatus==='current') { $summary['approved']++; $sum+=RubricValidator::cents($row['approved_score']); }
                }
                $summary['approved_sum']=number_format($sum/100,2,'.','');
                $summary['complete']=$summary['questions']>0 && $summary['approved']===$summary['questions'];
                if($summary['complete']) $summary['final_total']=$summary['approved_sum'];
                $summaries[]=$summary;
            }
            return array_replace($base,['details'=>$details,'summary'=>$summaries]);
        });
    }
    private function current(GradingRun $run,$answer,array $meta): bool {
        $r=$answer->latestRevision;
        return $run->status==='completed' && $run->stage==='confidence' && $r && $meta['rubric']
            && $run->answer_revision_id===$r->id && $run->rubric_version_id===$meta['rubric']->id && $r->source_hash===$meta['hash']
            && ($r->content_type!=='photo' || ($r->latestTranscription?->status==='confirmed' && $r->latestTranscription?->id===$run->answer_transcription_id));
    }
}
