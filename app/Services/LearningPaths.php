<?php
namespace App\Services;
use App\Models\{Assessment,BankBook,BankChapter,BankPage,Classroom,LearningPath,LearningStageCompletion,Participant,StudentTask,StudentSubmission,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningPaths {
    private function fail(string $message): never { throw ValidationException::withMessages(['path'=>$message]); }

    public function create(Classroom $classroom,User $teacher,int $bookId,int $preId,int $postId): LearningPath {
        abort_unless($teacher->role==='teacher' && $teacher->is_active && $classroom->owner_id===$teacher->id,403);
        if($classroom->grade!==1) $this->fail('Jalur buku ini hanya untuk kelas 1.');
        if($preId===$postId) $this->fail('Pretest dan post-test harus memakai asesmen berbeda.');
        return DB::transaction(function() use($classroom,$teacher,$bookId,$preId,$postId) {
            $c=Classroom::lockForUpdate()->findOrFail($classroom->id);
            if($c->grade!==1 || $c->owner_id!==$teacher->id) $this->fail('Kelas berubah. Muat ulang.');
            if(LearningPath::where('classroom_id',$c->id)->exists()) $this->fail('Jalur kelas sudah disiapkan.');
            $book=BankBook::findOrFail($bookId);
            if($book->grade!==1 || $book->chapters()->whereIn('number',range(1,8))->count()!==8) $this->fail('Pilih buku kelas 1 yang memuat Materi 1–8.');
            foreach([$preId,$postId] as $id) {
                $a=Assessment::with('questions')->findOrFail($id);
                if($a->owner_id!==$teacher->id || $a->classroom_id!==$c->id || $a->questions->isEmpty() || $a->packageIssues()) $this->fail('Pretest dan post-test harus paket lengkap pada kelas ini.');
                if(StudentSubmission::whereIn('student_task_id',StudentTask::where('assessment_id',$id)->select('id'))->exists()) $this->fail('Gunakan paket tes baru yang belum memiliki pengiriman siswa.');
            }
            return LearningPath::create(['classroom_id'=>$c->id,'bank_book_id'=>$bookId,'pre_assessment_id'=>$preId,'post_assessment_id'=>$postId,'created_by'=>$teacher->id]);
        });
    }

    public function forParticipant(Participant $participant): ?LearningPath {
        return LearningPath::with(['book','classroom'])->where('classroom_id',$participant->classroom_id)->first();
    }
    public function current(LearningPath $path,Participant $participant): int {
        $done=LearningStageCompletion::where('learning_path_id',$path->id)->where('participant_id',$participant->id)->pluck('stage')->map(fn($n)=>(int)$n)->all();
        for($stage=0;$stage<10;$stage++) if(!in_array($stage,$done,true)) return $stage;
        return 10;
    }
    public function chapter(LearningPath $path,int $number): BankChapter {
        abort_unless($number>=1 && $number<=8,404);
        return $path->book->chapters()->where('number',$number)->firstOrFail();
    }
    public function pages(BankChapter $chapter) { return $chapter->book->pages()->whereBetween('number',[$chapter->start_page,$chapter->end_page])->orderBy('number')->get(); }
    public function visited(LearningPath $path,Participant $participant,BankChapter $chapter): array {
        return DB::table('learning_page_visits')->where('learning_path_id',$path->id)->where('participant_id',$participant->id)
            ->whereIn('bank_page_id',$this->pages($chapter)->pluck('id'))->pluck('bank_page_id')->map(fn($n)=>(int)$n)->all();
    }
    public function taskAllowed(Participant $participant,StudentTask $task): bool {
        $path=$this->forParticipant($participant); if(!$path) return $participant->classroom->grade!==1;
        $stage=$this->current($path,$participant);
        if($stage===10) return true;
        return ($stage===0 && $task->assessment_id===$path->pre_assessment_id) || ($stage===9 && $task->assessment_id===$path->post_assessment_id);
    }
    public function taskForStage(LearningPath $path,Participant $participant,int $stage): ?StudentTask {
        $id=$stage===0?$path->pre_assessment_id:($stage===9?$path->post_assessment_id:null);
        if(!$id) return null;
        $task=app(StudentTasks::class)->head($id);
        return $task && app(StudentTasks::class)->visible($task,$participant)?$task:null;
    }
    public function recordTaskCompletion(StudentTask $task,Participant $participant): void {
        $path=$this->forParticipant($participant); if(!$path) return;
        $stage=$this->current($path,$participant);
        if(!(($stage===0 && $task->assessment_id===$path->pre_assessment_id) || ($stage===9 && $task->assessment_id===$path->post_assessment_id))) return;
        $ids=collect($task->snapshot['questions'] ?? [])->pluck('id')->map(fn($n)=>(int)$n)->unique()->all();
        if(!$ids) return;
        $sent=StudentSubmission::where('student_task_id',$task->id)->where('participant_id',$participant->id)->whereIn('question_id',$ids)->distinct()->count('question_id');
        if($sent!==count($ids)) return;
        LearningStageCompletion::firstOrCreate(['learning_path_id'=>$path->id,'participant_id'=>$participant->id,'stage'=>$stage],['student_task_id'=>$task->id,'created_at'=>now()]);
    }
    public function markPage(LearningPath $path,Participant $participant,BankPage $page): void {
        DB::transaction(function() use($path,$participant,$page) {
            Participant::lockForUpdate()->findOrFail($participant->id);
            $stage=$this->current($path,$participant);
            $chapter=$this->chapter($path,$stage);
            if($page->bank_book_id!==$path->bank_book_id || $page->number<$chapter->start_page || $page->number>$chapter->end_page) $this->fail('Selesaikan tahap yang sedang terbuka terlebih dahulu.');
            app(QuestionBank::class)->validPage($page);
            DB::table('learning_page_visits')->insertOrIgnore(['learning_path_id'=>$path->id,'participant_id'=>$participant->id,'bank_page_id'=>$page->id,'created_at'=>now()]);
        });
    }
    public function completeMaterial(LearningPath $path,Participant $participant,int $number): void {
        DB::transaction(function() use($path,$participant,$number) {
            Participant::lockForUpdate()->findOrFail($participant->id);
            if($this->current($path,$participant)!==$number) $this->fail('Materi sebelumnya harus diselesaikan terlebih dahulu.');
            $chapter=$this->chapter($path,$number); $pages=$this->pages($chapter);
            if($pages->isEmpty() || count($this->visited($path,$participant,$chapter))!==$pages->count()) $this->fail('Buka dan tandai seluruh halaman materi ini sebelum melanjutkan.');
            LearningStageCompletion::create(['learning_path_id'=>$path->id,'participant_id'=>$participant->id,'stage'=>$number,'created_at'=>now()]);
        });
    }
}
