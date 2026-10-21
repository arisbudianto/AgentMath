<?php
namespace Tests\Feature;
use App\Models\{StudentProfile, User};
use App\Services\{AnswerIntake, TranscriptionWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class StudentTasksTest extends TestCase {
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
    private function setupTask(): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        $f=$this->fixture(); $s=$this->create($f[0],$f[3]);
        $t=app(\App\Services\StudentTasks::class)->save($f[5],$f[1]->id,(string)Str::uuid(),0,'open',now()->addDay()->toIso8601String(),true);
        $this->actingAs($s); return [$f,$s,$t];
    }
    private function payload(int $expected=0,string $text='My own work'): array { return ['creation_key'=>(string)Str::uuid(),'expected_version'=>$expected,'content_type'=>'text','answer_text'=>$text,'confirmation'=>1]; }
    private function submitUrl($task,$q): string { return '/student/tasks/'.$task->id.'/questions/'.$q->id; }
    public function test_published_task_shows_only_public_fields_and_submits_for_linked_participant(): void {
        [$f,$s,$t]=$this->setupTask(); $url='/student/tasks/'.$t->id;
        $this->get('/student/tasks')->assertOk()->assertSee('Private assessment');
        $this->get($url)->assertOk()->assertSee('Question for own answer')->assertDontSee('SECRET_KEY_123')->assertDontSee('SECRET_RULE_123')->assertDontSee('source_hash');
        $v=$this->payload(); $v['participant_id']=$f[4]->id; $this->post($this->submitUrl($t,$f[6]),$v)->assertSessionDoesntHaveErrors()->assertRedirect('/student');
        $answer=$f[5]->answers()->first(); $this->assertSame($f[3]->id,$answer->participant_id); $this->assertSame($s->id,$answer->created_by); $this->assertSame('student',$answer->latestRevision->origin);
        $this->assertSame(1,\App\Models\StudentSubmission::count()); $this->get('/student')->assertSee('My own work'); Http::assertNothingSent();
    }
    public function test_receipt_replay_closed_task_and_changed_payload_are_handled_without_duplicates(): void {
        [$f,$s,$t]=$this->setupTask(); $url=$this->submitUrl($t,$f[6]); $v=$this->payload();
        $this->post($url,$v)->assertSessionDoesntHaveErrors();
        app(\App\Services\StudentTasks::class)->save($f[5],$f[1]->id,(string)Str::uuid(),1,'close',null,true);
        $this->post($url,$v)->assertSessionDoesntHaveErrors(); $v['answer_text']='Changed'; $this->postJson($url,$v)->assertUnprocessable();
        $this->get('/student/tasks/'.$t->id)->assertNotFound(); $this->assertSame(1,\App\Models\StudentSubmission::count()); $this->assertSame(1,$f[5]->answers()->first()->current_version);
    }
    public function test_three_submissions_keep_distinct_revisions_and_reject_stale_tab_or_fourth_request(): void {
        [$f,$s,$t]=$this->setupTask(); $url=$this->submitUrl($t,$f[6]);
        for($i=0;$i<3;$i++) $this->post($url,$this->payload($i,'Work version '.$i))->assertSessionDoesntHaveErrors();
        $this->postJson($url,$this->payload(3,'Fourth'))->assertUnprocessable();
        $revisions=\App\Models\StudentSubmission::orderBy('id')->pluck('answer_revision_id'); $this->assertCount(3,$revisions->unique());
        $this->assertSame([1,2,3],\App\Models\AnswerRevision::whereIn('id',$revisions)->orderBy('version')->pluck('version')->all());
        $this->assertSame(3,$f[5]->answers()->first()->current_version);
    }
    public function test_expiry_source_changes_and_foreign_student_prevent_access_and_submission(): void {
        [$f,$s,$t]=$this->setupTask(); $url=$this->submitUrl($t,$f[6]);
        $other=$this->user('student'); $c=$f[1]->classrooms()->create(['name'=>'Other','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'OTHER']);
        $other->studentProfile()->create(['participant_id'=>$p->id,'created_by'=>$f[0]->id]); $this->actingAs($other)->get('/student/tasks/'.$t->id)->assertNotFound(); $this->postJson($url,$this->payload())->assertUnprocessable();
        $this->actingAs($s); $f[6]->update(['prompt'=>'Changed source']); $this->get('/student/tasks/'.$t->id)->assertNotFound(); $this->postJson($url,$this->payload())->assertUnprocessable();
        $t=app(\App\Services\StudentTasks::class)->save($f[5],$f[1]->id,(string)Str::uuid(),1,'open',now()->addMinute()->toIso8601String(),true);
        $this->travel(61)->seconds(); $this->get('/student/tasks/'.$t->id)->assertNotFound(); $this->postJson($this->submitUrl($t,$f[6]),$this->payload())->assertUnprocessable(); $this->assertSame(0,\App\Models\StudentSubmission::count());
    }
    public function test_student_cannot_overwrite_teacher_answer_or_teacher_revision(): void {
        [$f,$s,$t]=$this->setupTask(); $a=app(AnswerIntake::class)->create($f[5],$f[1]->id,$f[3]->id,$f[6]->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'Teacher recorded']);
        $this->postJson($this->submitUrl($t,$f[6]),$this->payload(1))->assertUnprocessable(); $this->assertSame('Teacher recorded',$a->latestRevision->answer_text);
        $q=$f[5]->questions()->create(['number'=>2,'prompt'=>'Second','answer_key'=>'secret','scoring_guidelines'=>'secret','max_score'=>1]);
        $t=app(\App\Services\StudentTasks::class)->save($f[5],$f[1]->id,(string)Str::uuid(),1,'open',now()->addDay()->toIso8601String(),true);
        $this->post($this->submitUrl($t,$q),$this->payload())->assertSessionDoesntHaveErrors(); $mine=$f[5]->answers()->where('question_id',$q->id)->first();
        app(AnswerIntake::class)->revise($f[5],$f[1]->id,$mine->id,1,'Teacher correction',['content_type'=>'text','answer_text'=>'Teacher update']);
        $this->postJson($this->submitUrl($t,$q),$this->payload(2,'Try overwrite'))->assertUnprocessable(); $this->assertSame(2,$mine->fresh()->current_version);
    }
    public function test_photo_submission_is_private_unconfirmed_and_invalid_photo_or_confirmation_is_rejected(): void {
        Storage::fake('answers'); [$f,$s,$t]=$this->setupTask(); $url=$this->submitUrl($t,$f[6]);
        $v=$this->payload(); unset($v['confirmation']); $this->postJson($url,$v)->assertUnprocessable();
        $v=$this->payload(); $v['content_type']='photo'; $v['photo']=UploadedFile::fake()->createWithContent('bad.png','not a png'); $this->postJson($url,$v)->assertUnprocessable();
        $v['photo']=UploadedFile::fake()->createWithContent('own.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='));
        $this->post($url,$v)->assertSessionDoesntHaveErrors(); $a=$f[5]->answers()->first(); $r=$a->latestRevision; Storage::disk('answers')->assertExists($r->photo_path);
        $this->assertNull(app(TranscriptionWorkflow::class)->readyText($a)); $this->assertSame('student',$r->origin); $this->get('/student/answers/'.$a->id.'/revisions/'.$r->id.'/photo')->assertOk(); Http::assertNothingSent();
    }
    public function test_teacher_controls_require_owner_confirmation_and_expected_version(): void {
        [$f,$s,$t]=$this->setupTask(); $url='/assessments/'.$f[5]->id.'/student-tasks';
        $this->actingAs($f[1])->get($url)->assertOk()->assertSee('Pratinjau yang akan dibuka');
        $v=['creation_key'=>(string)Str::uuid(),'expected_version'=>1,'action'=>'close']; $this->postJson($url,$v)->assertUnprocessable(); $v['confirmation']=1; $this->post($url,$v)->assertSessionDoesntHaveErrors();
        $v['creation_key']=(string)Str::uuid(); $this->postJson($url,$v)->assertUnprocessable();
        foreach([$f[0],$s,$this->user('teacher')] as $u) { $this->actingAs($u)->get($url)->assertForbidden(); $this->post($url,$v)->assertForbidden(); }
        $this->actingAs($f[1])->get('/student/tasks')->assertForbidden();
    }
    public function test_receipt_storage_failure_rolls_back_answer_and_removes_new_private_photo(): void {
        Storage::fake('answers'); [$f,$s,$t]=$this->setupTask();
        \Illuminate\Support\Facades\Event::listen('eloquent.creating: App\\Models\\StudentSubmission',fn()=>throw new \RuntimeException('Synthetic receipt failure'));
        $photo=UploadedFile::fake()->createWithContent('own.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='));
        try { app(\App\Services\StudentTasks::class)->submit($s,$t->id,$f[6]->id,(string)Str::uuid(),0,['content_type'=>'photo','photo'=>$photo],true); $this->fail('Expected receipt failure'); }
        catch(\RuntimeException) { $this->assertSame(0,$f[5]->answers()->count()); $this->assertCount(0,Storage::disk('answers')->allFiles()); }
        finally { \Illuminate\Support\Facades\Event::forget('eloquent.creating: App\\Models\\StudentSubmission'); }
    }
}
