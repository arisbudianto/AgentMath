<?php
namespace Tests\Feature;
use App\Models\{GradingRun, User};
use App\Services\{AnswerIntake, GradingOrchestrator, GradingStages, RubricWorkflow, TranscriptionWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Preserve M04 orchestration contract tests independently of paid M05 agents.
class PreflightOnlyStages extends GradingStages {
    public function supports(string $stage): bool { return $stage==='preflight'; }
}
class GradingOrchestrationTest extends TestCase {
    use RefreshDatabase;
    private function fixture(bool $photo=false): array {
        $this->app->instance(GradingStages::class,new PreflightOnlyStages());
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        $u=new User(['name'=>'Teacher','email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role='teacher'; $u->is_active=true; $u->save();
        $c=$u->classrooms()->create(['name'=>'Class','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'P001']);
        $a=$u->assessments()->make(['title'=>'Math']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1 - 1 = ?','answer_key'=>'0','scoring_guidelines'=>'Two points for zero.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $rw=app(RubricWorkflow::class); $r=$rw->create($q,$u->id,(string)Str::uuid(),'manual'); $r=$rw->save($q,$r->id,$r->revision,[],true,$u->id);
        $payload=['content_type'=>'text','answer_text'=>'0'];
        if($photo) { Storage::fake('answers'); $payload=['content_type'=>'photo','photo'=>UploadedFile::fake()->createWithContent('answer.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='))]; }
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),$payload);
        $this->actingAs($u); return [$u,$a,$answer,$q,$r];
    }
    private function body($answer): array { return ['answer_id'=>$answer->id,'creation_key'=>(string)Str::uuid(),'confirmation'=>1]; }
    private function start($u,$a,$answer): GradingRun { return app(GradingOrchestrator::class)->start($a,$u->id,$answer->id,(string)Str::uuid()); }
    private function step(GradingRun $run): void { $run->refresh(); app(GradingOrchestrator::class)->process($run->id,$run->generation); $run->refresh(); }
    private function fails(callable $fn): void { try { $fn(); $this->fail('Expected validation failure'); } catch(ValidationException) { $this->assertTrue(true); } }

    public function test_database_queue_preflight_snapshot_replay_and_no_fake_grade(): void {
        [$u,$a,$answer]=$this->fixture(); config(['queue.default'=>'sync','ai.api_key'=>'NEVER_EXPOSE']);
        $url='/assessments/'.$a->id.'/grading-runs'; $body=$this->body($answer);
        $this->post($url,$body)->assertSessionDoesntHaveErrors(); $this->post($url,$body)->assertSessionDoesntHaveErrors();
        $run=GradingRun::firstOrFail(); $this->assertSame('queued',$run->status); $this->assertSame(1,DB::table('agentmath_jobs')->count());
        $this->assertSame('0',$run->input_snapshot['answer_text']);
        $payload=DB::table('agentmath_jobs')->value('payload'); $this->assertStringNotContainsString('Two points',$payload); $this->assertStringNotContainsString('NEVER_EXPOSE',$payload);
        $this->assertStringNotContainsString('NEVER_EXPOSE',json_encode($run->toArray()));
        $this->post($url,$this->body($answer))->assertSessionHasErrors('grading');
        $this->artisan('agentmath:work')->assertSuccessful();
        $run->refresh(); $this->assertSame('awaiting_agent',$run->status); $this->assertSame('marker_a',$run->stage);
        $this->assertSame(0,DB::table('agentmath_jobs')->count()); $this->assertSame(1,$run->attempts()->count());
        $this->assertSame(true,$run->attempts()->first()->output['validated']);
        $this->get($url)->assertOk()->assertSee('Menunggu agent penilaian');
        $this->get($url.'/'.$run->id)->assertOk()->assertSee('Persiapan berhasil')->assertSee('succeeded')->assertDontSee('NEVER_EXPOSE');
        $this->post($url.'/'.$run->id.'/control',['action'=>'resume'])->assertSessionHasErrors('grading');
        $this->post($url.'/'.$run->id.'/control',['action'=>'cancel'])->assertSessionDoesntHaveErrors();
        $this->assertSame('cancelled',$run->fresh()->status); Http::assertNothingSent();
    }
    public function test_ready_gate_requires_approved_current_rubric_and_confirmed_photo(): void {
        [$u,$a,$answer,$q,$r]=$this->fixture(true);
        $workflow=app(GradingOrchestrator::class); $this->fails(fn()=>$this->start($u,$a,$answer));
        $photo=$answer->latestRevision; $tw=app(TranscriptionWorkflow::class);
        $tw->save($a,$u->id,$answer->id,$photo->id,(string)Str::uuid(),0,'draft','0','Copy');
        $this->fails(fn()=>$this->start($u,$a,$answer));
        $tw->save($a,$u->id,$answer->id,$photo->id,(string)Str::uuid(),1,'confirm','0','Review',[],true);
        $run=$this->start($u,$a,$answer); $this->assertNotNull($run->answer_transcription_id);
        $tw->save($a,$u->id,$answer->id,$photo->id,(string)Str::uuid(),2,'draft','Corrected','Correction');
        $this->step($run); $this->assertSame('stale',$run->status); $this->assertSame(0,$run->attempts()->count());
        $this->assertFalse($workflow->isCurrent($run));
    }
    public function test_missing_rubric_changed_question_and_replaced_answer_are_rejected(): void {
        [$u,$a,$answer,$q,$r]=$this->fixture();
        DB::table('rubric_versions')->where('id',$r->id)->update(['status'=>'draft']);
        $this->fails(fn()=>$this->start($u,$a,$answer));
        DB::table('rubric_versions')->where('id',$r->id)->update(['status'=>'approved']);
        $run=$this->start($u,$a,$answer); $q->update(['prompt'=>'Changed']); $this->step($run); $this->assertSame('stale',$run->status);
        $q->update(['prompt'=>'1 - 1 = ?']); $run=$this->start($u,$a,$answer);
        app(AnswerIntake::class)->revise($a,$u->id,$answer->id,1,'Correction',['content_type'=>'text','answer_text'=>'Zero']);
        $this->step($run); $this->assertSame('stale',$run->status); $this->assertSame('0',$run->input_snapshot['answer_text']);
    }
    public function test_failure_retry_limit_and_completed_checkpoint_are_preserved(): void {
        [$u,$a,$answer]=$this->fixture();
        $adapter=new class extends PreflightOnlyStages { public bool $fail=true; public function execute(string $stage,array $input,array $previous,array $configuration): array { if($this->fail) throw new \RuntimeException('SECRET_PROVIDER_ERROR'); return parent::execute($stage,$input,$previous,$configuration); } };
        $this->app->instance(GradingStages::class,$adapter); $workflow=app(GradingOrchestrator::class); $run=$this->start($u,$a,$answer);
        $this->step($run); $this->assertSame('failed',$run->status); $this->assertNull($run->attempts()->first()->output);
        $this->get('/assessments/'.$a->id.'/grading-runs/'.$run->id)->assertDontSee('SECRET_PROVIDER_ERROR')->assertSee('stage_failed');
        $adapter->fail=false; $workflow->control($a,$u->id,$run->id,'resume'); $this->step($run);
        $this->assertSame('queued',$run->status); $this->assertSame('marker_a',$run->stage); $this->step($run);
        $this->assertSame('awaiting_agent',$run->status); $this->assertSame(2,$run->attempts()->count());
        $attempt=$run->attempts()->where('status','succeeded')->first();
        try { $attempt->update(['output'=>['changed'=>true]]); $this->fail('Mutable output'); } catch(\LogicException) { $this->assertTrue(true); }
        $workflow->control($a,$u->id,$run->id,'cancel'); $adapter->fail=true; $run=$this->start($u,$a,$answer);
        for($i=0;$i<3;$i++) { $this->step($run); if($i<2) $workflow->control($a,$u->id,$run->id,'resume'); }
        $this->fails(fn()=>$workflow->control($a,$u->id,$run->id,'resume')); $this->assertSame(3,$run->attempts()->count());
    }
    public function test_validation_duplicate_delivery_and_cancellation_during_execution(): void {
        [$u,$a,$answer]=$this->fixture();
        $adapter=new class extends PreflightOnlyStages { public function execute(string $stage,array $input,array $previous,array $configuration): array { return ['validated'=>false]; } };
        $this->app->instance(GradingStages::class,$adapter); $run=$this->start($u,$a,$answer); $old=$run->generation; $this->step($run);
        $this->assertSame('invalid_output',$run->attempts()->first()->error_code);
        app(GradingOrchestrator::class)->process($run->id,$old); $this->assertSame(1,$run->attempts()->count());
        app(GradingOrchestrator::class)->control($a,$u->id,$run->id,'cancel');
        $adapter=new class extends PreflightOnlyStages { public $cancel; public function execute(string $stage,array $input,array $previous,array $configuration): array { ($this->cancel)(); return parent::execute($stage,$input,$previous,$configuration); } };
        $this->app->instance(GradingStages::class,$adapter); $run=$this->start($u,$a,$answer);
        $adapter->cancel=fn()=>app(GradingOrchestrator::class)->control($a,$u->id,$run->id,'cancel'); $this->step($run);
        $this->assertSame('cancelled',$run->status); $this->assertSame('cancelled',$run->attempts()->first()->status); $this->assertNull($run->attempts()->first()->output);
    }
    public function test_expired_lease_recovery_and_old_worker_result_cannot_overwrite_retry(): void {
        [$u,$a,$answer]=$this->fixture(); $run=$this->start($u,$a,$answer); $token=(string)Str::uuid();
        $run->attempts()->create(['stage'=>'preflight','number'=>1,'lease_token'=>$token,'status'=>'running','started_at'=>now()]);
        $run->update(['status'=>'running','lease_token'=>$token,'lease_until'=>now()->addSeconds(120)]);
        $workflow=app(GradingOrchestrator::class); $this->fails(fn()=>$workflow->control($a,$u->id,$run->id,'resume')); $this->assertSame(0,$workflow->recoverExpired());
        $this->travel(121)->seconds(); $this->assertSame(1,$workflow->recoverExpired()); $this->assertSame('failed',$run->fresh()->status);
        $this->assertSame('worker_interrupted',$run->attempts()->first()->error_code); $workflow->control($a,$u->id,$run->id,'resume');
        $this->step($run); $this->assertSame('marker_a',$run->stage); $this->assertSame(2,$run->attempts()->count());
    }
    public function test_ownership_account_status_request_scope_and_queue_atomicity(): void {
        [$u,$a,$answer]=$this->fixture(); [$other,$foreign,$foreignAnswer]=$this->fixture(); $this->actingAs($u);
        $url='/assessments/'.$a->id.'/grading-runs';
        $this->post($url,['answer_id'=>$answer->id,'creation_key'=>(string)Str::uuid()])->assertSessionHasErrors('confirmation');
        $this->post($url,$this->body($foreignAnswer))->assertNotFound();
        $run=$this->start($u,$a,$answer); $this->actingAs($other)->get($url.'/'.$run->id)->assertForbidden();
        $this->post($url.'/'.$run->id.'/control',['action'=>'cancel'])->assertForbidden();
        $this->get('/assessments/'.$foreign->id.'/grading-runs/'.$run->id)->assertNotFound();
        $u->is_active=false; $u->save(); $this->step($run); $this->assertSame('cancelled',$run->status);
        $this->actingAs($u)->get($url)->assertRedirect('/login');
        DB::beginTransaction(); $new=$this->start($other,$foreign,$foreignAnswer); $count=DB::table('agentmath_jobs')->count(); DB::rollBack();
        $this->assertNull(GradingRun::find($new->id)); $this->assertSame($count-1,DB::table('agentmath_jobs')->count());
    }
    public function test_stage_contract_order_marker_isolation_and_saved_checkpoints(): void {
        [$u,$a,$answer]=$this->fixture();
        // Contract-only test adapter. No production marker or score implementation in M04.
        $adapter=new class extends PreflightOnlyStages {
            public array $calls=[];
            public function supports(string $stage): bool { return true; }
            public function execute(string $stage,array $input,array $previous,array $configuration): array {
                $this->calls[]=['stage'=>$stage,'previous'=>$previous,'text'=>$input['answer_text']];
                return ['test_stage'=>$stage];
            }
            public function validate(string $stage,array $output): array { if(($output['test_stage'] ?? null)!==$stage) throw new \UnexpectedValueException(); return $output; }
        };
        $this->app->instance(GradingStages::class,$adapter); $run=$this->start($u,$a,$answer);
        foreach(GradingStages::PLAN as $stage) { $this->assertSame($stage,$run->stage); $this->step($run); }
        $this->assertSame('completed',$run->status); $this->assertCount(5,$adapter->calls);
        $this->assertSame([],$adapter->calls[1]['previous']); $this->assertSame([],$adapter->calls[2]['previous']);
        $this->assertSame('marker_a',$adapter->calls[3]['previous']['marker_a']['test_stage']);
        $this->assertSame('marker_b',$adapter->calls[3]['previous']['marker_b']['test_stage']);
        $this->assertSame(5,$run->attempts()->where('status','succeeded')->count());
        $this->fails(fn()=>app(GradingOrchestrator::class)->control($a,$u->id,$run->id,'resume'));
        try { $run->update(['input_snapshot'=>['answer_text'=>'Tampered']]); $this->fail('Mutable snapshot'); } catch(\LogicException) { $this->assertTrue(true); }
    }
    public function test_late_worker_result_and_changed_source_during_stage_are_discarded(): void {
        [$u,$a,$answer,$q]=$this->fixture();
        $adapter=new class extends PreflightOnlyStages { public $during; public function execute(string $stage,array $input,array $previous,array $configuration): array { ($this->during)(); return parent::execute($stage,$input,$previous,$configuration); } };
        $this->app->instance(GradingStages::class,$adapter); $run=$this->start($u,$a,$answer);
        $adapter->during=fn()=>$q->update(['prompt'=>'Different during execution']); $this->step($run);
        $this->assertSame('stale',$run->status); $this->assertNull($run->attempts()->first()->output);
        $q->update(['prompt'=>'1 - 1 = ?']); $run=$this->start($u,$a,$answer);
        $adapter->during=function() use($u,$a,$run) { $this->travel(121)->seconds(); app(GradingOrchestrator::class)->control($a,$u->id,$run->id,'resume'); };
        $this->step($run); $this->assertSame('queued',$run->status); $this->assertSame('preflight',$run->stage);
        $this->assertSame('failed',$run->attempts()->first()->status); $this->assertNull($run->attempts()->first()->output);
    }
    public function test_lost_queued_delivery_can_be_requeued_without_rerunning_successful_stage(): void {
        [$u,$a,$answer]=$this->fixture(); $run=$this->start($u,$a,$answer); $workflow=app(GradingOrchestrator::class);
        $oldGeneration=$run->generation; DB::table('agentmath_jobs')->delete();
        $this->fails(fn()=>$workflow->control($a,$u->id,$run->id,'resume')); $this->travel(121)->seconds();
        $workflow->control($a,$u->id,$run->id,'resume'); $workflow->process($run->id,$oldGeneration);
        $this->assertSame(0,$run->attempts()->count()); $this->assertSame(1,DB::table('agentmath_jobs')->count());
        $this->step($run); $this->assertSame('marker_a',$run->stage); $this->assertSame(1,$run->attempts()->count());
    }
}
