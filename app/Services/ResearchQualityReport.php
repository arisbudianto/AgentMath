<?php
namespace App\Services;
use App\Models\{EvalDataset,EvalReference};
class ResearchQualityReport {
    public function build(EvalDataset $dataset): array {
        $entries=app(EvaluationData::class)->manifest($dataset); $quality=app(ResearchQuality::class);
        $out=['total'=>count($entries),'invalid'=>0,'human_pairs'=>0,'adjudicated'=>0,'total_exact'=>0,'error_sum'=>0,'normal_error_sum'=>0,'criterion_exact'=>0,'criterion_n'=>0,'photo_cases'=>0,'ocr_labeled'=>0,'rows'=>[]];
        foreach(['ai','confirmed'] as $m) $out[$m]=['computed'=>0,'missing'=>0,'limited'=>0,'edits'=>0,'characters'=>0,'cer_percent'=>null];
        foreach($entries as $entry) {
            $row=['case_id'=>$entry['case_id'],'status'=>'valid','human_a_id'=>null,'human_b_id'=>null,'adjudication_id'=>null,'ocr_id'=>null,'human_a_total'=>null,'human_b_total'=>null,'absolute_difference'=>null,'criterion_disagreements'=>null,'ai_ocr_status'=>null,'confirmed_ocr_status'=>null,'ai_cer_percent'=>null,'confirmed_cer_percent'=>null,'ai_edits'=>null,'confirmed_edits'=>null,'reference_characters'=>null];
            try {
                $case=$dataset->cases()->findOrFail($entry['case_id']); if($case->answer_transcription_id) $out['photo_cases']++;
                $ref=EvalReference::findOrFail($entry['reference_id']); app(EvaluationWorkflow::class)->pinned($dataset,$case,$ref); $p=$quality->pinned($entry,$case);
                foreach(ResearchQuality::KINDS as $kind) $row[$kind.'_id']=$p[$kind]->id ?? null;
                if(isset($p['human_a'],$p['human_b'])) {
                    $a=$p['human_a']->data; $b=$p['human_b']->data; $diff=abs(RubricValidator::cents($a['total_score'])-RubricValidator::cents($b['total_score'])); $unequal=0;
                    foreach($a['criteria'] as $i=>$score) if($score['score']!==$b['criteria'][$i]['score']) $unequal++;
                    $row['human_a_total']=$a['total_score']; $row['human_b_total']=$b['total_score']; $row['absolute_difference']=$diff/100; $row['criterion_disagreements']=$unequal;
                    $out['human_pairs']++; $out['total_exact']+=(int)($diff===0); $out['error_sum']+=$diff/100; $out['normal_error_sum']+=100*$diff/RubricValidator::cents($case->input_snapshot['source']['max_score']);
                    $out['criterion_n']+=count($a['criteria']); $out['criterion_exact']+=count($a['criteria'])-$unequal;
                }
                if(isset($p['adjudication'])) $out['adjudicated']++;
                if(isset($p['ocr'])) {
                    $ocr=$p['ocr']->data; $comparisons=[]; $row['photo_revision_id']=$case->answer_revision_id; $row['confirmed_transcription_id']=$case->answer_transcription_id; $row['ocr_reference_version']=$p['ocr']->version;
                    foreach(['ai_transcription_id','ai_provider','ai_model','ai_prompt_version'] as $field) $row[$field]=$ocr['source'][$field] ?? null;
                    foreach(['ai'=>'ai_text','confirmed'=>'confirmed_text'] as $m=>$field) $comparisons[$m]=$quality->cer($ocr['source'][$field],$ocr['text']);
                    foreach($comparisons as $m=>$c) {
                        $out[$m][$c['status']]++; $row[$m.'_ocr_status']=$c['status']; $row[$m.'_cer_percent']=$c['cer_percent']; $row[$m.'_edits']=$c['edits'];
                        if($c['status']==='computed') { $out[$m]['edits']+=$c['edits']; $out[$m]['characters']+=$c['characters']; $row['reference_characters']=$c['characters']; }
                    } $out['ocr_labeled']++;
                }
            } catch(\Throwable) { $row['status']='invalid_snapshot_or_annotation'; $out['invalid']++; }
            $out['rows'][]=$row;
        }
        $out['total_exact_percent']=$out['human_pairs']?round(100*$out['total_exact']/$out['human_pairs'],4):null;
        $out['mae']=$out['human_pairs']?round($out['error_sum']/$out['human_pairs'],4):null;
        $out['normalized_mae_percent']=$out['human_pairs']?round($out['normal_error_sum']/$out['human_pairs'],4):null;
        $out['criterion_exact_percent']=$out['criterion_n']?round(100*$out['criterion_exact']/$out['criterion_n'],4):null;
        foreach(['ai','confirmed'] as $m) if($out[$m]['characters']) $out[$m]['cer_percent']=round(100*$out[$m]['edits']/$out[$m]['characters'],4);
        return $out;
    }
}
