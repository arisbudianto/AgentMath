<?php
namespace Tests\Feature;
use App\Models\{EvalAttempt,EvalCase,EvalDataset,EvalExperiment,EvalObservation,EvalReference,EvalSample,GradeReview,User};
use App\Services\{AnswerIntake,EvaluationData,EvaluationMetrics,EvaluationWorkflow,GradingOrchestrator,GradingStages,RubricWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EvaluationTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role='teacher'): User {
        $u=new User(['name'=>'DO_NOT_EXPORT_NAME','email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role=$role; $u->is_active=true; $u->save(); return $u;
    }
    private function marker(array $scores=[0,1]): array {
        return ['criteria'=>array_map(fn($i)=>['criterion_id'=>'C'.($i+1),'score'=>$scores[$i],'evidence'=>['1 + 1 = 2'],'missing_evidence'=>false,'reason'=>'MULTI_SECRET_REASON','rule_ids'=>['R1']],[0,1])];
    }
    private function fake(array $results): void {
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        $seq=Http::sequence(); foreach($results as $r) $seq->push(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($r)]]]],'usage'=>['input_tokens'=>10,'output_tokens'=>20]]);
        Http::fake(['ai.dinoiki.com/*'=>$seq]);
    }
    private function fixture(bool $multi=true,bool $freeze=true): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'SECRET_EVAL','ai.max_output_tokens'=>4000]);
        $u=$this->user(); $c=$u->classrooms()->create(['name'=>'Synthetic','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'DO_NOT_SEND_CODE']);
        $a=$u->assessments()->make(['title'=>'Synthetic evaluation']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1+1?','answer_key'=>'2','scoring_guidelines'=>'One point per step and result.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $rw=app(RubricWorkflow::class); $r=$rw->create($q,$u->id,(string)Str::uuid(),'manual');
        $rubric=$r->only(['rules','criteria','grading_policy']); $rubric['criteria'][0]['max_score']='1.00'; $rubric['criteria'][]=array_replace($rubric['criteria'][0],['criterion_id'=>'C2']);
        $r=$rw->save($q,$r->id,$r->revision,$rubric,false,$u->id); $rw->save($q,$r->id,$r->revision,[],true,$u->id);
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'1 + 1 = 2 DO_NOT_EXPORT_TEXT']);
        $this->actingAs($u); $data=app(EvaluationData::class);
        $d=$data->dataset($a,$u->id,(string)Str::uuid(),'Synthetic protocol','synthetic','Test implementation only.','Synthetic metrics, not empirical accuracy.','0');
        $case=$data->add($a,$u->id,$d->id,$answer->id,'test',(string)Str::uuid());
        $reference=$data->label($a,$u->id,$d->id,$case->id,(string)Str::uuid(),0,[['criterion_id'=>'C1','score'=>'0.00'],['criterion_id'=>'C2','score'=>'1.00']],'HUMAN_SECRET_REASON',true,20);
        if($freeze) $d=$data->freeze($a,$u->id,$d->id,true);
        $run=null;
        if($multi) {
            $this->fake([$this->marker([1,0]),$this->marker([1,0]),['label'=>'High','reason'=>'Synthetic consistency.','concerns'=>[]]]);
            $w=app(GradingOrchestrator::class); $run=$w->start($a,$u->id,$answer->id,(string)Str::uuid());
            foreach(GradingStages::PLAN as $stage) { $run->refresh(); $w->process($run->id,$run->generation); }
            $this->assertSame('completed',$run->fresh()->status);
        }
        return [$u,$a,$answer,$q,$d,$case,$reference,$run,$c];
    }
    private function start(array $f,bool $reuse=false,?string $key=null): EvalExperiment { return app(EvaluationWorkflow::class)->start($f[1],$f[0]->id,$f[4]->id,'test',$key ?? (string)Str::uuid(),true,$reuse); }
    private function fails(callable $f): void { try { $f(); $this->fail('Expected validation failure'); } catch(ValidationException) { $this->assertTrue(true); } }
    private function step(EvalSample $s): void { $s->refresh(); app(EvaluationWorkflow::class)->process($s->id,$s->generation); $s->refresh(); }
    private function base(array $f): string { return '/assessments/'.$f[1]->id.'/evaluations/'.$f[4]->id; }

    public function test_fresh_baseline_worker_isolated_from_labels_multi_and_identity_and_paired_metrics(): void {
        $f=$this->fixture(); $this->fake([$this->marker()]); $e=$this->start($f);
        $this->get($this->base($f))->assertOk()->assertSee('Jalankan eksperimen berpasangan');
        $this->artisan('agentmath:work')->assertSuccessful(); $sample=$e->samples()->first();
        $this->assertSame('succeeded',$sample->status); Http::assertSentCount(1);
        $this->assertSame(1,\App\Models\AiCall::where('kind','baseline')->count());
        $this->assertSame(3,\App\Models\AiCall::where('kind','grading')->count());
        Http::assertSent(function($request) {
            $input=json_decode($request['input'],true); $text=$request['input'];
            return array_keys($input['untrusted_data'])===['source','rubric','answer_text'] && !str_contains($text,'HUMAN_SECRET_REASON') && !str_contains($text,'MULTI_SECRET_REASON')
                && !str_contains($text,'DO_NOT_SEND_CODE') && !str_contains($text,'DO_NOT_EXPORT_NAME') && $request['store']===false;
        });
        $report=app(EvaluationMetrics::class)->report($e); $this->assertSame(1,$report['paired']);
        $this->assertSame(100.0,$report['metrics']['multi']['exact_percent']); $this->assertSame(0.0,$report['metrics']['multi']['criterion_exact_percent']);
        $this->assertSame(100.0,$report['metrics']['baseline']['criterion_exact_percent']); $this->assertSame(1,$report['metrics']['multi']['high_wrong']);
        $this->assertSame(100.0,$report['metrics']['multi']['high_error_percent']); $this->assertSame(0,$report['metrics']['baseline']['wrong']);
        $this->assertSame(30,$report['metrics']['multi']['input_tokens']); $this->assertSame(10,$report['metrics']['baseline']['input_tokens']);
        $this->assertSame(0,GradeReview::count()); $this->assertSame(1,DB::table('answer_revisions')->count());
        $url=$this->base($f).'/experiments/'.$e->id;
        $this->get($url)->assertOk()->assertSee('Laporan eksperimen')->assertSee('Data sintetis')->assertSee('Kesalahan berlabel High');
        $csv=$this->get($url.'/export')->assertOk()->streamedContent();
        foreach(['SECRET_EVAL','HUMAN_SECRET_REASON','DO_NOT_EXPORT_TEXT','MULTI_SECRET_REASON','DO_NOT_EXPORT_NAME'] as $secret) $this->assertStringNotContainsString($secret,$csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
    }
    public function test_reference_is_manual_versioned_and_freeze_locks_protocol_labels_and_split(): void {
        $f=$this->fixture(false,false); $data=app(EvaluationData::class); $rows=[['criterion_id'=>'C1','score'=>1],['criterion_id'=>'C2','score'=>0]];
        $this->get($this->base($f).'/cases/'.$f[5]->id)->assertOk()->assertDontSee('Hasil multi-agent tersimpan');
        $this->fails(fn()=>$data->label($f[1],$f[0]->id,$f[4]->id,$f[5]->id,(string)Str::uuid(),1,$rows,'Reason',false,null));
        $this->fails(fn()=>$data->label($f[1],$f[0]->id,$f[4]->id,$f[5]->id,(string)Str::uuid(),0,$rows,'Reason',true,null));
        $r=$data->label($f[1],$f[0]->id,$f[4]->id,$f[5]->id,(string)Str::uuid(),1,$rows,'Independent correction.',true,null);
        $d=$data->freeze($f[1],$f[0]->id,$f[4]->id,true); $this->assertSame($r->id,$d->manifest['cases'][0]['reference_id']);
        $this->fails(fn()=>$data->label($f[1],$f[0]->id,$d->id,$f[5]->id,(string)Str::uuid(),2,$rows,'No',true,null));
        foreach([$d,$f[5],$r] as $record) {
            try { $record->update(['created_by'=>999]); $this->fail('Mutable research record'); } catch(\LogicException) { $this->assertTrue(true); }
            try { $record->delete(); $this->fail('Deleted record'); } catch(\LogicException) { $this->assertTrue(true); }
        }
        Http::assertNothingSent();
    }
    public function test_question_group_split_prevents_answer_leakage_and_duplicate_revision(): void {
        $f=$this->fixture(false,false); $data=app(EvaluationData::class);
        $p=$f[8]->participants()->create(['code'=>'SECOND']); $answer=app(AnswerIntake::class)->create($f[1],$f[0]->id,$p->id,$f[3]->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'1 + 1 = 2']);
        $this->fails(fn()=>$data->add($f[1],$f[0]->id,$f[4]->id,$answer->id,'tuning',(string)Str::uuid()));
        $data->add($f[1],$f[0]->id,$f[4]->id,$answer->id,'test',(string)Str::uuid());
        $this->fails(fn()=>$data->add($f[1],$f[0]->id,$f[4]->id,$answer->id,'test',(string)Str::uuid()));
        $this->fails(fn()=>$data->freeze($f[1],$f[0]->id,$f[4]->id,true)); // Missing second human reference.
    }
    public function test_reference_bounds_unknown_ids_and_confirmation_never_create_zero_labels(): void {
        $f=$this->fixture(false,false); $data=app(EvaluationData::class);
        foreach([2,-1,'0.001','1e0'] as $score) {
            $this->fails(fn()=>$data->label($f[1],$f[0]->id,$f[4]->id,$f[5]->id,(string)Str::uuid(),1,[['criterion_id'=>'C1','score'=>$score],['criterion_id'=>'C2','score'=>0]],'Reason',true,null));
        }
        $this->fails(fn()=>$data->scores([['criterion_id'=>'C1','score'=>0],['criterion_id'=>'C1','score'=>0]],$f[5]->input_snapshot));
        $this->assertSame(1,EvalReference::count());
        $this->post($this->base($f).'/freeze',[])->assertSessionHasErrors('confirmation');
    }
    public function test_replay_config_match_test_reuse_and_queue_payload_are_safe(): void {
        $f=$this->fixture(); $this->fake([]); $key=(string)Str::uuid(); $e=$this->start($f,false,$key); $same=$this->start($f,false,$key);
        $this->assertSame($e->id,$same->id); $this->assertSame(1,EvalExperiment::count()); $this->assertSame(1,EvalSample::count());
        $payload=DB::table('agentmath_jobs')->get()->pluck('payload')->implode('');
        foreach(['SECRET_EVAL','HUMAN_SECRET_REASON','DO_NOT_EXPORT_TEXT'] as $secret) $this->assertStringNotContainsString($secret,$payload);
        $this->fails(fn()=>$this->start($f)); $second=$this->start($f,true); $this->assertTrue($second->test_reused);
        $this->get($this->base($f).'/experiments/'.$second->id)->assertSee('Test digunakan ulang');
        Http::assertNothingSent(); $this->assertStringNotContainsString('SECRET_EVAL',json_encode($e->toArray()));
    }
    public function test_failed_baseline_metrics_are_unavailable_not_zero_and_manual_retry_preserves_history(): void {
        $f=$this->fixture(); $this->fake([]); Http::fake(['ai.dinoiki.com/*'=>Http::response(['error'=>['message'=>'SECRET_EVAL']],429)]);
        $e=$this->start($f); $s=$e->samples()->first(); $old=$s->generation; $this->step($s);
        $this->assertSame('failed',$s->status); $this->assertNull($s->attempts()->first()->output);
        $r=app(EvaluationMetrics::class)->report($e); $this->assertSame(0,$r['paired']); $this->assertNull($r['metrics']['multi']['mae']); $this->assertSame(1,$r['total']);
        app(EvaluationWorkflow::class)->process($s->id,$old); $this->assertSame(1,$s->attempts()->count());
        $this->fake([$this->marker()]); app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id); $this->step($s);
        $this->assertSame('succeeded',$s->status); $this->assertSame(2,$s->attempts()->count()); $this->assertSame(1,app(EvaluationMetrics::class)->report($e)['paired']);
        $this->fails(fn()=>app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id));
    }
    public function test_nonzero_error_uses_question_scale_and_failures_stop_after_three_attempts(): void {
        $f=$this->fixture(); $this->fake([$this->marker([0,0])]); $e=$this->start($f); $s=$e->samples()->first(); $this->step($s);
        $m=app(EvaluationMetrics::class)->report($e)['metrics']['baseline'];
        $this->assertSame(1.0,$m['mae']); $this->assertSame(50.0,$m['normalized_mae_percent']);
        $this->assertSame(50.0,$m['criterion_exact_percent']); $this->assertSame(50.0,$m['criterion_normalized_mae_percent']); $this->assertSame(1,$m['wrong']);
        $this->fake([]); $e=$this->start($f,true); $s=$e->samples()->first();
        for($i=0;$i<3;$i++) {
            Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests(); Http::fake(['ai.dinoiki.com/*'=>Http::response([],429)]);
            $this->step($s); $this->assertSame('failed',$s->status);
            if($i<2) app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id);
        }
        $this->assertSame(3,$s->attempts()->count());
        $this->fails(fn()=>app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id));
    }
    public function test_stalled_queue_can_be_replaced_without_duplicate_provider_calls(): void {
        $f=$this->fixture(); $this->fake([$this->marker()]); $e=$this->start($f); $s=$e->samples()->first(); $old=$s->generation;
        $this->fails(fn()=>app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id));
        $this->travel(121)->seconds(); app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id);
        app(EvaluationWorkflow::class)->process($s->id,$old); Http::assertNothingSent();
        $this->step($s); Http::assertSentCount(1); $this->assertSame('succeeded',$s->status); $this->assertSame(1,$s->attempts()->count());
    }
    public function test_config_drift_or_corrupted_frozen_case_never_calls_provider(): void {
        $f=$this->fixture(); $this->fake([]); $e=$this->start($f); $s=$e->samples()->first();
        config(['ai.model'=>'gpt-other']); $this->step($s); Http::assertNothingSent();
        $this->assertSame('configuration_changed',$s->attempts()->first()->error_code);
        config(['ai.model'=>'gpt-5-mini']); app(EvaluationWorkflow::class)->retry($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id);
        DB::table('eval_cases')->where('id',$f[5]->id)->update(['input_hash'=>str_repeat('0',64)]);
        $this->step($s); Http::assertNothingSent(); $this->assertSame('failed',$s->status);
        $this->assertSame(1,app(EvaluationMetrics::class)->report($e)['invalid']);
    }
    public function test_cancellation_during_request_discards_result_and_expired_worker_can_be_retried(): void {
        $f=$this->fixture(); $this->fake([]); $e=$this->start($f); $s=$e->samples()->first();
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        Http::fake(['ai.dinoiki.com/*'=>function() use($f,$e) {
            app(EvaluationWorkflow::class)->cancel($f[1],$f[0]->id,$f[4]->id,$e->id);
            return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($this->marker())]]]]]);
        }]); $this->step($s); $this->assertSame('cancelled',$s->status); $this->assertNull($s->attempts()->first()->output);
        $this->fake([]); $e=$this->start($f,true); $s=$e->samples()->first(); $token=(string)Str::uuid();
        $s->attempts()->create(['number'=>1,'lease_token'=>$token,'status'=>'running','started_at'=>now()]);
        $s->update(['status'=>'running','lease_token'=>$token,'lease_until'=>now()->addSeconds(120)]); $this->travel(121)->seconds();
        $this->assertSame(1,app(EvaluationWorkflow::class)->recoverExpired()); $this->assertSame('failed',$s->fresh()->status);
        $this->assertSame('worker_interrupted',$s->attempts()->first()->error_code);
    }
    public function test_missing_multi_is_counted_and_frozen_snapshots_survive_operational_revisions(): void {
        $f=$this->fixture(true,false); $data=app(EvaluationData::class); $p=$f[8]->participants()->create(['code'=>'SECOND']);
        $answer=app(AnswerIntake::class)->create($f[1],$f[0]->id,$p->id,$f[3]->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'1 + 1 = 2']);
        $case=$data->add($f[1],$f[0]->id,$f[4]->id,$answer->id,'test',(string)Str::uuid());
        $data->label($f[1],$f[0]->id,$f[4]->id,$case->id,(string)Str::uuid(),0,[['criterion_id'=>'C1','score'=>0],['criterion_id'=>'C2','score'=>1]],'Independent reference.',true,null);
        $data->freeze($f[1],$f[0]->id,$f[4]->id,true);
        app(AnswerIntake::class)->revise($f[1],$f[0]->id,$f[2]->id,1,'Operational correction',['content_type'=>'text','answer_text'=>'New version']);
        $this->fake([$this->marker()]); $e=$this->start($f); foreach($e->samples()->where('status','queued')->get() as $s) $this->step($s);
        $r=app(EvaluationMetrics::class)->report($e); $this->assertSame(2,$r['total']); $this->assertSame(1,$r['paired']); $this->assertSame(1,$r['statuses']['missing_multi']);
        Http::assertSentCount(1);
    }
    public function test_observations_have_independent_versions_and_timing_is_not_invented(): void {
        $f=$this->fixture(); $this->fake([$this->marker()]); $e=$this->start($f); $s=$e->samples()->first(); $this->step($s);
        $w=app(EvaluationWorkflow::class); $key=(string)Str::uuid();
        $o=$w->observe($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id,$key,0,'baseline','none','Checked against source.',10);
        $again=$w->observe($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id,$key,0,'baseline','none','Checked against source.',10); $this->assertSame($o->id,$again->id);
        $this->fails(fn()=>$w->observe($f[1],$f[0]->id,$f[4]->id,$e->id,$s->id,(string)Str::uuid(),0,'baseline','none','Stale',20));
        $r=app(EvaluationMetrics::class)->report($e); $this->assertSame(10.0,$r['metrics']['baseline']['mean_review_seconds']); $this->assertNull($r['metrics']['multi']['mean_review_seconds']);
        $this->assertSame(1,EvalObservation::count()); $this->assertSame('1.00',$f[6]->total_score);
        $this->get($this->base($f).'/experiments/'.$e->id.'/samples/'.$s->id)->assertOk()->assertSee('Catatan tinjauan');
    }
    public function test_ownership_roles_foreign_scope_and_no_operational_grade_changes(): void {
        $f=$this->fixture(); foreach(['teacher','admin','student'] as $role) {
            $this->actingAs($this->user($role))->get($this->base($f))->assertForbidden();
            $this->post($this->base($f).'/freeze',[])->assertForbidden();
        }
        $this->actingAs($f[0]); $own=$f[0]->assessments()->create(['title'=>'Another']);
        $this->get('/assessments/'.$own->id.'/evaluations/'.$f[4]->id)->assertNotFound();
        $this->assertSame(0,GradeReview::count()); $this->assertSame(1,DB::table('answer_revisions')->count());
    }
    public function test_no_pair_or_disabled_ai_does_not_enqueue_paid_work_and_transaction_rollback(): void {
        $f=$this->fixture(false); $this->fake([]); $this->fails(fn()=>$this->start($f)); $this->assertSame(0,EvalExperiment::count());
        Http::assertNothingSent(); config(['ai.enabled'=>false]); $this->fails(fn()=>$this->start($f)); $this->assertSame(0,EvalSample::count());
    }
}

