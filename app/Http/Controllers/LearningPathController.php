<?php
namespace App\Http\Controllers;
use App\Models\{BankBook,BankPage,Classroom,LearningPath};
use App\Services\{LearningPaths,QuestionBank,StudentTasks};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate,Storage};

class LearningPathController {
    public function manage(Classroom $classroom) {
        Gate::authorize('view',$classroom);
        $path=LearningPath::with(['book','preAssessment','postAssessment'])->where('classroom_id',$classroom->id)->first();
        $books=BankBook::where('grade',1)->orderBy('title')->get();
        $assessments=$classroom->assessments()->with('questions')->orderBy('title')->get();
        $students=$path?$classroom->participants()->with('studentProfile')->get():collect();
        return view('learning-path-manage',compact('classroom','path','books','assessments','students'));
    }
    public function store(Request $request,Classroom $classroom,LearningPaths $paths) {
        Gate::authorize('update',$classroom);
        $v=$request->validate(['bank_book_id'=>'required|integer|min:1','pre_assessment_id'=>'required|integer|min:1','post_assessment_id'=>'required|integer|min:1']);
        $paths->create($classroom,$request->user(),(int)$v['bank_book_id'],(int)$v['pre_assessment_id'],(int)$v['post_assessment_id']);
        return redirect()->route('learning.manage',$classroom)->with('status','Jalur belajar disiapkan. Buka tugas pretest dan post-test dari masing-masing asesmen saat waktunya tiba.');
    }
    private function context(Request $request,LearningPaths $paths): array {
        $p=app(StudentTasks::class)->participant($request->user());
        $path=$paths->forParticipant($p);
        return [$p,$path,$path?$paths->current($path,$p):null];
    }
    public function index(Request $request,LearningPaths $paths) {
        [$participant,$path,$current]=$this->context($request,$paths);
        $chapters=$path?$path->book->chapters()->orderBy('number')->get():collect();
        $task=$path && in_array($current,[0,9],true)?$paths->taskForStage($path,$participant,$current):null;
        return response()->view('learning-path',compact('participant','path','current','chapters','task'))->header('Cache-Control','private, no-store');
    }
    public function material(Request $request,int $chapter,LearningPaths $paths) {
        [$participant,$path,$current]=$this->context($request,$paths); abort_unless($path && $chapter>=1 && $chapter<=8 && $chapter<=$current,404);
        $unit=$paths->chapter($path,$chapter); $pages=$paths->pages($unit); $visited=$paths->visited($path,$participant,$unit);
        $sets=$unit->sets()->orderBy('start_page')->get();
        return response()->view('learning-material',compact('path','current','unit','pages','visited','sets'))->header('Cache-Control','private, no-store');
    }
    public function page(Request $request,int $chapter,int $page,LearningPaths $paths) {
        [$participant,$path,$current]=$this->context($request,$paths); abort_unless($path && $chapter>=1 && $chapter<=8 && $chapter<=$current,404);
        $unit=$paths->chapter($path,$chapter); $record=$path->book->pages()->where('number',$page)->firstOrFail();
        abort_unless($page>=$unit->start_page && $page<=$unit->end_page,404);
        app(QuestionBank::class)->validPage($record);
        $read=in_array($record->id,$paths->visited($path,$participant,$unit),true);
        $next=$page<$unit->end_page?$page+1:null;
        return response()->view('learning-page',compact('path','current','unit','record','read','next'))->header('Cache-Control','private, no-store');
    }
    public function image(Request $request,int $chapter,int $page,LearningPaths $paths,QuestionBank $bank) {
        [$participant,$path,$current]=$this->context($request,$paths); abort_unless($path && $chapter>=1 && $chapter<=8 && $chapter<=$current,404);
        $unit=$paths->chapter($path,$chapter); abort_unless($page>=$unit->start_page && $page<=$unit->end_page,404);
        $record=$path->book->pages()->where('number',$page)->firstOrFail(); $bank->validPage($record);
        return response()->file(Storage::disk('local')->path($record->imagePath()),['Content-Type'=>'image/webp','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function read(Request $request,int $chapter,int $page,LearningPaths $paths) {
        [$participant,$path]=$this->context($request,$paths); abort_unless($path,404);
        $unit=$paths->chapter($path,$chapter); abort_unless($page>=$unit->start_page && $page<=$unit->end_page,404);
        $record=$path->book->pages()->where('number',$page)->firstOrFail();
        $paths->markPage($path,$participant,$record);
        $next=$page<$unit->end_page?route('learning.page',[$chapter,$page+1]):route('learning.material',$chapter);
        return redirect($next)->with('status','Halaman ditandai selesai.');
    }
    public function complete(Request $request,int $chapter,LearningPaths $paths) {
        $request->validate(['confirmation'=>'accepted']);
        [$participant,$path]=$this->context($request,$paths); abort_unless($path,404);
        $paths->completeMaterial($path,$participant,$chapter);
        return redirect()->route('learning.index')->with('status','Materi '.$chapter.' selesai. Tahap berikutnya terbuka.');
    }
}
