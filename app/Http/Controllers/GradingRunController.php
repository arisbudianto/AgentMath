<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use App\Services\GradingOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Gate};
class GradingRunController {
    public function index(Assessment $assessment) {
        Gate::authorize('view',$assessment);
        return view('grading-runs',['assessment'=>$assessment,'answers'=>$assessment->answers()->with(['participant','question'])->latest()->limit(200)->get(),'runs'=>\App\Models\GradingRun::where('assessment_id',$assessment->id)->with('answer.participant')->latest()->paginate(30),'heartbeat'=>Cache::get('grading-worker-finished')]);
    }
    public function store(Request $request,Assessment $assessment,GradingOrchestrator $workflow) {
        Gate::authorize('update',$assessment); $data=$request->validate(['answer_id'=>'required|integer','creation_key'=>'required|uuid','confirmation'=>'accepted']);
        $run=$workflow->start($assessment,$request->user()->id,(int)$data['answer_id'],$data['creation_key']);
        return redirect()->route('grading.show',[$assessment,$run])->with('status','Proses disimpan dalam antrean. Belum ada nilai yang diberikan.');
    }
    public function show(Assessment $assessment,int $run,GradingOrchestrator $workflow) {
        Gate::authorize('view',$assessment); $record=\App\Models\GradingRun::where('assessment_id',$assessment->id)->with(['answer.participant','revision','rubric','attempts'])->findOrFail($run);
        return view('grading-run',['assessment'=>$assessment,'run'=>$record,'current'=>$workflow->isCurrent($record),'canResume'=>app(\App\Services\GradingStages::class)->supports($record->stage),'events'=>$record->events()->paginate(30),'heartbeat'=>Cache::get('grading-worker-finished')]);
    }
    public function control(Request $request,Assessment $assessment,int $run,GradingOrchestrator $workflow) {
        Gate::authorize('update',$assessment); $data=$request->validate(['action'=>'required|in:resume,cancel']);
        $workflow->control($assessment,$request->user()->id,$run,$data['action']);
        return redirect()->route('grading.show',[$assessment,$run])->with('status',$data['action']==='cancel'?'Proses dibatalkan; riwayat tetap disimpan.':'Proses masuk antrean untuk melanjutkan tahap yang belum berhasil.');
    }
}
