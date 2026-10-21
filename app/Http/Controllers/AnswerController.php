<?php
namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\{AnswerIntake, RubricValidator};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Storage};

class AnswerController
{
    public function index(Assessment $assessment)
    {
        Gate::authorize('view',$assessment);
        return view('answers',[
            'assessment'=>$assessment,'participants'=>$assessment->classroom?->participants()->get() ?? collect(),
            'questions'=>$assessment->questions()->get(),
            'answers'=>$assessment->answers()->with(['participant','question','latestRevision'])->latest()->paginate(30),
            'batches'=>$assessment->answerImports()->latest()->limit(10)->get(['id','status','row_count','imported_count','expires_at','created_at']),
        ]);
    }

    public function store(Request $request, Assessment $assessment, AnswerIntake $intake)
    {
        Gate::authorize('update',$assessment);
        $data=$request->validate(['participant_id'=>'required|integer','question_id'=>'required|integer','creation_key'=>'required|uuid']+$this->payloadRules());
        $answer=$intake->create($assessment,$request->user()->id,(int)$data['participant_id'],(int)$data['question_id'],$data['creation_key'],$request->only(['content_type','answer_text'])+['photo'=>$request->file('photo')]);
        return redirect()->route('answers.show',[$assessment,$answer])->with('status','Jawaban tersimpan. Belum ada nilai yang diberikan.');
    }

    public function show(Assessment $assessment, int $answer)
    {
        Gate::authorize('view',$assessment);
        $record=$assessment->answers()->with(['participant','question','latestRevision.latestTranscription'])->findOrFail($answer);
        $record->question->setRelation('assessment',$assessment);
        $approved=\App\Models\GradeReview::where('answer_id',$record->id)->where('status','approved')->orderByDesc('version')->first();
        return view('answer',['assessment'=>$assessment,'answer'=>$record,'approved'=>$approved,
            'approvedCurrent'=>$approved?app(\App\Services\GradingOrchestrator::class)->isCurrent($approved->run):false,
            'completedRuns'=>\App\Models\GradingRun::where('answer_id',$record->id)->where('status','completed')->latest()->limit(10)->get(),
            'revisions'=>$record->revisions()->with('latestTranscription')->paginate(20),'sourceHash'=>RubricValidator::sourceHash($record->question->rubricSource())]);
    }

    public function revise(Request $request, Assessment $assessment, int $answer, AnswerIntake $intake)
    {
        Gate::authorize('update',$assessment);
        $data=$request->validate(['expected_version'=>'required|integer|min:1','reason'=>'required|string|max:500']+$this->payloadRules());
        $record=$intake->revise($assessment,$request->user()->id,$answer,(int)$data['expected_version'],$data['reason'],$request->only(['content_type','answer_text'])+['photo'=>$request->file('photo')]);
        return redirect()->route('answers.show',[$assessment,$record])->with('status','Revisi baru disimpan; riwayat sebelumnya dipertahankan.');
    }

    public function photo(Assessment $assessment, int $answer, int $revision)
    {
        Gate::authorize('view',$assessment);
        $record=$assessment->answers()->findOrFail($answer)->revisions()->findOrFail($revision);
        abort_unless($record->content_type==='photo' && $record->photo_path,404);
        $disk=Storage::disk('answers'); abort_unless($disk->exists($record->photo_path),404);
        $name='jawaban-'.$answer.'-v'.$record->version.($record->photo_mime==='image/png'?'.png':'.jpg');
        return $disk->response($record->photo_path,$name,['Content-Type'=>$record->photo_mime,'Content-Security-Policy'=>"default-src 'none'; sandbox"],'inline');
    }

    private function payloadRules(): array
    {
        return [
            'content_type'=>'required|in:text,photo',
            'answer_text'=>'prohibited_unless:content_type,text|required_if:content_type,text|nullable|string|max:20000',
            'photo'=>'prohibited_unless:content_type,photo|required_if:content_type,photo|file|max:5120',
        ];
    }
}
