<?php
namespace Tests\Feature;
use App\Models\{EvalAttempt,EvalCase,EvalDataset,EvalExperiment,EvalObservation,EvalReference,EvalSample,GradeReview,User};
use App\Services\{AnswerIntake,EvaluationData,EvaluationMetrics,EvaluationWorkflow,GradingOrchestrator,GradingStages,RubricWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResearchQualityTest extends TestCase {
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
    private function fixture(bool $multi=false,bool $freeze=false,bool $photo=false,bool $strict=true,bool $rawAi=true): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'SECRET_EVAL','ai.max_output_tokens'=>4000]);
        $u=$this->user(); $c=$u->classrooms()->create(['name'=>'Synthetic','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'DO_NOT_SEND_CODE']);
        $a=$u->assessments()->make(['title'=>'Synthetic evaluation']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1+1?','answer_key'=>'2','scoring_guidelines'=>'One point per step and result.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $rw=app(RubricWorkflow::class); $r=$rw->create($q,$u->id,(string)Str::uuid(),'manual');
        $rubric=$r->only(['rules','criteria','grading_policy']); $rubric['criteria'][0]['max_score']='1.00'; $rubric['criteria'][]=array_replace($rubric['criteria'][0],['criterion_id'=>'C2']);
        $r=$rw->save($q,$r->id,$r->revision,$rubric,false,$u->id); $rw->save($q,$r->id,$r->revision,[],true,$u->id);
        $payload=['content_type'=>'text','answer_text'=>'1 + 1 = 2 DO_NOT_EXPORT_TEXT'];
        if($photo) { \Illuminate\Support\Facades\Storage::fake('answers'); $payload=['content_type'=>'photo','photo'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('own.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='))]; }
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),$payload);
        if($photo) {
            $tw=app(\App\Services\TranscriptionWorkflow::class); $r=$answer->latestRevision;
            $tw->save($a,$u->id,$answer->id,$r->id,(string)Str::uuid(),0,$rawAi?'ai':'draft','1 + 1 = 3','Synthetic raw draft',['uncertainties'=>[],'metadata'=>[]]);
            $tw->save($a,$u->id,$answer->id,$r->id,(string)Str::uuid(),1,'draft','1 + 1 = 2','Synthetic correction');
            $tw->save($a,$u->id,$answer->id,$r->id,(string)Str::uuid(),2,'confirm','1 + 1 = 2','Synthetic confirmation',[],true);
        }
        $this->actingAs($u); $data=app(EvaluationData::class);
        $d=$data->dataset($a,$u->id,(string)Str::uuid(),'Synthetic protocol','synthetic','Test implementation only.','Synthetic metrics, not empirical accuracy.','0',$strict);
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

    private function human(array $f,string $kind,array $scores=[0,1],int $expected=0,?string $code=null,?string $key=null) {
        return app(\App\Services\ResearchQuality::class)->save($f[1],$f[0]->id,$f[4]->id,$f[5]->id,$kind,$key ?? (string)Str::uuid(),$expected,['rater_code'=>$code ?? strtoupper($kind),'criteria'=>[['criterion_id'=>'C1','score'=>$scores[0]],['criterion_id'=>'C2','score'=>$scores[1]]],'reason'=>'PRIVATE_'.$kind.'_REASON'],true);
    }
    private function adjudicate(array $f,int $expected=0) {
        $q=app(\App\Services\ResearchQuality::class); return $q->save($f[1],$f[0]->id,$f[4]->id,$f[5]->id,'adjudication',(string)Str::uuid(),$expected,
            ['a_id'=>$q->latest($f[5]->id,'human_a')->id,'b_id'=>$q->latest($f[5]->id,'human_b')->id,'reference_version'=>$f[5]->references()->first()->version,'criteria'=>[['criterion_id'=>'C1','score'=>0],['criterion_id'=>'C2','score'=>1]],'reason'=>'PRIVATE_ADJUDICATION_REASON'],true);
    }
    private function prepare(array $f): void { $this->human($f,'human_a',[1,0]); $this->human($f,'human_b',[0,1]); $this->adjudicate($f); }
    private function freeze(array $f) { return app(EvaluationData::class)->freeze($f[1],$f[0]->id,$f[4]->id,true); }
    private function report($d): array { return app(\App\Services\ResearchQualityReport::class)->build($d); }
    private function ocr(array $f): void { app(\App\Services\ResearchQuality::class)->save($f[1],$f[0]->id,$f[4]->id,$f[5]->id,'ocr',(string)Str::uuid(),0,['text'=>'1 + 1 = 2','reason'=>'PRIVATE_OCR_REASON'],true); }
    public function test_two_humans_and_adjudication_are_required_and_equal_totals_keep_criterion_disagreements(): void {
        $f=$this->fixture(); $this->fails(fn()=>$this->freeze($f)); $this->prepare($f); $d=$this->freeze($f); $r=$this->report($d);
        $this->assertSame(1,$r['human_pairs']); $this->assertSame(1,$r['adjudicated']); $this->assertSame(100.0,$r['total_exact_percent']); $this->assertSame(0.0,$r['criterion_exact_percent']); $this->assertSame(2,$r['rows'][0]['criterion_disagreements']); $this->assertSame(0.0,$r['mae']);
        $this->assertSame(2,$f[5]->references()->first()->version); $this->assertArrayHasKey('adjudication',$d->manifest['cases'][0]['quality']); Http::assertNothingSent();
    }
    public function test_rater_codes_scores_replay_stale_labels_and_frozen_records_are_guarded(): void {
        $f=$this->fixture(); $key=(string)Str::uuid(); $a=$this->human($f,'human_a',[1,0],0,'RATER_ONE',$key);
        $this->assertSame($a->id,$this->human($f,'human_a',[1,0],0,'RATER_ONE',$key)->id);
        $this->fails(fn()=>$this->human($f,'human_a',[0,1],0,'RATER_ONE',$key)); $this->fails(fn()=>$this->human($f,'human_b',[0,1],0,'rater_one'));
        $this->fails(fn()=>$this->human($f,'human_b',[2,0],0,'OTHER')); $this->human($f,'human_b'); $this->adjudicate($f);
        $this->human($f,'human_a',[0,1],1,'RATER_ONE'); $this->fails(fn()=>$this->freeze($f)); $this->adjudicate($f,1); $this->freeze($f);
        $this->fails(fn()=>$this->human($f,'human_a',[1,0],2,'RATER_ONE'));
        try { $a->update(['rater_code'=>'NEW']); $this->fail(); } catch(\LogicException) { $this->assertTrue(true); }
    }
    public function test_raw_ai_and_confirmed_photo_transcripts_are_measured_separately_against_human_reference(): void {
        $f=$this->fixture(false,false,true); $this->prepare($f); $this->ocr($f); $d=$this->freeze($f); $r=$this->report($d);
        $this->assertSame(1,$r['photo_cases']); $this->assertSame(1,$r['ocr_labeled']); $this->assertSame(11.1111,$r['ai']['cer_percent']); $this->assertSame(0.0,$r['confirmed']['cer_percent']);
        $this->assertSame(1,$r['ai']['edits']); $this->assertSame(9,$r['ai']['characters']);
        app(\App\Services\TranscriptionWorkflow::class)->save($f[1],$f[0]->id,$f[2]->id,$f[5]->answer_revision_id,(string)Str::uuid(),3,'draft','Another operational text','Later source');
        $this->assertSame($r['ai'],$this->report($d)['ai']); $this->assertSame($r['confirmed'],$this->report($d)['confirmed']); Http::assertNothingSent();
        DB::table('answer_transcriptions')->where('answer_revision_id',$f[5]->answer_revision_id)->where('origin','ai')->update(['transcript_text'=>'Corrupt source']);
        $this->assertSame(1,$this->report($d)['invalid']);
    }
    public function test_missing_ai_transcription_is_not_reported_as_zero_error(): void {
        $f=$this->fixture(false,false,true,true,false); $this->prepare($f); $this->ocr($f); $r=$this->report($this->freeze($f));
        $this->assertSame(1,$r['ai']['missing']); $this->assertSame(0,$r['ai']['computed']); $this->assertNull($r['ai']['cer_percent']); $this->assertSame(0.0,$r['confirmed']['cer_percent']);
    }
    public function test_unicode_cer_insertions_and_computation_limits_have_explicit_semantics(): void {
        $q=app(\App\Services\ResearchQuality::class);
        $this->assertSame(0,$q->cer('½','½')['edits']); $this->assertSame(1,$q->cer('3','½')['characters']); $this->assertSame(100.0,$q->cer('3','½')['cer_percent']);
        $this->assertSame(300.0,$q->cer('1/2','½')['cer_percent']); $this->assertSame(100.0,$q->cer('','ab')['cer_percent']); $this->assertSame('missing',$q->cer(null,'a')['status']);
        $limited=$q->cer(str_repeat('a',501),str_repeat('a',500)); $this->assertSame('limited',$limited['status']); $this->assertNull($limited['cer_percent']);
    }
    public function test_corrupt_pinned_annotation_holds_quality_metrics_and_baseline_before_provider_call(): void {
        $f=$this->fixture(true); $this->prepare($f); $d=$this->freeze($f); $this->fake([]); $e=$this->start($f); $s=$e->samples()->first();
        $a=app(\App\Services\ResearchQuality::class)->latest($f[5]->id,'human_a'); DB::table('research_annotations')->where('id',$a->id)->update(['content_hash'=>str_repeat('0',64)]);
        $this->step($s); $this->assertSame('failed',$s->status); Http::assertNothingSent();
        $r=$this->report($d); $this->assertSame(1,$r['invalid']); $this->assertSame(0,$r['human_pairs']); $this->assertNull($r['mae']);
    }
    public function test_annotation_pages_are_blind_where_required_owner_only_and_csv_contains_no_raw_reference_text(): void {
        $f=$this->fixture(false,false,true); $this->prepare($f); $base=$this->base($f); $form=$base.'/cases/'.$f[5]->id.'/quality/';
        $this->get($form.'ocr')->assertOk()->assertDontSee('1 + 1 = 2')->assertDontSee('1 + 1 = 3'); $this->ocr($f);
        $this->get($form.'ocr')->assertOk()->assertDontSee('1 + 1 = 3')->assertDontSee('1 + 1 = 2 DO_NOT_EXPORT_TEXT');
        $this->get($form.'human_a')->assertOk()->assertDontSee('PRIVATE_human_b_REASON')->assertDontSee('MULTI_SECRET_REASON'); $this->get($form.'adjudication')->assertOk()->assertSee('PRIVATE_human_b_REASON');
        $this->getJson($base.'/quality/export')->assertUnprocessable(); $this->freeze($f);
        $this->get($base.'/quality')->assertOk()->assertSee('Kesepakatan sebelum adjudikasi')->assertSee('11.1111');
        $csv=$this->get($base.'/quality/export')->assertOk()->streamedContent(); $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
        foreach(['1 + 1 = 2','SECRET_EVAL','PRIVATE_OCR_REASON','HUMAN_A','HUMAN_B'] as $secret) $this->assertStringNotContainsString($secret,$csv);
        foreach(['teacher','admin','student'] as $role) { $this->actingAs($this->user($role)); $this->get($form.'human_a')->assertForbidden(); $this->get($base.'/quality')->assertForbidden(); $this->get($base.'/quality/export')->assertForbidden(); }
    }
    public function test_legacy_single_reference_dataset_remains_usable_and_cannot_gain_post_freeze_annotations(): void {
        $f=$this->fixture(false,false,false,false); $d=$this->freeze($f); $r=$this->report($d); $this->assertSame(0,$r['invalid']); $this->assertSame(0,$r['human_pairs']); $this->assertNull($r['mae']);
        $this->fails(fn()=>$this->human($f,'human_a')); $this->assertSame('human_independent_attested',$d->protocol['reference_policy']);
    }
    public function test_adjudication_receipt_failure_rolls_back_new_reference(): void {
        $f=$this->fixture(); $this->human($f,'human_a'); $this->human($f,'human_b');
        \Illuminate\Support\Facades\Event::listen('eloquent.creating: App\\Models\\ResearchAnnotation',fn()=>throw new \RuntimeException('Synthetic audit failure'));
        try { $this->adjudicate($f); $this->fail('Expected failure'); } catch(\RuntimeException) { $this->assertSame(1,$f[5]->references()->count()); }
        finally { \Illuminate\Support\Facades\Event::forget('eloquent.creating: App\\Models\\ResearchAnnotation'); }
    }
    public function test_http_annotation_requires_attestation_preserves_reference_whitespace_and_replays(): void {
        $f=$this->fixture(false,false,true); $url=$this->base($f).'/cases/'.$f[5]->id.'/quality/ocr';
        $body=['creation_key'=>(string)Str::uuid(),'expected_version'=>0,'reference_text'=>"  1 + 1 = 2  ",'reason'=>'Independent literal whitespace.'];
        $this->postJson($url,$body)->assertUnprocessable(); $body['independent']=1;
        $this->post($url,$body)->assertSessionDoesntHaveErrors(); $this->post($url,$body)->assertSessionDoesntHaveErrors();
        $a=app(\App\Services\ResearchQuality::class)->latest($f[5]->id,'ocr'); $this->assertSame("  1 + 1 = 2  ",$a->data['text']); $this->assertSame(1,\App\Models\ResearchAnnotation::count());
    }
    public function test_http_dataset_can_preregister_two_humans_and_valid_baseline_keeps_reference_context_isolated(): void {
        $f=$this->fixture(true); $this->post('/assessments/'.$f[1]->id.'/evaluations',['creation_key'=>(string)Str::uuid(),'name'=>'HTTP strict','data_kind'=>'synthetic','goal'=>'Software only','target'=>'No accuracy claim','tolerance'=>'0','two_humans'=>1])->assertSessionDoesntHaveErrors();
        $this->assertSame('two_humans_adjudicated',EvalDataset::latest('id')->first()->protocol['reference_policy']);
        $this->prepare($f); $this->freeze($f); $this->fake([$this->marker()]); $e=$this->start($f); $s=$e->samples()->first(); $this->step($s); $this->assertSame('succeeded',$s->status); Http::assertSentCount(1);
        Http::assertSent(function($request) { $data=json_decode($request['input'],true)['untrusted_data']; return array_keys($data)===['source','rubric','answer_text'] && !str_contains($request['input'],'PRIVATE_ADJUDICATION_REASON') && !str_contains($request['input'],'HUMAN_A'); });
        $this->assertSame(1,app(EvaluationMetrics::class)->report($e)['paired']);
    }
}
