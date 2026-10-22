<?php
namespace Tests\Feature;
use App\Models\{BankBook,BankPage,LearningStageCompletion,StudentProfile,User};
use App\Services\{LearningPaths,QuestionBank,StudentTasks};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningPathTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role): User { $u=new User(['name'=>'Test '.$role,'email'=>uniqid().'@example.test','password'=>'TestPassword123']); $u->role=$role; $u->is_active=true; $u->save(); return $u; }
    private function fixture(): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('local'); Http::preventStrayRequests();
        $teacher=$this->user('teacher'); $student=$this->user('student');
        $class=$teacher->classrooms()->create(['name'=>'Kelas I','grade'=>1,'school_year'=>'2026']);
        $participant=$class->participants()->create(['code'=>'P001']); $student->studentProfile()->create(['participant_id'=>$participant->id,'created_by'=>$teacher->id]);
        $book=BankBook::create(['code'=>'path-test','title'=>'Matematika Kelas I','authors'=>'Penulis','edition'=>'2022','isbn'=>'test','grade'=>1,'source_hash'=>str_repeat('a',64),'manifest_hash'=>str_repeat('b',64),'created_at'=>now()]);
        for($n=1;$n<=8;$n++) {
            $book->chapters()->create(['number'=>$n,'title'=>'Materi '.$n,'start_page'=>$n,'end_page'=>$n,'objectives'=>'Tujuan materi '.$n]);
            $bytes='SYNTHETIC_PAGE_'.$n; Storage::disk('local')->put('bank-books/path-test/pages/'.$n.'.webp',$bytes);
            $body=['number'=>$n,'pdf_page'=>$n+12,'source_text'=>'Konten materi '.$n,'image_hash'=>hash('sha256',$bytes)];
            $book->pages()->create($body+['content_hash'=>QuestionBank::hash($body)]);
        }
        $tests=[];
        foreach(['Pretest','Post-test','Tugas luar jalur'] as $title) {
            $a=$teacher->assessments()->make(['title'=>$title]); $a->classroom()->associate($class); $a->save();
            $q=$a->questions()->create(['number'=>1,'prompt'=>'Soal '.$title,'answer_key'=>'Jawaban','scoring_guidelines'=>'Pedoman','max_score'=>'2.00']);
            $tests[]=[$a,$q];
        }
        return compact('teacher','student','class','participant','book','tests');
    }
    private function open($assessment,$teacher) { return app(StudentTasks::class)->save($assessment,$teacher->id,(string)Str::uuid(),0,'open',now()->addDay()->toIso8601String(),true); }
    private function send($task,$question): void {
        $this->post('/student/tasks/'.$task->id.'/questions/'.$question->id,['creation_key'=>(string)Str::uuid(),'expected_version'=>0,'content_type'=>'text','answer_text'=>'Jawaban saya','confirmation'=>1])->assertSessionDoesntHaveErrors();
    }
    public function test_all_ten_stages_unlock_in_order_and_progress_is_per_student(): void {
        $f=$this->fixture(); $teacher=$f['teacher']; $student=$f['student']; $class=$f['class']; $book=$f['book']; [$pre,$preQuestion]=$f['tests'][0]; [$post,$postQuestion]=$f['tests'][1]; [$other]=$f['tests'][2];
        $secondQuestion=$pre->questions()->create(['number'=>2,'prompt'=>'Soal pretest kedua','answer_key'=>'Jawaban','scoring_guidelines'=>'Pedoman','max_score'=>'2.00']);
        $this->actingAs($teacher)->post('/classrooms/'.$class->id.'/learning-path',['bank_book_id'=>$book->id,'pre_assessment_id'=>$pre->id,'post_assessment_id'=>$post->id])->assertSessionDoesntHaveErrors();
        $path=\App\Models\LearningPath::firstOrFail(); $preTask=$this->open($pre,$teacher); $postTask=$this->open($post,$teacher); $otherTask=$this->open($other,$teacher);
        $this->actingAs($student)->get('/student/path')->assertOk()->assertSee('Pretest')->assertSee('TERKUNCI');
        $this->get('/student/path/materials/1')->assertNotFound(); $this->get('/student/tasks/'.$postTask->id)->assertNotFound(); $this->get('/student/tasks/'.$otherTask->id)->assertNotFound();
        $this->postJson('/student/tasks/'.$postTask->id.'/questions/'.$postQuestion->id,['creation_key'=>(string)Str::uuid(),'expected_version'=>0,'content_type'=>'text','answer_text'=>'Bypass','confirmation'=>1])->assertUnprocessable();
        $this->send($preTask,$preQuestion); $this->assertSame(0,app(LearningPaths::class)->current($path,$f['participant']));
        $this->send($preTask,$secondQuestion); $this->assertSame(1,app(LearningPaths::class)->current($path,$f['participant']));
        $this->get('/student/tasks/'.$preTask->id)->assertNotFound(); $this->get('/student/path/materials/2')->assertNotFound();
        for($n=1;$n<=8;$n++) {
            $url='/student/path/materials/'.$n; $this->get($url)->assertOk()->assertSee('0 / 1 halaman selesai');
            $this->post($url.'/complete',['confirmation'=>1])->assertSessionHasErrors('path');
            $this->get($url.'/pages/'.$n)->assertOk()->assertSee('Halaman '.$n);
            $this->get($url.'/pages/'.$n.'/image')->assertOk()->assertHeader('Content-Type','image/webp')->assertHeader('Cache-Control','no-store, private');
            $this->post($url.'/pages/'.$n.'/read')->assertRedirect(); $this->post($url.'/pages/'.$n.'/read')->assertRedirect();
            $this->post($url.'/complete',['confirmation'=>1])->assertRedirect('/student/path');
            $this->assertSame($n+1,app(LearningPaths::class)->current($path,$f['participant']));
        }
        $this->get('/student/tasks/'.$postTask->id)->assertOk()->assertSee('Soal Post-test'); $this->send($postTask,$postQuestion);
        $this->assertSame(10,app(LearningPaths::class)->current($path,$f['participant'])); $this->assertSame(10,LearningStageCompletion::count());
        $this->get('/student/path')->assertOk()->assertSee('Semua 10 tahap selesai');
        $second=$this->user('student'); $p2=$class->participants()->create(['code'=>'P002']); $second->studentProfile()->create(['participant_id'=>$p2->id,'created_by'=>$teacher->id]);
        $this->actingAs($second)->get('/student/path/materials/1')->assertNotFound(); $this->assertSame(0,app(LearningPaths::class)->current($path,$p2));
        $this->actingAs($teacher)->get('/student/path')->assertForbidden(); $this->get('/student/path/materials/1/pages/1/image')->assertForbidden(); Http::assertNothingSent();
    }
    public function test_path_setup_rejects_invalid_or_reused_assessments_and_grade_change(): void {
        $f=$this->fixture(); $teacher=$f['teacher']; $class=$f['class']; $book=$f['book']; [$pre]=$f['tests'][0]; [$post]=$f['tests'][1];
        $preTask=$this->open($pre,$teacher);
        $this->actingAs($f['student'])->get('/student/tasks/'.$preTask->id)->assertNotFound();
        $url='/classrooms/'.$class->id.'/learning-path'; $this->actingAs($teacher)->post($url,['bank_book_id'=>$book->id,'pre_assessment_id'=>$pre->id,'post_assessment_id'=>$pre->id])->assertSessionHasErrors('path');
        $this->post($url,['bank_book_id'=>$book->id,'pre_assessment_id'=>$pre->id,'post_assessment_id'=>$post->id])->assertSessionDoesntHaveErrors();
        $this->get($url)->assertOk()->assertSee('Kemajuan peserta')->assertSee('Atur tugas pretest');
        $this->post($url,['bank_book_id'=>$book->id,'pre_assessment_id'=>$pre->id,'post_assessment_id'=>$post->id])->assertSessionHasErrors('path');
        $this->patch('/classrooms/'.$class->id,['name'=>'Kelas I','grade'=>4,'school_year'=>'2026'])->assertSessionHasErrors('grade');
        $this->actingAs($f['student'])->post($url,['bank_book_id'=>$book->id,'pre_assessment_id'=>$pre->id,'post_assessment_id'=>$post->id])->assertForbidden();
    }
}
