<?php
namespace App\Http\Controllers;
use App\Models\{Assessment,EvalDataset};
use App\Services\{ResearchQuality,ResearchQualityReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class ResearchQualityController {
    private function dataset(Assessment $a,int $id): EvalDataset { Gate::authorize('view',$a); return EvalDataset::where('assessment_id',$a->id)->findOrFail($id); }
    public function index(Assessment $assessment,int $dataset,ResearchQualityReport $reports) {
        $d=$this->dataset($assessment,$dataset); $report=$d->status==='frozen'?$reports->build($d):null; $cases=$d->cases()->orderBy('id')->get();
        return response()->view('research-quality',['assessment'=>$assessment,'dataset'=>$d,'report'=>$report,'cases'=>$cases])->header('Cache-Control','private, no-store');
    }
    public function form(Assessment $assessment,int $dataset,int $case,string $mode,ResearchQuality $quality) {
        $d=$this->dataset($assessment,$dataset); abort_unless(in_array($mode,ResearchQuality::KINDS,true),404); $c=$d->cases()->findOrFail($case);
        abort_if($mode==='ocr' && !$c->answer_transcription_id,404);
        $latest=$quality->latest($c->id,$mode); $pair=$mode==='adjudication'?['a'=>$quality->latest($c->id,'human_a'),'b'=>$quality->latest($c->id,'human_b')]:[];
        return response()->view('research-annotation',['assessment'=>$assessment,'dataset'=>$d,'case'=>$c,'mode'=>$mode,'latest'=>$latest,'pair'=>$pair,'reference'=>$c->references()->first()])->header('Cache-Control','private, no-store');
    }
    public function store(Request $request,Assessment $assessment,int $dataset,int $case,string $mode,ResearchQuality $quality) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','independent'=>'accepted','reason'=>'required|string|max:2000',
            'rater_code'=>'nullable|string|max:40','criteria'=>'nullable|array|max:20','reference_text'=>'nullable|string|max:20000','a_id'=>'nullable|integer|min:1','b_id'=>'nullable|integer|min:1','reference_version'=>'nullable|integer|min:0']);
        $input=array_intersect_key($v,array_flip(['reason','rater_code','criteria','text','a_id','b_id','reference_version']));
        if(isset($v['reference_text'])) $input['text']=$v['reference_text'];
        $quality->save($assessment,$request->user()->id,$dataset,$case,$mode,$v['creation_key'],(int)$v['expected_version'],$input,true);
        return redirect()->route('research.form',[$assessment,$dataset,$case,$mode])->with('status','Versi anotasi manusia disimpan. Acuan, sumber dan histori tetap terpisah dari nilai operasional.');
    }
    public function export(Assessment $assessment,int $dataset,ResearchQualityReport $reports) {
        $d=$this->dataset($assessment,$dataset); $report=$reports->build($d);
        $fields=['case_id','status','human_a_id','human_b_id','adjudication_id','ocr_id','human_a_total','human_b_total','absolute_difference','criterion_disagreements','ai_ocr_status','confirmed_ocr_status','ai_cer_percent','confirmed_cer_percent','ai_edits','confirmed_edits','reference_characters','photo_revision_id','confirmed_transcription_id','ocr_reference_version','ai_transcription_id','ai_provider','ai_model','ai_prompt_version'];
        return response()->streamDownload(function() use($d,$report,$fields) {
            $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,array_merge(['dataset_id','data_kind','reference_policy','manifest_hash'],$fields),',','"','');
            foreach($report['rows'] as $row) { $values=[$d->id,$d->data_kind,$d->protocol['reference_policy'],$d->manifest_hash]; foreach($fields as $f) $values[]=$row[$f] ?? ''; fputcsv($out,array_map([ResultReportController::class,'safeCell'],$values),',','"',''); } fclose($out);
        },'agentmath-reference-quality-'.$d->id.'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
