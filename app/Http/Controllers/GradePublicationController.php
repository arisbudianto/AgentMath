<?php
namespace App\Http\Controllers;
use App\Models\{Assessment,GradeReview};
use App\Services\GradePublishing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class GradePublicationController {
    public function index(Assessment $assessment,GradePublishing $publishing) {
        Gate::authorize('view',$assessment); $answers=$assessment->answers()->with(['participant','question'])->latest()->paginate(20);
        $rows=[]; foreach($answers as $answer) {
            $review=GradeReview::where('answer_id',$answer->id)->orderByDesc('version')->first(); $candidate=null;
            if($review) try { $candidate=app(\App\Services\GradeReviewWorkflow::class)->approvedSnapshot($answer,$review->id); } catch(\Illuminate\Validation\ValidationException) {}
            $rows[$answer->id]=['head'=>$publishing->head($answer),'visible'=>$publishing->visible($answer),'candidate'=>$candidate];
        }
        return response()->view('grade-publications',compact('assessment','answers','rows'))->header('Cache-Control','private, no-store');
    }
    public function store(Request $request,Assessment $assessment,int $answer,GradePublishing $publishing) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','action'=>'required|in:publish,revoke','review_id'=>'nullable|integer|min:1','message'=>'nullable|string|max:1000','confirmation'=>'accepted']);
        $publishing->save($assessment,$request->user()->id,$answer,$v['creation_key'],(int)$v['expected_version'],$v['action'],isset($v['review_id'])?(int)$v['review_id']:null,$v['message'] ?? '',true);
        return redirect()->route('publications.index',$assessment)->with('status','Perubahan publikasi disimpan. Hanya siswa terkait dapat melihat nilai yang diterbitkan dan masih berlaku.');
    }
}
