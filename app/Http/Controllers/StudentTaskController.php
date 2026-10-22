<?php
namespace App\Http\Controllers;
use App\Models\{Assessment,StudentTask,StudentSubmission};
use App\Services\StudentTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class StudentTaskController {
    public function manage(Assessment $assessment,StudentTasks $tasks) {
        Gate::authorize('view',$assessment); $head=$tasks->head($assessment->id); $preview=null;
        try { $preview=$tasks->snapshot($assessment); } catch(\Illuminate\Validation\ValidationException) {}
        $history=StudentTask::where('assessment_id',$assessment->id)->orderByDesc('version')->limit(20)->get();
        return view('student-task-manage',compact('assessment','head','preview','history'));
    }
    public function control(Request $request,Assessment $assessment,StudentTasks $tasks) {
        Gate::authorize('update',$assessment); $v=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','action'=>'required|in:open,close','closes_at'=>'nullable|date_format:Y-m-d\TH:i','confirmation'=>'accepted']);
        $deadline=empty($v['closes_at'])?null:\Carbon\CarbonImmutable::parse($v['closes_at'],config('app.display_timezone','Asia/Jakarta'))->utc()->toIso8601String();
        $tasks->save($assessment,$request->user()->id,$v['creation_key'],(int)$v['expected_version'],$v['action'],$deadline,true);
        return redirect()->route('tasks.manage',$assessment)->with('status','Versi tugas disimpan; pengiriman hanya berlaku pada versi terbuka yang masih sesuai sumber.');
    }
    public function index(Request $request,StudentTasks $tasks) {
        $p=$tasks->participant($request->user());
        $pages=StudentTask::whereIn('id',StudentTask::selectRaw('MAX(id)')->groupBy('assessment_id'))->where('classroom_id',$p->classroom_id)->where('action','open')->where('closes_at','>',now())->latest()->paginate(10);
        $available=[]; foreach($pages as $task) if($visible=$tasks->visible($task,$p)) $available[]=$visible;
        return view('student-tasks',compact('available','pages'));
    }
    public function show(Request $request,int $task,StudentTasks $tasks) {
        $p=$tasks->participant($request->user()); $record=StudentTask::findOrFail($task); $visible=$tasks->visible($record,$p); abort_unless($visible,404);
        $answers=$record->assessment->answers()->where('participant_id',$p->id)->with('latestRevision')->get()->keyBy('question_id'); $rows=[];
        foreach($visible['questions'] as $q) {
            $answer=$answers[$q['id']] ?? null; $revision=$answer?->latestRevision;
            $rows[$q['id']]=['version'=>$answer?->current_version ?? 0,'text'=>$revision?->content_type==='text'?$revision->answer_text:'',
                'editable'=>!$answer || ($answer->created_by===$request->user()->id && $revision?->created_by===$request->user()->id && !\App\Models\GradeReview::where('answer_id',$answer->id)->exists()),
                'sent'=>StudentSubmission::where('student_task_id',$task)->where('participant_id',$p->id)->where('question_id',$q['id'])->count()];
        }
        return view('student-task-answer',compact('visible','rows'));
    }
    public function submit(Request $request,int $task,int $question,StudentTasks $tasks) {
        $v=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','content_type'=>'required|in:text,photo','answer_text'=>'required_if:content_type,text|nullable|string|max:20000','photo'=>'required_if:content_type,photo|nullable|file|max:5120','confirmation'=>'accepted']);
        $tasks->submit($request->user(),$task,$question,$v['creation_key'],(int)$v['expected_version'],['content_type'=>$v['content_type'],'answer_text'=>$v['answer_text'] ?? '','photo'=>$request->file('photo')],true);
        $participant=$tasks->participant($request->user());
        $path=app(\App\Services\LearningPaths::class)->forParticipant($participant);
        $message=($v['content_type']==='photo')?'Jawaban terkirim. Foto perlu diperiksa transkripsinya oleh pengajar; belum ada nilai otomatis.':'Jawaban terkirim. Guru akan memeriksa sebelum nilai diterbitkan.';
        return redirect()->route($path?'learning.index':'student.index')->with('status',$message);
    }
}
