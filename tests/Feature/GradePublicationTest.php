<?php
namespace Tests\Feature;
use App\Models\{GradeReview,GradingRun,User};
use App\Services\{AnswerIntake,GradeReviewWorkflow,GradingOrchestrator,GradingStages,RubricWorkflow,TranscriptionWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GradePublicationTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role='teacher'): User {
        $u=new User(['name'=>'Synthetic '.$role,'email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role=$role; $u->is_active=true; $u->save(); return $u;
    }
    private function fixture(bool $photo=false): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'MOCK_ONLY','ai.max_output_tokens'=>4000]);
        $u=$this->user(); $c=$u->classrooms()->create(['name'=>'Synthetic','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'P001']);
        $a=$u->assessments()->make(['title'=>'Synthetic review']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1+1?','answer_key'=>'2','scoring_guidelines'=>'One point for steps, one for correct result.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $rw=app(RubricWorkflow::class); $r=$rw->create($q,$u->id,(string)Str::uuid(),'manual');
        $data=$r->only(['rules','criteria','grading_policy']); $data['criteria'][0]['max_score']='1.00'; $data['criteria'][]=array_replace($data['criteria'][0],['criterion_id'=>'C2']);
        $r=$rw->save($q,$r->id,$r->revision,$data,false,$u->id); $r=$rw->save($q,$r->id,$r->revision,[],true,$u->id);
        $payload=['content_type'=>'text','answer_text'=>'1 + 1 = 2 <script>bad()</script>'];
        if($photo) { Storage::fake('answers'); $payload=['content_type'=>'photo','photo'=>UploadedFile::fake()->createWithContent('answer.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='))]; }
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),$payload);
        if($photo) {
            $tw=app(TranscriptionWorkflow::class); $revision=$answer->latestRevision;
            $tw->save($a,$u->id,$answer->id,$revision->id,(string)Str::uuid(),0,'draft','1 + 1 = 2','Synthetic');
            $tw->save($a,$u->id,$answer->id,$revision->id,(string)Str::uuid(),1,'confirm','1 + 1 = 2','Checked',[],true);
        }
        $run=$this->completed($u,$a,$answer);
        $this->actingAs($u); return [$u,$a,$answer,$q,$run];
    }
    private function completed($u,$a,$answer): GradingRun {
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        $marker=['criteria'=>array_map(fn($id)=>['criterion_id'=>$id,'score'=>1,'evidence'=>['1 + 1 = 2'],'missing_evidence'=>false,'reason'=>'Matches rule.','rule_ids'=>['R1']],['C1','C2'])];
        $sequence=Http::sequence(); foreach([$marker,$marker,['label'=>'High','reason'=>'Consistent.','concerns'=>[]]] as $data) $sequence->push(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]]);
        Http::fake(['ai.dinoiki.com/*'=>$sequence]);
        $w=app(GradingOrchestrator::class); $run=$w->start($a,$u->id,$answer->id,(string)Str::uuid());
        foreach(GradingStages::PLAN as $stage) { $run->refresh(); $w->process($run->id,$run->generation); }
        $this->assertSame('completed',$run->fresh()->status); return $run->fresh();
    }
    private function rows(string $score='1.00',string $reason=''): array { return [['criterion_id'=>'C1','score'=>$score,'reason'=>$reason],['criterion_id'=>'C2','score'=>'1.00','reason'=>'']]; }
    private function draft(array $f,int $expected=0,array $rows=[],string $reason='Reviewed source and evidence.'): GradeReview {
        return app(GradeReviewWorkflow::class)->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),$expected,'draft',$rows ?: $this->rows(),$reason);
    }
    private function approve(array $f,int $expected): GradeReview { return app(GradeReviewWorkflow::class)->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),$expected,'approve',[],'',true); }
    private function fails(callable $fn): void { try { $fn(); $this->fail('Expected validation error'); } catch(ValidationException) { $this->assertTrue(true); } }
    private function url(array $f): string { return '/assessments/'.$f[1]->id.'/grading-runs/'.$f[4]->id.'/review'; }

    private function publish(array $f,int $review,int $expected=0,string $message='Untuk siswa'): \App\Models\GradePublication {
        return app(\App\Services\GradePublishing::class)->save($f[1],$f[0]->id,$f[2]->id,(string)Str::uuid(),$expected,'publish',$review,$message,true);
    }
    public function test_publication_is_explicit_scoped_and_revocable_without_disclosing_private_material(): void {
        $f=$this->fixture(); $this->draft($f); $review=$this->approve($f,1); $w=app(\App\Services\GradePublishing::class);
        $this->assertNull($w->visible($f[2])); $p=$this->publish($f,$review->id,0,'Pesan <script>bad()</script>');
        $this->assertSame('2.00',$w->visible($f[2])['total_score']);
        $student=$this->user('student'); $student->studentProfile()->create(['participant_id'=>$f[2]->participant_id,'created_by'=>$f[0]->id]);
        $this->actingAs($student)->get('/student')->assertOk()->assertSee('Nilai diterbitkan')->assertSee('2.00')->assertSee('Pesan &lt;script&gt;',false)->assertDontSee('Reviewed source and evidence.')->assertDontSee('Matches rule.');
        $this->get('/assessments/'.$f[1]->id.'/publications')->assertForbidden();
        $w->save($f[1],$f[0]->id,$f[2]->id,(string)Str::uuid(),1,'revoke',null,'',true);
        $this->get('/student')->assertOk()->assertDontSee('Nilai diterbitkan'); $this->assertSame(2,\App\Models\GradePublication::count()); Http::assertSentCount(3);
    }
    public function test_publish_rejects_draft_stale_corrupt_scores_and_newer_draft_hides_previous_publication(): void {
        $f=$this->fixture(); $draft=$this->draft($f); $this->fails(fn()=>$this->publish($f,$draft->id)); $r=$this->approve($f,1); $this->publish($f,$r->id);
        $this->draft($f,2); $this->assertNull(app(\App\Services\GradePublishing::class)->visible($f[2])); $this->fails(fn()=>$this->publish($f,$r->id,1));
        $r=$this->approve($f,3); DB::table('grade_reviews')->where('id',$r->id)->update(['total_score'=>'0.00']); $this->fails(fn()=>$this->publish($f,$r->id,1));
    }
    public function test_source_revisions_and_photo_drafts_hide_publications(): void {
        $f=$this->fixture(true); $this->draft($f); $r=$this->approve($f,1); $this->publish($f,$r->id);
        $this->assertNotNull(app(\App\Services\GradePublishing::class)->visible($f[2]));
        $revision=$f[2]->latestRevision; app(TranscriptionWorkflow::class)->save($f[1],$f[0]->id,$f[2]->id,$revision->id,(string)Str::uuid(),2,'draft','1 + 1 = 3','Correction');
        $this->assertNull(app(\App\Services\GradePublishing::class)->visible($f[2])); $this->fails(fn()=>$this->publish($f,$r->id,1));
    }
    public function test_http_replay_conflicts_confirmation_and_foreign_roles(): void {
        $f=$this->fixture(); $this->draft($f); $r=$this->approve($f,1); $base='/assessments/'.$f[1]->id.'/publications';
        $this->get($base)->assertOk()->assertSee('Keputusan disahkan');
        $v=['creation_key'=>(string)Str::uuid(),'expected_version'=>0,'action'=>'publish','review_id'=>$r->id,'message'=>'Visible'];
        $this->post($base.'/'.$f[2]->id,$v)->assertSessionHasErrors('confirmation'); $v['confirmation']=1;
        $this->post($base.'/'.$f[2]->id,$v)->assertSessionDoesntHaveErrors(); $this->post($base.'/'.$f[2]->id,$v)->assertSessionDoesntHaveErrors();
        $v['message']='Conflict'; $this->post($base.'/'.$f[2]->id,$v)->assertSessionHasErrors('publication');
        $v['creation_key']=(string)Str::uuid(); $this->post($base.'/'.$f[2]->id,$v)->assertSessionHasErrors('publication');
        foreach(['teacher','student','admin'] as $role) { $this->actingAs($this->user($role)); $this->get($base)->assertForbidden(); $this->post($base.'/'.$f[2]->id,$v)->assertForbidden(); }
        $p=\App\Models\GradePublication::first(); try { $p->update(['message'=>'changed']); $this->fail(); } catch(\LogicException) { $this->assertTrue(true); }
        DB::table('grade_publications')->where('id',$p->id)->update(['message'=>'tampered']); $this->assertNull(app(\App\Services\GradePublishing::class)->visible($f[2]));
    }
}
