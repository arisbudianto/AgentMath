<?php

namespace Tests\Feature;

use App\Models\{Question, RubricVersion, User};
use App\Services\{RubricWorkflow, RubricValidator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RubricTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'teacher'): array
    {
        $u = new User(['name'=>'Teacher','email'=>uniqid().'@example.test','password'=>'TestPassword123']);
        $u->role=$role; $u->is_active=true; $u->save();
        $a=$u->assessments()->create(['title'=>'Fractions','instructions'=>'Show steps.']);
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1/2 + 1/4?','answer_key'=>'3/4','scoring_guidelines'=>'Award 2.50 for correct answer and steps.','max_score'=>'2.50']);
        $q->setRelation('assessment',$a);
        return [$u,$a,$q];
    }

    private function draft(Question $q, User $u): RubricVersion
    {
        return app(RubricWorkflow::class)->create($q,$u->id,(string) Str::uuid(),'manual');
    }

    private function url(Question $q, ?RubricVersion $r = null): string
    {
        return '/assessments/'.$q->assessment_id.'/questions/'.$q->id.'/rubrics'.($r?'/'.$r->id:'');
    }

    private function body(RubricVersion $r): array
    {
        return ['revision'=>$r->revision]+$r->only(['rules','criteria','grading_policy']);
    }

    private function aiResponse(array $data): array
    {
        return ['id'=>'response-test','status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($data)]]]],'usage'=>['input_tokens'=>100,'output_tokens'=>150]];
    }

    private function enableAi(): void
    {
        config(['ai.enabled'=>true,'ai.provider'=>'openai','ai.model'=>'configured-model','ai.api_key'=>'NEVER-EXPOSE-KEY']);
        Http::preventStrayRequests();
    }

    public function test_manual_workflow_approval_new_version_and_idempotency(): void
    {
        [$u,$a,$q]=$this->fixture(); $key=(string) Str::uuid();
        $this->actingAs($u)->get($this->url($q))->assertOk()->assertSee('Buat draf manual');
        $this->post($this->url($q),['creation_key'=>$key,'origin'=>'manual'])->assertRedirect();
        $r=$q->rubricVersions()->firstOrFail();
        $this->assertSame('draft',$r->status);
        $this->post($this->url($q),['creation_key'=>$key,'origin'=>'manual'])->assertRedirect();
        $this->assertSame(1,RubricVersion::count());
        $body=$this->body($r); $body['grading_policy']='Apply R1 and require evidence.';
        $this->patch($this->url($q,$r),$body)->assertSessionDoesntHaveErrors();
        $r->refresh();
        $this->post($this->url($q,$r).'/approve',['revision'=>$r->revision,'confirmation'=>1])->assertRedirect();
        $this->assertSame('approved',$r->fresh()->status);
        $this->assertSame($u->id,$r->fresh()->approved_by);
        $this->get($this->url($q,$r))->assertOk()->assertSee('Disahkan')->assertDontSee('Simpan draf');
        $this->get($this->url($q))->assertOk()->assertSee('Rubrik berlaku: versi 1');
        $approvedAt=$r->fresh()->approved_at;
        $this->post($this->url($q,$r).'/approve',['revision'=>1,'confirmation'=>1])->assertRedirect();
        $this->assertEquals($approvedAt,$r->fresh()->approved_at);
        $this->patch($this->url($q,$r),$this->body($r->fresh()))->assertSessionHasErrors('rubric');
        $this->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'revision','copy_id'=>$r->id])->assertRedirect();
        $copy=$q->rubricVersions()->firstOrFail();
        $this->assertSame(2,$copy->version); $this->assertSame('draft',$copy->status);
        $this->assertSame($r->criteria,$copy->criteria);
        $this->assertSame($r->id,$copy->parent_id);
        $this->assertSame($r->id,$q->fresh()->currentApprovedRubric()?->id);
    }

    public function test_bad_citations_rule_references_and_score_totals_are_rejected(): void
    {
        [$u,$a,$q]=$this->fixture(); $r=$this->draft($q,$u); $this->actingAs($u);
        $body=$this->body($r); $body['rules'][0]['quote']='Fabricated quote';
        $this->patch($this->url($q,$r),$body)->assertSessionHasErrors('rubric');
        $body=$this->body($r); $body['criteria'][0]['rule_ids']=['R99'];
        $this->patch($this->url($q,$r),$body)->assertSessionHasErrors('rubric');
        foreach (['0','2.51','2.499','-1','1e300'] as $score) {
            $body=$this->body($r); $body['criteria'][0]['max_score']=$score;
            $this->patch($this->url($q,$r),$body)->assertSessionHasErrors();
        }
        $body=$this->body($r); $body['rules'][]=$body['rules'][0];
        $this->patch($this->url($q,$r),$body)->assertSessionHasErrors('rules.0.rule_id');
        $this->assertSame(1,$r->fresh()->revision);
        $this->assertSame('draft',$r->fresh()->status);
    }

    public function test_fractional_criteria_sum_uses_exact_hundredths_and_form_references(): void
    {
        [$u,$a,$q]=$this->fixture(); $r=$this->draft($q,$u); $body=$this->body($r);
        $body['criteria'][0]['max_score']='0.10'; $body['criteria'][0]['rule_ids']='R1';
        $body['criteria'][]=array_replace($r->criteria[0],['criterion_id'=>'C2','max_score'=>'2.40','rule_ids'=>'R1']);
        $this->actingAs($u)->patch($this->url($q,$r),$body)->assertSessionDoesntHaveErrors();
        $this->assertSame('0.10',$r->fresh()->criteria[0]['max_score']);
        $this->assertSame(['R1'],$r->fresh()->criteria[1]['rule_ids']);
    }

    public function test_source_changes_block_approval_but_preserve_history(): void
    {
        [$u,$a,$q]=$this->fixture(); $r=$this->draft($q,$u);
        $a->instructions='New instructions'; $a->save();
        $this->actingAs($u)->post($this->url($q,$r).'/approve',['revision'=>1,'confirmation'=>1])->assertSessionHasErrors('rubric');
        $this->get($this->url($q,$r))->assertOk()->assertSee('Dasar soal atau pedoman sudah berubah');
        $this->assertSame('Show steps.',$r->fresh()->source_snapshot['instructions']);
        $q=$q->fresh(); $new=$this->draft($q,$u);
        $this->assertSame('New instructions',$new->source_snapshot['instructions']);
        app(RubricWorkflow::class)->save($q,$new->id,1,[],true,$u->id);
        $q->max_score='3.00'; $q->save();
        $this->assertNull($q->fresh()->currentApprovedRubric());
        $this->assertSame('approved',$new->fresh()->status);
    }

    public function test_stale_tab_and_missing_confirmation_do_not_approve(): void
    {
        [$u,$a,$q]=$this->fixture(); $r=$this->draft($q,$u);
        $this->actingAs($u)->patch($this->url($q,$r),$this->body($r))->assertRedirect();
        $this->post($this->url($q,$r).'/approve',['revision'=>1,'confirmation'=>1])->assertSessionHasErrors('rubric');
        $this->post($this->url($q,$r).'/approve',['revision'=>2])->assertSessionHasErrors('confirmation');
        $this->assertSame('draft',$r->fresh()->status);
    }

    public function test_nested_ownership_admin_guest_and_id_tampering_are_denied(): void
    {
        [$a,$aa,$qa]=$this->fixture(); [$b,$ab,$qb]=$this->fixture(); $r=$this->draft($qb,$b);
        $this->get($this->url($qb))->assertRedirect('/login');
        $this->actingAs($a)->get($this->url($qb))->assertForbidden();
        $this->post($this->url($qb),['creation_key'=>(string) Str::uuid(),'origin'=>'manual'])->assertForbidden();
        $this->patch($this->url($qb,$r),$this->body($r))->assertForbidden();
        $this->post($this->url($qb,$r).'/approve',['revision'=>1,'confirmation'=>1])->assertForbidden();
        $this->get('/assessments/'.$aa->id.'/questions/'.$qb->id.'/rubrics')->assertNotFound();
        $this->get($this->url($qa).'/'.$r->id)->assertNotFound();
        [$admin]=$this->fixture('admin');
        $this->actingAs($admin)->get($this->url($qb,$r))->assertForbidden();
        $this->assertSame('draft',$r->fresh()->status);
    }

    public function test_ai_disabled_makes_no_request_and_manual_still_works(): void
    {
        [$u,$a,$q]=$this->fixture(); Http::fake(); config(['ai.enabled'=>false]);
        $this->actingAs($u)->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertSessionHasErrors('rubric');
        Http::assertNothingSent();
        $this->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'manual'])->assertRedirect();
        $this->assertSame(1,RubricVersion::count());
    }

    public function test_two_ai_roles_receive_no_account_or_class_identifiers_and_never_auto_approve(): void
    {
        [$u,$a,$q]=$this->fixture(); $template=$this->draft($q,$u); $this->enableAi();
        Http::fake(['api.openai.com/*'=>Http::sequence()->push($this->aiResponse(['rules'=>$template->rules]))->push($this->aiResponse($template->only(['criteria','grading_policy'])))]);
        $this->actingAs($u)->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertRedirect();
        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($u) {
            return $request['store'] === false && $request['text']['format']['strict'] === true && !str_contains($request['input'],$u->email) && !str_contains($request['input'],'owner_id') && !str_contains($request['input'],'assessment_id');
        });
        $r=$q->rubricVersions()->firstOrFail();
        $this->assertSame('ai',$r->origin); $this->assertSame('draft',$r->status);
        $this->assertSame(100,$r->ai_metadata['a1']['usage']['input_tokens']);
        $this->get($this->url($q,$r))->assertOk()->assertDontSee('NEVER-EXPOSE-KEY');
    }

    public function test_ai_bad_quote_stops_before_second_request_and_saves_no_version(): void
    {
        [$u,$a,$q]=$this->fixture(); $template=$this->draft($q,$u); $rules=$template->rules; $rules[0]['quote']='not in source'; $this->enableAi();
        Http::fake(['api.openai.com/*'=>Http::response($this->aiResponse(['rules'=>$rules]))]);
        $this->actingAs($u)->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertSessionHasErrors('rubric');
        Http::assertSentCount(1); $this->assertSame(1,RubricVersion::count());
    }

    public function test_ai_failure_refusal_and_incomplete_response_never_leak_errors_or_create_rubrics(): void
    {
        [$u,$a,$q]=$this->fixture(); $this->enableAi();
        foreach ([['status'=>'incomplete'],['status'=>'completed','output'=>[['content'=>[['type'=>'refusal','refusal'=>'NEVER-EXPOSE-KEY']]]]]] as $failure) {
            Http::fake(['api.openai.com/*'=>Http::response($failure)]);
            $this->actingAs($u)->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertSessionHasErrors('rubric');
            $this->get($this->url($q))->assertOk()->assertDontSee('NEVER-EXPOSE-KEY');
        }
        Http::fake(['api.openai.com/*'=>Http::response(['error'=>'NEVER-EXPOSE-KEY'],401)]);
        $this->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertSessionHasErrors('rubric');
        $this->assertSame(0,RubricVersion::count());
    }

    public function test_malformed_form_data_can_be_redisplayed_without_crashing(): void
    {
        [$u,$a,$q]=$this->fixture(); $r=$this->draft($q,$u);
        $this->actingAs($u)->from($this->url($q,$r))->patch($this->url($q,$r),['revision'=>1,'rules'=>[['quote'=>['x']]],'criteria'=>['bad'],'grading_policy'=>'Policy'])->assertSessionHasErrors();
        $this->get($this->url($q,$r))->assertOk();
    }

    public function test_approved_model_cannot_be_overwritten(): void
    {
        [$u,$a,$q]=$this->fixture(); $r=$this->draft($q,$u);
        $r=app(RubricWorkflow::class)->save($q,$r->id,1,[],true,$u->id);
        $this->expectException(\LogicException::class);
        $r->update(['grading_policy'=>'Overwrite history']);
    }

    public function test_invalid_ai_total_and_source_change_during_generation_save_no_version(): void
    {
        [$u,$a,$q]=$this->fixture(); $template=$this->draft($q,$u); $this->enableAi();
        $data=$template->only(['criteria','grading_policy']); $data['criteria'][0]['max_score']=3;
        Http::fake(['api.openai.com/*'=>Http::sequence()->push($this->aiResponse(['rules'=>$template->rules]))->push($this->aiResponse($data))]);
        $this->actingAs($u)->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertSessionHasErrors('rubric');
        $calls=0;
        Http::fake(function () use (&$calls,$template,$a) {
            if (++$calls === 1) return Http::response($this->aiResponse(['rules'=>$template->rules]));
            $a->update(['instructions'=>'Changed while AI was running']);
            return Http::response($this->aiResponse($template->only(['criteria','grading_policy'])));
        });
        $this->post($this->url($q),['creation_key'=>(string) Str::uuid(),'origin'=>'ai'])->assertSessionHasErrors('rubric');
        $this->assertSame(1,RubricVersion::count());
    }
}
