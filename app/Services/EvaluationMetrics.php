<?php
namespace App\Services;
use App\Models\EvalExperiment;

class EvaluationMetrics {
    public function marker(array $result,array $input): array {
        $rows=$result['criteria'] ?? []; foreach($rows as &$row) $row['score']=RubricValidator::cents($row['score'])/100; unset($row);
        $canonical=app(GradingResultValidator::class)->marker(['criteria'=>$rows],$input);
        if($canonical['total_score']!==($result['total_score'] ?? null) || $canonical['criteria']!==($result['criteria'] ?? null)) throw new \UnexpectedValueException('Hasil eksperimen tidak valid.');
        return $canonical;
    }
    private function compare(array $actual,array $reference,array $input,int $tolerance): array {
        $total=RubricValidator::cents($actual['total_score']); $human=RubricValidator::cents($reference['total_score']); $max=RubricValidator::cents($input['source']['max_score']);
        $diff=abs($total-$human); $wrong=$diff*10000>$max*$tolerance; $equal=0; $criterionError=0;
        foreach($input['rubric']['criteria'] as $i=>$c) {
            $delta=abs(RubricValidator::cents($actual['criteria'][$i]['score'])-RubricValidator::cents($reference['criteria'][$i]['score']));
            $cmax=RubricValidator::cents($c['max_score']); if($delta===0) $equal++;
            if($delta*10000>$cmax*$tolerance) $wrong=true;
            $criterionError+=100*$delta/$cmax;
        }
        return ['score'=>$actual['total_score'],'exact'=>$diff===0,'absolute_error'=>$diff/100,'normalized_error'=>100*$diff/$max,
            'criterion_exact'=>$equal,'criterion_count'=>count($input['rubric']['criteria']),'criterion_normalized_error_sum'=>$criterionError,'wrong'=>$wrong];
    }
    public function report(EvalExperiment $experiment): array {
        $dataset=$experiment->dataset; $data=app(EvaluationData::class); $data->manifest($dataset);
        $metrics=[]; foreach(['multi','baseline'] as $method) $metrics[$method]=['n'=>0,'exact'=>0,'absolute_error_sum'=>0,'normalized_error_sum'=>0,'criterion_exact'=>0,'criterion_n'=>0,'criterion_error_sum'=>0,'wrong'=>0,'high_n'=>0,'high_wrong'=>0,
            'input_tokens'=>0,'output_tokens'=>0,'usage_n'=>0,'duration_ms_sum'=>0,'duration_n'=>0,'review_seconds_sum'=>0,'review_n'=>0];
        $rows=[]; $statuses=[]; $paired=0; $invalid=0; $labelTime=0; $labelN=0; $categories=['multi'=>[],'baseline'=>[]];
        foreach($experiment->samples()->with(['case','reference'])->lazyById(10) as $sample) {
            $statuses[$sample->status]=($statuses[$sample->status] ?? 0)+1;
            $row=['sample_id'=>$sample->id,'case_id'=>$sample->eval_case_id,'status'=>$sample->status,'reference_version'=>$sample->reference->version,
                'human_score'=>null,'multi_score'=>null,'baseline_score'=>null,'multi_confidence'=>null,'multi_wrong'=>null,'baseline_wrong'=>null,'paired'=>false,'error_code'=>null];
            try {
                app(EvaluationWorkflow::class)->pinned($dataset,$sample->case,$sample->reference);
                $input=$sample->case->input_snapshot; $r=$sample->reference; $row['human_score']=$r->total_score;
                if($r->label_seconds!==null) { $labelTime+=$r->label_seconds; $labelN++; }
                if($sample->status==='succeeded') {
                    $attempt=$sample->attempts()->where('status','succeeded')->first();
                    if(!$attempt || ($attempt->output['metadata']['evaluation_protocol'] ?? '')!=='m08_single_marker_v1') throw new \UnexpectedValueException();
                    $results=['multi'=>$this->marker($sample->multi_result,$input),'baseline'=>$this->marker($attempt->output['data'],$input)];
                    if(!in_array($sample->multi_result['effective_label'] ?? null,['High','Low'],true)) throw new \UnexpectedValueException();
                    $ref=['criteria'=>$r->criteria,'total_score'=>$r->total_score]; $comparisons=[];
                    foreach($results as $method=>$actual) $comparisons[$method]=$this->compare($actual,$ref,$input,$dataset->protocol['tolerance_bps']);
                    $row['paired']=true; $paired++;
                    foreach($comparisons as $method=>$comparison) {
                        $m=&$metrics[$method]; $m['n']++; $m['exact']+=(int)$comparison['exact']; $m['absolute_error_sum']+=$comparison['absolute_error'];
                        $m['normalized_error_sum']+=$comparison['normalized_error']; $m['criterion_exact']+=$comparison['criterion_exact'];
                        $m['criterion_n']+=$comparison['criterion_count']; $m['criterion_error_sum']+=$comparison['criterion_normalized_error_sum']; $m['wrong']+=(int)$comparison['wrong'];
                        if($method==='multi' && $sample->multi_result['effective_label']==='High') { $m['high_n']++; $m['high_wrong']+=(int)$comparison['wrong']; }
                        $meta=$method==='multi'?$sample->multi_metadata:$attempt->output['metadata'];
                        if(is_int($meta['usage']['input_tokens'] ?? null) && is_int($meta['usage']['output_tokens'] ?? null)) {
                            $m['input_tokens']+=$meta['usage']['input_tokens']; $m['output_tokens']+=$meta['usage']['output_tokens']; $m['usage_n']++;
                        }
                        if(is_int($meta['duration_ms'] ?? null)) { $m['duration_ms_sum']+=$meta['duration_ms']; $m['duration_n']++; }
                        $observation=$sample->observations()->where('method',$method)->orderByDesc('version')->first();
                        if($observation) {
                            $categories[$method][$observation->category]=($categories[$method][$observation->category] ?? 0)+1;
                            if($observation->review_seconds!==null) { $m['review_seconds_sum']+=$observation->review_seconds; $m['review_n']++; }
                        }
                        $row[$method.'_score']=$comparison['score']; $row[$method.'_wrong']=$comparison['wrong'];
                        $row[$method.'_normalized_error']=round($comparison['normalized_error'],4);
                        $row[$method.'_review_seconds']=$observation?->review_seconds; $row[$method.'_observation_version']=$observation?->version;
                        unset($m);
                    }
                    $row['multi_confidence']=$sample->multi_result['effective_label'];
                } else $row['error_code']=$sample->attempts()->orderByDesc('number')->first()?->error_code;
            } catch(\Throwable) { $row['status']='invalid_snapshot_or_result'; $invalid++; }
            $rows[]=$row;
        }
        foreach($metrics as &$m) {
            $m['exact_percent']=$m['n']?round(100*$m['exact']/$m['n'],4):null;
            $m['mae']=$m['n']?round($m['absolute_error_sum']/$m['n'],4):null;
            $m['normalized_mae_percent']=$m['n']?round($m['normalized_error_sum']/$m['n'],4):null;
            $m['criterion_exact_percent']=$m['criterion_n']?round(100*$m['criterion_exact']/$m['criterion_n'],4):null;
            $m['criterion_normalized_mae_percent']=$m['criterion_n']?round($m['criterion_error_sum']/$m['criterion_n'],4):null;
            $m['high_error_percent']=$m['high_n']?round(100*$m['high_wrong']/$m['high_n'],4):null;
            $m['mean_duration_ms']=$m['duration_n']?round($m['duration_ms_sum']/$m['duration_n'],2):null;
            $m['mean_review_seconds']=$m['review_n']?round($m['review_seconds_sum']/$m['review_n'],2):null;
        } unset($m);
        return ['rows'=>$rows,'metrics'=>$metrics,'paired'=>$paired,'total'=>count($rows),'invalid'=>$invalid,'statuses'=>$statuses,'categories'=>$categories,
            'reference_label_seconds_mean'=>$labelN?round($labelTime/$labelN,2):null,'reference_label_time_n'=>$labelN,'generated_at'=>now()->toIso8601String()];
    }
}
