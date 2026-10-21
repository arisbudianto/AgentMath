<?php
namespace Tests\Feature;
use App\Models\{AiCall,User};
use App\Services\{AiAudit,GradingAi,GradingOrchestrator,RubricWorkflow,AnswerIntake};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAuditTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role='teacher'): User {
        $u=new User(['name'=>'PRIVATE_NAME','email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role=$role; $u->is_active=true; $u->save(); return $u;
    }
    private function fixture(): array {
        config(['ai.enabled'=>true,'ai.transcription_enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'PRIVATE_KEY','ai.max_output_tokens'=>4000]);
        Http::preventStrayRequests(); $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $u=$this->user(); $c=$u->classrooms()->create(['name'=>'Audit synthetic','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'PRIVATE_CODE']);
        $a=$u->assessments()->make(['title'=>'Audit synthetic']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1+1?','answer_key'=>'2','scoring_guidelines'=>'Two points for correct answer.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $r=app(RubricWorkflow::class)->create($q,$u->id,(string)Str::uuid(),'manual'); app(RubricWorkflow::class)->save($q,$r->id,$r->revision,[],true,$u->id);
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'1 + 1 = 2 PRIVATE_ANSWER']);
        $this->actingAs($u); return [$u,$a,$q,$answer,$p];
    }
    private function marker(): array { return ['criteria'=>[['criterion_id'=>'C1','score'=>2,'evidence'=>['1 + 1 = 2'],'missing_evidence'=>false,'reason'=>'PRIVATE_REASON','rule_ids'=>['R1']]]]; }
    private function response(array $data,array $usage=[]): array { return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]],'usage'=>$usage]; }
    private function rates(): void { config(['audit'=>['provider'=>'dinoiki','model'=>'gpt-5-mini','currency'=>'USD','version'=>'test-v1','input'=>'2','cached'=>'1','output'=>'4']]); }
    private function config(): array { return ['pipeline_version'=>'m05_v1','provider'=>'dinoiki','model'=>'gpt-5-mini','enabled'=>true,'max_output_tokens'=>4000]; }
    private function auditCall(array $f): void { app(AiAudit::class)->within($f[1],'rubric',$f[2]->id,(string)Str::uuid(),fn()=>app(GradingAi::class)->call('marker_a','PRIVATE_PROMPT',['text'=>'PRIVATE_INPUT'],[], $this->config())); }
    private function fake(array $body,int $status=200): void { Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests(); Http::fake(['ai.dinoiki.com/*'=>Http::response($body,$status)]); }
    private function url(array $f): string { return '/assessments/'.$f[1]->id.'/audit'; }

    public function test_real_worker_records_only_actual_requests_and_links_attempts_without_private_content(): void {
        $f=$this->fixture(); $this->rates(); $seq=Http::sequence();
        foreach([$this->marker(),$this->marker(),['label'=>'High','reason'=>'Synthetic','concerns'=>[]]] as $data) $seq->push($this->response($data,['input_tokens'=>100,'output_tokens'=>10,'input_tokens_details'=>['cached_tokens'=>40]]));
        Http::fake(['ai.dinoiki.com/*'=>$seq]); $run=app(GradingOrchestrator::class)->start($f[1],$f[0]->id,$f[3]->id,(string)Str::uuid());
        $this->artisan('agentmath:work')->assertSuccessful(); $this->assertSame('completed',$run->fresh()->status);
        $this->assertSame(['marker_a','marker_b','confidence'],AiCall::orderBy('id')->pluck('stage')->all());
        foreach(AiCall::all() as $c) { $this->assertSame('validated',$c->outcome); $this->assertSame('0.000200000000',$c->estimated_cost); $this->assertTrue($run->attempts()->whereKey($c->source_id)->exists()); }
        $this->get($this->url($f))->assertOk()->assertSee('Operasional dan audit AI')->assertSee('Proses #'.$run->id)->assertSee('0.0006');
        $this->get($this->url($f).'?run='.$run->id)->assertOk()->assertSee('3 panggilan');
        $this->getJson($this->url($f).'?run='.$run->id.'&kind=baseline')->assertUnprocessable();
        $this->get($this->url($f).'?run=999999')->assertNotFound();
        $csv=$this->get($this->url($f).'/export')->assertOk()->streamedContent(); $json=AiCall::all()->toJson();
        foreach(['PRIVATE_KEY','PRIVATE_NAME','PRIVATE_CODE','PRIVATE_ANSWER','PRIVATE_REASON','PRIVATE_PROMPT'] as $secret) { $this->assertStringNotContainsString($secret,$json); $this->assertStringNotContainsString($secret,$csv); }
        Http::assertSentCount(3); $this->assertSame(3,AiCall::count());
    }
    public function test_rejected_provider_response_keeps_sanitized_usage_and_frozen_rate_instead_of_zero(): void {
        $f=$this->fixture(); $this->rates(); $this->fake(['error'=>['message'=>'PRIVATE_KEY PRIVATE_INPUT'],'usage'=>['input_tokens'=>100,'output_tokens'=>10,'input_tokens_details'=>['cached_tokens'=>40]]],429);
        try { $this->auditCall($f); $this->fail(); } catch(\RuntimeException) {}
        $c=AiCall::first(); $this->assertSame('rejected',$c->outcome); $this->assertSame('response_received',$c->state); $this->assertSame(429,$c->http_status); $this->assertSame('0.000200000000',$c->estimated_cost);
        config(['audit.input'=>'999']); $this->assertSame('2',$c->fresh()->tariff['input']);
        $this->assertStringNotContainsString('PRIVATE_KEY',$c->toJson());
        try { $c->update(['input_tokens'=>0]); $this->fail(); } catch(\LogicException) { $this->assertTrue(true); }
        try { $c->delete(); $this->fail(); } catch(\LogicException) { $this->assertTrue(true); }
    }
    public function test_transport_failure_and_missing_cached_usage_do_not_invent_costs_and_context_is_cleared(): void {
        $f=$this->fixture(); $this->rates(); Http::fake(['ai.dinoiki.com/*'=>Http::failedConnection('PRIVATE_KEY')]);
        try { $this->auditCall($f); $this->fail(); } catch(\RuntimeException) {}
        $c=AiCall::first(); $this->assertSame('transport_failed',$c->state); $this->assertNull($c->input_tokens); $this->assertNull($c->estimated_cost); $this->assertNull($c->http_status);
        $this->fake($this->response($this->marker(),['input_tokens'=>100,'output_tokens'=>10])); $this->auditCall($f);
        $this->assertNull(AiCall::latest('id')->first()->estimated_cost);
        config(['audit.cached'=>'2']); $this->auditCall($f); $this->assertSame('0.000240000000',AiCall::latest('id')->first()->estimated_cost);
        config(['audit.model'=>'different']); $this->auditCall($f); $this->assertNull(AiCall::latest('id')->first()->tariff);
        $this->assertSame(4,AiCall::count()); $this->assertSame(3,AiCall::where('outcome','validated')->count());
    }
    public function test_domain_invalid_grading_still_audits_tokens_and_retry_has_new_request(): void {
        $f=$this->fixture(); $this->fake($this->response(['criteria'=>[]],['input_tokens'=>12,'output_tokens'=>3]));
        $w=app(GradingOrchestrator::class); $run=$w->start($f[1],$f[0]->id,$f[3]->id,(string)Str::uuid());
        $w->process($run->id,$run->generation); $run->refresh(); $w->process($run->id,$run->generation);
        $this->assertSame('failed',$run->fresh()->status); $this->assertSame('rejected',AiCall::first()->outcome); $this->assertSame(12,AiCall::first()->input_tokens);
        $this->assertNull($run->attempts()->where('stage','marker_a')->first()->output);
        $this->fake($this->response($this->marker())); $run=$w->control($f[1],$f[0]->id,$run->id,'resume'); $w->process($run->id,$run->generation);
        $this->assertSame(2,AiCall::count()); $this->assertSame(2,AiCall::distinct()->count('correlation_id'));
    }
    public function test_rubric_failure_preserves_two_calls_even_without_a_saved_ai_draft(): void {
        $f=$this->fixture(); $seq=Http::sequence();
        $seq->push($this->response(['rules'=>[['rule_id'=>'R1','source'=>'scoring_guidelines','quote'=>$f[2]->scoring_guidelines,'description'=>'PRIVATE_RULE']]],['input_tokens'=>5,'output_tokens'=>2]));
        $seq->push($this->response(['criteria'=>[],'grading_policy'=>'Invalid'],['input_tokens'=>8,'output_tokens'=>4])); Http::fake(['ai.dinoiki.com/*'=>$seq]);
        try { app(RubricWorkflow::class)->create($f[2],$f[0]->id,(string)Str::uuid(),'ai'); $this->fail(); } catch(\Illuminate\Validation\ValidationException) {}
        $this->assertSame(2,AiCall::count()); $this->assertSame(2,AiCall::where('outcome','rejected')->count()); $this->assertSame(1,$f[2]->rubricVersions()->count());
        $this->assertSame(1,AiCall::distinct()->count('correlation_id')); $this->assertStringNotContainsString('PRIVATE_RULE',AiCall::all()->toJson());
    }
    public function test_photo_entrypoint_is_audited_and_replay_does_not_call_again(): void {
        $f=$this->fixture(); Storage::fake('answers');
        $p=$f[4]->classroom->participants()->create(['code'=>'SECOND']);
        $photo=\Illuminate\Http\UploadedFile::fake()->createWithContent('s.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='));
        $answer=app(AnswerIntake::class)->create($f[1],$f[0]->id,$p->id,$f[2]->id,(string)Str::uuid(),['content_type'=>'photo','photo'=>$photo]);
        $this->fake($this->response(['transcript_text'=>'1 + 1 = 2','uncertainties'=>[]],['input_tokens'=>40,'output_tokens'=>10]));
        $url='/assessments/'.$f[1]->id.'/answers/'.$answer->id.'/revisions/'.$answer->latestRevision->id.'/transcription';
        $body=['creation_key'=>(string)Str::uuid(),'expected_version'=>0,'action'=>'ai','reason'=>'Synthetic','privacy_checked'=>1];
        $this->post($url,$body)->assertRedirect(); $this->post($url,$body)->assertRedirect(); Http::assertSentCount(1);
        $this->assertSame('transcription',AiCall::first()->kind); $this->assertSame('validated',AiCall::first()->outcome); $this->get($this->url($f))->assertOk()->assertSee('Revisi foto');
    }
    public function test_reports_enforce_owner_filters_and_unknown_values_with_private_csv(): void {
        $f=$this->fixture(); $this->fake($this->response($this->marker(),['input_tokens'=>0,'output_tokens'=>0])); $this->rates(); config(['audit.cached'=>'2']);
        for($i=0;$i<26;$i++) $this->auditCall($f);
        $this->get($this->url($f))->assertOk()->assertSee('26 panggilan')->assertHeader('Cache-Control','no-store, private');
        $csv=$this->get($this->url($f).'/export')->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->streamedContent(); $this->assertCount(28,explode("\n",trim($csv,"\r")));
        $this->get($this->url($f).'?kind=baseline')->assertOk()->assertSee('0 panggilan');
        $this->getJson($this->url($f).'?from=2026-01-01&to=2026-12-31')->assertUnprocessable();
        $this->getJson($this->url($f).'?kind=bogus')->assertUnprocessable();
        foreach(['teacher','admin','student'] as $role) { $this->actingAs($this->user($role)); $this->get($this->url($f))->assertForbidden(); $this->get($this->url($f).'/export')->assertForbidden(); }
        $this->assertSame(26,AiCall::count());
    }
    public function test_invalid_usage_and_tariff_remain_unknown_and_interrupted_rows_are_visible(): void {
        $f=$this->fixture(); $this->rates();
        $this->fake($this->response($this->marker(),['input_tokens'=>100,'output_tokens'=>10,'input_tokens_details'=>['cached_tokens'=>101]])); $this->auditCall($f);
        $this->assertNull(AiCall::first()->cached_tokens); $this->assertNull(AiCall::first()->estimated_cost);
        $this->fake($this->response($this->marker(),['input_tokens'=>'100','output_tokens'=>-1])); $this->auditCall($f);
        $last=AiCall::latest('id')->first(); $this->assertNull($last->input_tokens); $this->assertNull($last->output_tokens); $this->assertNull($last->estimated_cost);
        config(['audit.input'=>'NaN']); $this->assertNull(app(AiAudit::class)->tariff('dinoiki','gpt-5-mini'));
        $row=AiCall::first()->only(['assessment_id','kind','source_id','stage','provider','model','prompt_version']);
        AiCall::create($row+['correlation_id'=>(string)Str::uuid(),'state'=>'in_flight','outcome'=>'pending','started_at'=>now()->subMinutes(5)]);
        $this->get($this->url($f))->assertOk()->assertSee('1 belum diketahui')->assertSee('in_flight')->assertSee('Belum diketahui');
        $csv=$this->get($this->url($f).'/export?outcome=pending')->assertOk()->streamedContent();
        $this->assertStringContainsString('in_flight,pending,,,', $csv);
    }
}
