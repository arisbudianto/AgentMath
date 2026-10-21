<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class StudentController {
    private function participant(Request $request) { return $request->user()->studentProfile()->firstOrFail()->participant()->with('classroom')->firstOrFail(); }
    public function index(Request $request) {
        $participant=$this->participant($request);
        $answers=$participant->answers()->whereHas('assessment',fn($q)=>$q->where('classroom_id',$participant->classroom_id))->with('latestRevision.latestTranscription')->latest()->paginate(15);
        $grades=[]; foreach($answers as $answer) $grades[$answer->id]=app(\App\Services\GradePublishing::class)->visible($answer);
        return view('student',compact('participant','answers','grades'));
    }
    public function photo(Request $request,int $answer,int $revision) {
        $participant=$this->participant($request);
        $record=$participant->answers()->whereHas('assessment',fn($q)=>$q->where('classroom_id',$participant->classroom_id))->findOrFail($answer);
        $photo=$record->revisions()->where('version',$record->current_version)->findOrFail($revision);
        abort_unless($photo->content_type==='photo' && $photo->photo_path && Storage::disk('answers')->exists($photo->photo_path),404);
        return Storage::disk('answers')->response($photo->photo_path,'jawaban-saya'.($photo->photo_mime==='image/png'?'.png':'.jpg'),['Content-Type'=>$photo->photo_mime,'Content-Security-Policy'=>"default-src 'none'; sandbox"],'inline');
    }
}
