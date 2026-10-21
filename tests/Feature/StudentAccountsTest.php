<?php
namespace Tests\Feature;
use App\Models\{StudentProfile, User};
use App\Services\{AnswerIntake, TranscriptionWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class StudentAccountsTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role): User {
        $u=new User(['name'=>'Test '.$role,'email'=>uniqid().'@example.test','password'=>'TestPassword123']); $u->role=$role; $u->is_active=true; $u->save(); return $u;
    }
    private function fixture(): array {
        $admin=$this->user('admin'); $teacher=$this->user('teacher');
        $c=$teacher->classrooms()->create(['name'=>'Class 4','grade'=>4,'school_year'=>'2026']);
        $p=$c->participants()->create(['code'=>'S001']); $other=$c->participants()->create(['code'=>'S002']);
        $a=$teacher->assessments()->make(['title'=>'Private assessment']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'Question for own answer','answer_key'=>'SECRET_KEY_123','scoring_guidelines'=>'SECRET_RULE_123','max_score'=>'2.00']);
        return [$admin,$teacher,$c,$p,$other,$a,$q];
    }
    private function body($p,string $email='student@example.test'): array { return ['name'=>'Student','email'=>$email,'password'=>'StudentTest123','password_confirmation'=>'StudentTest123','role'=>'student','participant_id'=>$p->id]; }
    private function create($admin,$p): User {
        $this->actingAs($admin)->post('/admin/users',$this->body($p))->assertSessionDoesntHaveErrors(); return User::where('email','student@example.test')->firstOrFail();
    }
    public function test_admin_creates_student_with_unique_locked_link_and_student_can_login(): void {
        [$admin,$teacher,$c,$p]=$this->fixture(); $student=$this->create($admin,$p);
        $this->assertSame('student',$student->role); $this->assertTrue(Hash::check('StudentTest123',$student->password));
        $this->assertSame($p->id,$student->studentProfile->participant_id);
        $this->get('/admin/users')->assertOk()->assertSee('Siswa');
        $this->post('/logout');
        $this->post('/login',['email'=>$student->email,'password'=>'StudentTest123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Buka ruang siswa')->assertDontSee('Kelola akun')->assertDontSee('Kelola kelas');
        $this->get('/student')->assertOk()->assertSee('S001')->assertSee('Class 4')->assertSee('Belum ada jawaban')->assertDontSee('S002');
        $this->post('/logout')->assertRedirect('/login'); $this->get('/student')->assertRedirect('/login');
        try { $student->studentProfile->update(['participant_id'=>999]); $this->fail('Mutable student link'); } catch(\LogicException) { $this->assertTrue(true); }
    }
    public function test_only_own_answers_are_visible_without_keys_rubrics_or_foreign_answers(): void {
        [$admin,$teacher,$c,$p,$other,$a,$q]=$this->fixture(); $student=$this->create($admin,$p); Http::fake();
        $intake=app(AnswerIntake::class);
        $intake->create($a,$teacher->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'OWN_ANSWER_123']);
        $foreign=$intake->create($a,$teacher->id,$other->id,$q->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'FOREIGN_ANSWER_123']);
        $this->actingAs($student)->get('/student')->assertOk()->assertSee('OWN_ANSWER_123')->assertSee('Question for own answer')->assertDontSee('FOREIGN_ANSWER_123')->assertDontSee('SECRET_KEY_123')->assertDontSee('SECRET_RULE_123')->assertDontSee('Private assessment');
        $this->get('/student/answers/'.$foreign->id.'/revisions/'.$foreign->latestRevision->id.'/photo')->assertNotFound();
        foreach(['/classrooms','/assessments','/admin/users','/admin/ai','/assessments/'.$a->id,'/assessments/'.$a->id.'/answers','/assessments/'.$a->id.'/grading-runs'] as $path) $this->get($path)->assertForbidden();
        $this->post('/assessments',['title'=>'Hacked'])->assertForbidden(); $this->post('/admin/users',[])->assertForbidden();
        $this->patch('/assessments/'.$a->id,['title'=>'Hacked'])->assertForbidden(); Http::assertNothingSent();
    }
    public function test_private_photo_and_confirmed_transcription_are_scoped_to_student(): void {
        Storage::fake('answers'); [$admin,$teacher,$c,$p,$other,$a,$q]=$this->fixture(); $student=$this->create($admin,$p);
        $file=UploadedFile::fake()->createWithContent('answer.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='));
        $answer=app(AnswerIntake::class)->create($a,$teacher->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'photo','photo'=>$file]); $photo=$answer->latestRevision;
        $url='/student/answers/'.$answer->id.'/revisions/'.$photo->id.'/photo';
        $tw=app(TranscriptionWorkflow::class); $tw->save($a,$teacher->id,$answer->id,$photo->id,(string)Str::uuid(),0,'draft','PRIVATE_DRAFT','Copy');
        $this->actingAs($student)->get('/student')->assertOk()->assertDontSee('PRIVATE_DRAFT')->assertDontSee($photo->photo_path);
        $this->get($url)->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('Cache-Control','no-store, private');
        $tw->save($a,$teacher->id,$answer->id,$photo->id,(string)Str::uuid(),1,'confirm','PRIVATE_DRAFT','Review',[],true);
        $this->get('/student')->assertSee('PRIVATE_DRAFT')->assertSee('Transkripsi telah diperiksa');
        $this->actingAs($teacher)->get($url)->assertForbidden(); $this->actingAs($admin)->get($url)->assertForbidden();
        $another=$this->user('student'); $another->studentProfile()->create(['participant_id'=>$other->id,'created_by'=>$admin->id]);
        $this->actingAs($another)->get($url)->assertNotFound();
        $this->actingAs($student); app(AnswerIntake::class)->revise($a,$teacher->id,$answer->id,1,'Correction',['content_type'=>'text','answer_text'=>'New text']);
        $this->get($url)->assertNotFound(); $this->get('/student')->assertSee('New text')->assertDontSee('PRIVATE_DRAFT');
    }
    public function test_invalid_or_duplicate_enrollment_rolls_back_account_and_role_escalation_is_denied(): void {
        [$admin,$teacher,$c,$p]=$this->fixture();
        $body=$this->body($p); unset($body['participant_id']); $this->actingAs($admin)->post('/admin/users',$body)->assertSessionHasErrors('participant_id');
        $body=$this->body($p); $body['participant_id']=999; $this->post('/admin/users',$body)->assertSessionHasErrors('participant_id');
        $teacher->is_active=false; $teacher->save(); $this->post('/admin/users',$this->body($p))->assertSessionHasErrors('participant_id');
        $this->assertSame(2,User::count()); $teacher->is_active=true; $teacher->save(); $student=$this->create($admin,$p);
        $this->post('/admin/users',$this->body($p,'duplicate@example.test'))->assertSessionHasErrors('participant_id'); $this->assertSame(3,User::count());
        $bad=$this->body($p,'teacher-new@example.test'); $bad['role']='teacher'; $this->post('/admin/users',$bad)->assertSessionHasErrors('participant_id');
        $this->actingAs($student)->post('/admin/users',$this->body($p,'admin-hacked@example.test')+['role'=>'admin'])->assertForbidden();
        $this->assertSame(1,StudentProfile::count());
    }
    public function test_disabled_student_session_loses_access_and_wrong_roles_cannot_use_portal(): void {
        [$admin,$teacher,$c,$p]=$this->fixture(); $student=$this->create($admin,$p);
        $this->actingAs($teacher)->get('/student')->assertForbidden(); $this->actingAs($admin)->get('/student')->assertForbidden();
        $this->actingAs($student); $student->is_active=false; $student->save(); $this->get('/student')->assertRedirect('/login');
        $this->post('/login',['email'=>$student->email,'password'=>'StudentTest123'])->assertSessionHasErrors('email');
    }
}
