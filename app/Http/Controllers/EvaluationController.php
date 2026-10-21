<?php
namespace App\Http\Controllers;
use App\Models\{Assessment,EvalDataset};
use App\Services\{EvaluationData,EvaluationMetrics,EvaluationWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvaluationController {
    private function dataset(Assessment $a,int $dataset): EvalDataset { Gate::authorize('view',$a); return EvalDataset::where('assessment_id',$a->id)->findOrFail($dataset); }
    public function index(Assessment $assessment) {
        Gate::authorize('view',$assessment);
        return view('evaluations',['assessment'=>$assessment,'datasets'=>EvalDataset::where('assessment_id',$assessment->id)->latest()->paginate(20)]);
    }
    public function store(Request $request,Assessment $assessment,EvaluationData $workflow) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','name'=>'required|string|max:160','data_kind'=>'required|in:synthetic,real',
            'goal'=>'required|string|max:2000','target'=>'required|string|max:2000','tolerance'=>'required|numeric|between:0,100']);
        $d=$workflow->dataset($assessment,$request->user()->id,$v['creation_key'],$v['name'],$v['data_kind'],$v['goal'],$v['target'],(string)$v['tolerance']);
        return redirect()->route('evaluations.show',[$assessment,$d]);
    }
    public function show(Assessment $assessment,int $dataset) {
        $d=$this->dataset($assessment,$dataset);
        return view('evaluation-dataset',['assessment'=>$assessment,'dataset'=>$d,'cases'=>$d->cases()->with('latestReference')->orderBy('id')->paginate(20),
            'experiments'=>$d->experiments()->withCount('samples')->latest()->limit(30)->get(),
            'answers'=>$assessment->answers()->with(['participant','question'])->latest()->limit(200)->get()]);
    }
    public function add(Request $request,Assessment $assessment,int $dataset,EvaluationData $workflow) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','answer_id'=>'required|integer|min:1','split'=>'required|in:tuning,test']);
        $c=$workflow->add($assessment,$request->user()->id,$dataset,(int)$v['answer_id'],$v['split'],$v['creation_key']);
        return redirect()->route('evaluations.case',[$assessment,$dataset,$c]);
    }
    public function case(Assessment $assessment,int $dataset,int $case) {
        $d=$this->dataset($assessment,$dataset); $c=$d->cases()->findOrFail($case);
        return view('evaluation-case',['assessment'=>$assessment,'dataset'=>$d,'case'=>$c,'label'=>$c->references()->first(),'history'=>$c->references()->paginate(20)]);
    }
    public function label(Request $request,Assessment $assessment,int $dataset,int $case,EvaluationData $workflow) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','criteria'=>'required|array|max:20',
            'reason'=>'required|string|max:2000','independent'=>'accepted','label_seconds'=>'nullable|integer|between:1,86400']);
        $workflow->label($assessment,$request->user()->id,$dataset,$case,$v['creation_key'],(int)$v['expected_version'],$v['criteria'],$v['reason'],true,isset($v['label_seconds'])?(int)$v['label_seconds']:null);
        return redirect()->route('evaluations.case',[$assessment,$dataset,$case])->with('status','Versi acuan manusia disimpan; nilai operasional tidak berubah.');
    }
    public function freeze(Request $request,Assessment $assessment,int $dataset,EvaluationData $workflow) {
        Gate::authorize('update',$assessment); $request->validate(['confirmation'=>'accepted']);
        $workflow->freeze($assessment,$request->user()->id,$dataset,true);
        return redirect()->route('evaluations.show',[$assessment,$dataset])->with('status','Protokol, snapshot, pembagian soal dan acuan dibekukan.');
    }
    public function start(Request $request,Assessment $assessment,int $dataset,EvaluationWorkflow $workflow) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','split'=>'required|in:tuning,test','confirmation'=>'accepted','reuse'=>'sometimes|accepted']);
        $e=$workflow->start($assessment,$request->user()->id,$dataset,$v['split'],$v['creation_key'],true,$request->boolean('reuse'));
        return redirect()->route('evaluations.experiment',[$assessment,$dataset,$e])->with('status','Baseline baru masuk antrean. Acuan manusia dan hasil multi-agent tidak dikirim ke baseline.');
    }
    public function experiment(Assessment $assessment,int $dataset,int $experiment,EvaluationMetrics $metrics) {
        $d=$this->dataset($assessment,$dataset); $e=$d->experiments()->findOrFail($experiment);
        return view('evaluation-experiment',['assessment'=>$assessment,'dataset'=>$d,'experiment'=>$e,'report'=>$metrics->report($e)]);
    }
    public function control(Request $request,Assessment $assessment,int $dataset,int $experiment,EvaluationWorkflow $workflow) {
        Gate::authorize('update',$assessment); $v=$request->validate(['action'=>'required|in:cancel,retry','sample'=>'required_if:action,retry|integer|min:1']);
        if($v['action']==='cancel') $workflow->cancel($assessment,$request->user()->id,$dataset,$experiment);
        else $workflow->retry($assessment,$request->user()->id,$dataset,$experiment,(int)$v['sample']);
        return redirect()->route('evaluations.experiment',[$assessment,$dataset,$experiment]);
    }
    public function sample(Assessment $assessment,int $dataset,int $experiment,int $sample) {
        $d=$this->dataset($assessment,$dataset); $e=$d->experiments()->findOrFail($experiment); $s=$e->samples()->with(['case','reference','attempts'])->findOrFail($sample);
        return view('evaluation-sample',['assessment'=>$assessment,'dataset'=>$d,'experiment'=>$e,'sample'=>$s,
            'observations'=>$s->observations()->orderByDesc('id')->get(),'baseline'=>$s->attempts->where('status','succeeded')->first()?->output]);
    }
    public function observe(Request $request,Assessment $assessment,int $dataset,int $experiment,int $sample,EvaluationWorkflow $workflow) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','method'=>'required|in:multi,baseline',
            'category'=>'required|in:none,arithmetic,procedure,transcription,rubric,ambiguous,other','note'=>'required|string|max:2000','review_seconds'=>'nullable|integer|between:1,86400']);
        $workflow->observe($assessment,$request->user()->id,$dataset,$experiment,$sample,$v['creation_key'],(int)$v['expected_version'],$v['method'],$v['category'],$v['note'],isset($v['review_seconds'])?(int)$v['review_seconds']:null);
        return redirect()->route('evaluations.sample',[$assessment,$dataset,$experiment,$sample])->with('status','Catatan analisis berversi disimpan; skor acuan dan hasil AI tetap dikunci.');
    }
    public function export(Assessment $assessment,int $dataset,int $experiment,EvaluationMetrics $metrics) {
        $d=$this->dataset($assessment,$dataset); $e=$d->experiments()->findOrFail($experiment); $report=$metrics->report($e);
        $fields=['sample_id','case_id','status','reference_version','human_score','multi_score','baseline_score','multi_confidence','multi_wrong','baseline_wrong','paired','multi_normalized_error','baseline_normalized_error','multi_review_seconds','baseline_review_seconds','multi_observation_version','baseline_observation_version','error_code'];
        return response()->streamDownload(function() use($report,$d,$e,$fields) {
            $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,array_merge(['dataset_id','data_kind','split','experiment_id','test_reused','manifest_hash','generated_at'],$fields),',','"','');
            foreach($report['rows'] as $row) {
                $values=[$d->id,$d->data_kind,$e->split,$e->id,(int)$e->test_reused,$d->manifest_hash,$report['generated_at']];
                foreach($fields as $field) $values[]=is_bool($row[$field] ?? null)?(int)$row[$field]:($row[$field] ?? '');
                fputcsv($out,array_map([ResultReportController::class,'safeCell'],$values),',','"','');
            } fclose($out);
        },'agentmath-evaluation-'.$e->id.'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
