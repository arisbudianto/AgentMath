<?php
namespace App\Http\Controllers;
use App\Models\{Assessment,GradeReview,GradingRun};
use App\Services\{GradeReviewWorkflow,GradingOrchestrator};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GradeReviewController {
    public function show(Assessment $assessment,int $run,GradeReviewWorkflow $workflow) {
        Gate::authorize('view',$assessment);
        $record=GradingRun::where('assessment_id',$assessment->id)->with(['answer.participant','revision','attempts'])->findOrFail($run);
        $proposal=$workflow->proposal($record)['data'];
        $latest=GradeReview::where('answer_id',$record->answer_id)->orderByDesc('version')->first();
        $saved=GradeReview::where('grading_run_id',$record->id)->orderByDesc('version')->first();
        $approved=GradeReview::where('answer_id',$record->answer_id)->where('status','approved')->orderByDesc('version')->first();
        return view('grade-review',[
            'assessment'=>$assessment,'run'=>$record,'proposal'=>$proposal,'latest'=>$latest,'saved'=>$saved,'approved'=>$approved,
            'current'=>app(GradingOrchestrator::class)->isCurrent($record),
            'approvedCurrent'=>$approved?app(GradingOrchestrator::class)->isCurrent($approved->run):false,
            'history'=>GradeReview::where('answer_id',$record->answer_id)->with('actor')->orderByDesc('version')->paginate(20),
        ]);
    }
    public function store(Request $request,Assessment $assessment,int $run,GradeReviewWorkflow $workflow) {
        Gate::authorize('update',$assessment);
        $data=$request->validate([
            'creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','action'=>'required|in:draft,approve',
            'criteria'=>'sometimes|array|max:20','reason'=>'sometimes|nullable|string|max:2000',
            'confirmation'=>'accepted_if:action,approve',
        ]);
        $review=$workflow->save($assessment,$request->user()->id,$run,$data['creation_key'],(int)$data['expected_version'],$data['action'],
            $data['criteria'] ?? [],$data['reason'] ?? '',$request->boolean('confirmation'));
        return redirect()->route('reviews.show',[$assessment,$run])->with('status',$review->status==='approved'?'Nilai disahkan oleh pengajar. Riwayat keputusan tetap disimpan.':'Draf pemeriksaan disimpan. Periksa kembali sebelum menyetujui nilai.');
    }
}
