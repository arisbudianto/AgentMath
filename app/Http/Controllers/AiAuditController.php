<?php
namespace App\Http\Controllers;
use App\Models\{AiCall,Assessment,AnswerRevision,GradingAttempt,EvalAttempt,EvalSample,GradingRun};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AiAuditController {
    private function selection(Request $request,Assessment $assessment): array {
        Gate::authorize('view',$assessment);
        $v=$request->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','kind'=>'nullable|in:rubric,transcription,grading,baseline','outcome'=>'nullable|in:pending,validated,rejected','run'=>'nullable|integer|min:1']);
        $from=CarbonImmutable::parse($v['from'] ?? CarbonImmutable::now('UTC')->subDays(29)->toDateString(),'UTC')->startOfDay();
        $to=CarbonImmutable::parse($v['to'] ?? CarbonImmutable::now('UTC')->toDateString(),'UTC')->endOfDay();
        if($from->gt($to) || $from->diffInDays($to)>93) throw ValidationException::withMessages(['from'=>'Pilih rentang berurutan maksimal 93 hari (UTC).']);
        $filters=['from'=>$from->toDateString(),'to'=>$to->toDateString(),'kind'=>$v['kind'] ?? '','outcome'=>$v['outcome'] ?? '','run'=>$v['run'] ?? ''];
        $query=AiCall::where('assessment_id',$assessment->id)->whereBetween('started_at',[$from,$to]);
        if($filters['run']!=='') {
            $run=GradingRun::where('assessment_id',$assessment->id)->findOrFail($filters['run']);
            if($filters['kind']!=='' && $filters['kind']!=='grading') throw ValidationException::withMessages(['kind'=>'Filter proses M05 memerlukan jenis Penilaian M05.']);
            $filters['kind']='grading'; $query->whereIn('source_id',$run->attempts()->select('id'));
        }
        foreach(['kind','outcome'] as $k) if($filters[$k]!=='') $query->where($k,$filters[$k]);
        return [$query,$filters];
    }
    public function index(Request $request,Assessment $assessment) {
        [$query,$filters]=$this->selection($request,$assessment);
        $summary=(clone $query)->selectRaw('COUNT(*) as calls, SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END) as rejected, SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN input_tokens IS NOT NULL AND output_tokens IS NOT NULL THEN 1 ELSE 0 END) as usage_known, COALESCE(SUM(input_tokens),0) as input_sum, COALESCE(SUM(output_tokens),0) as output_sum',['rejected','pending'])->first();
        $costs=(clone $query)->whereNotNull('estimated_cost')->selectRaw('currency, COUNT(*) as known, SUM(estimated_cost) as amount')->groupBy('currency')->get();
        $calls=$query->orderByDesc('id')->paginate(25)->withQueryString();
        $links=[]; foreach($calls as $call) $links[$call->id]=$this->source($call,$assessment);
        return response()->view('ai-audit',compact('assessment','filters','calls','summary','costs','links'))->header('Cache-Control','private, no-store');
    }
    private function source(AiCall $call,Assessment $a): ?array {
        if($call->kind==='rubric') {
            $q=$a->questions()->find($call->source_id); return $q?['label'=>'Soal #'.$q->number.' · rubrik','url'=>route('rubrics.index',[$a,$q])]:null;
        }
        if($call->kind==='transcription') {
            $r=AnswerRevision::find($call->source_id); if(!$r || !$a->answers()->whereKey($r->answer_id)->exists()) return null;
            return ['label'=>'Revisi foto #'.$r->id,'url'=>route('transcriptions.show',[$a,$r->answer_id,$r->id])];
        }
        if($call->kind==='grading') {
            $attempt=GradingAttempt::find($call->source_id); $run=$attempt?GradingRun::where('assessment_id',$a->id)->find($attempt->grading_run_id):null;
            return $run?['label'=>'Proses #'.$run->id.' · percobaan '.$attempt->number.' · '.$attempt->status,'url'=>route('grading.show',[$a,$run])]:null;
        }
        $attempt=EvalAttempt::find($call->source_id); $s=$attempt?EvalSample::find($attempt->eval_sample_id):null;
        if(!$s || $s->experiment->dataset->assessment_id!==$a->id) return null;
        return ['label'=>'Baseline #'.$s->id.' · percobaan '.$attempt->number.' · '.$attempt->status,'url'=>route('evaluations.sample',[$a,$s->experiment->eval_dataset_id,$s->eval_experiment_id,$s])];
    }
    public function export(Request $request,Assessment $assessment) {
        [$query]=$this->selection($request,$assessment);
        $query->where('id','<=',(clone $query)->max('id') ?? 0);
        if((clone $query)->count()>10000) throw ValidationException::withMessages(['from'=>'Ekspor maksimal 10.000 panggilan. Persempit rentang/filter.']);
        $fields=['id','kind','source_id','correlation_id','stage','provider','model','prompt_version','state','outcome','http_status','duration_ms','input_tokens','output_tokens','cached_tokens','currency','estimated_cost','started_at','finished_at'];
        return response()->streamDownload(function() use($query,$fields) {
            $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,array_merge($fields,['tariff_version','input_per_million','cached_per_million','output_per_million']),',','"','');
            foreach($query->orderBy('id')->lazyById(100) as $call) {
                $row=[]; foreach($fields as $f) $row[]=$call->$f===null?'':(string)$call->$f;
                foreach(['version','input','cached','output'] as $k) $row[]=$call->tariff[$k] ?? '';
                fputcsv($out,array_map([ResultReportController::class,'safeCell'],$row),',','"','');
            } fclose($out);
        },'agentmath-audit-'.$assessment->id.'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
