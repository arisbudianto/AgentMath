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

class GradeReviewTest extends TestCase {
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

    public function test_review_draft_correction_then_explicit_approval_with_immutable_history(): void {
        $f=$this->fixture(); $this->get($this->url($f))->assertOk()->assertSee('Pemeriksaan dan keputusan guru')->assertSee('Marker A')->assertSee('Marker B')->assertSee('&lt;script&gt;',false);
        $this->fails(fn()=>$this->approve($f,0));
        $draft=$this->draft($f,0,$this->rows('0.50','Partial reasoning only.'));
        $this->assertSame('1.50',$draft->total_score); $this->assertSame('draft',$draft->status); $this->assertNull($draft->approved_at);
        $w=app(GradeReviewWorkflow::class); $this->fails(fn()=>$w->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),1,'approve'));
        $this->fails(fn()=>$w->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),1,'approve',$this->rows(),'Changed at approval',true));
        $approved=$this->approve($f,1);
        $this->assertSame('approved',$approved->status); $this->assertSame($draft->criteria,$approved->criteria); $this->assertSame($draft->id,$approved->parent_id);
        $this->assertNotNull($approved->approved_at); $this->assertSame('2.00',$f[4]->attempts()->where('stage','confidence')->first()->output['data']['total_score']);
        foreach(['update','delete'] as $method) { try { $method==='update'?$approved->update(['total_score'=>'0.00']):$approved->delete(); $this->fail('Mutable decision'); } catch(\LogicException) { $this->assertTrue(true); } }
        $this->get('/assessments/'.$f[1]->id.'/answers/'.$f[2]->id)->assertOk()->assertSee('Disahkan untuk dasar terkini')->assertSee('1.50');
    }
    public function test_http_confirmation_replay_and_key_collision(): void {
        $f=$this->fixture(); $url=$this->url($f);
        $body=['creation_key'=>(string)Str::uuid(),'expected_version'=>0,'action'=>'draft','criteria'=>$this->rows(),'reason'=>'Teacher checked.'];
        $this->post($url,$body)->assertSessionDoesntHaveErrors(); $this->post($url,$body)->assertSessionDoesntHaveErrors(); $this->assertSame(1,GradeReview::count());
        $changed=$body; $changed['reason']='Changed'; $this->post($url,$changed)->assertSessionHasErrors('review');
        $approve=['creation_key'=>(string)Str::uuid(),'expected_version'=>1,'action'=>'approve'];
        $this->post($url,$approve)->assertSessionHasErrors('confirmation');
        $approve['confirmation']=1;
        $this->post($url,$approve)->assertSessionDoesntHaveErrors(); $this->post($url,$approve)->assertSessionDoesntHaveErrors();
        $this->assertSame(2,GradeReview::count()); $this->assertSame(1,GradeReview::where('status','approved')->count());
        Http::assertSentCount(3);
    }
    public function test_scores_ids_reason_and_unknown_fields_are_rejected(): void {
        $f=$this->fixture();
        foreach(['-1','1.01','0.001','abc','1e0'] as $score) $this->fails(fn()=>$this->draft($f,0,$this->rows($score,'Correction')));
        $this->fails(fn()=>$this->draft($f,0,$this->rows('0.50',' ')));
        $this->fails(fn()=>$this->draft($f,0,$this->rows(),' '));
        $rows=$this->rows(); $rows[1]['criterion_id']='C9'; $this->fails(fn()=>$this->draft($f,0,$rows));
        $rows=$this->rows(); $rows[1]['criterion_id']='C1'; $this->fails(fn()=>$this->draft($f,0,$rows));
        $rows=$this->rows(); $rows[0]['extra']=true; $this->fails(fn()=>$this->draft($f,0,$rows));
        $this->fails(fn()=>$this->draft($f,0,[$this->rows()[0]])); $this->assertSame(0,GradeReview::count());
        $this->assertSame('1.00',$this->draft($f,0,$this->rows('0','No matching step.'))->total_score);
    }
    public function test_stale_tabs_approval_revisions_and_latest_approval_not_overwritten_by_draft(): void {
        $f=$this->fixture(); $this->draft($f); $approved=$this->approve($f,1);
        $this->fails(fn()=>$this->draft($f,1));
        $draft=$this->draft($f,2,$this->rows('0.25','Corrected partial score.'),'Rechecked.');
        $this->assertSame('2.00',GradeReview::where('status','approved')->first()->total_score);
        $this->fails(fn()=>$this->approve($f,2));
        $new=$this->approve($f,3); $this->assertSame($approved->id,$new->supersedes_approval_id);
        $this->assertSame('1.25',$new->total_score); $this->assertSame(4,GradeReview::count());
    }
    public function test_changed_answer_source_rubric_and_photo_transcription_block_approval(): void {
        $f=$this->fixture(); $this->draft($f);
        $f[3]->update(['prompt'=>'Changed']); $this->fails(fn()=>$this->approve($f,1));
        $this->get($this->url($f))->assertOk()->assertSee('Halaman ini hanya riwayat')->assertDontSee('Sahkan nilai dari draf ini');
        $f[3]->update(['prompt'=>'1+1?']);
        app(AnswerIntake::class)->revise($f[1],$f[0]->id,$f[2]->id,1,'Revised',['content_type'=>'text','answer_text'=>'Two']);
        $this->fails(fn()=>$this->approve($f,1)); $this->assertSame(0,GradeReview::where('status','approved')->count());
    }
    public function test_photo_review_pairs_private_image_with_pinned_confirmed_transcript(): void {
        $f=$this->fixture(true); $this->draft($f); $photo=$f[4]->revision;
        $this->get($this->url($f))->assertOk()->assertSee('Foto asli privat')->assertSee('Transkripsi yang dikonfirmasi')->assertSee('1 + 1 = 2');
        $this->get('/assessments/'.$f[1]->id.'/answers/'.$f[2]->id.'/revisions/'.$photo->id.'/photo')->assertOk();
        app(TranscriptionWorkflow::class)->save($f[1],$f[0]->id,$f[2]->id,$photo->id,(string)Str::uuid(),2,'draft','1 + 1 = ?','Correction');
        $this->fails(fn()=>$this->approve($f,1));
    }
    public function test_foreign_admin_student_inactive_and_wrong_assessment_cannot_review(): void {
        $f=$this->fixture(); $url=$this->url($f);
        foreach(['teacher','admin','student'] as $role) {
            $actor=$this->user($role); $this->actingAs($actor)->get($url)->assertForbidden();
            $this->post($url,[])->assertForbidden();
        }
        $this->actingAs($f[0]); $other=$this->user(); $a=$other->assessments()->create(['title'=>'Other']);
        $this->get('/assessments/'.$a->id.'/grading-runs/'.$f[4]->id.'/review')->assertForbidden();
        $f[0]->is_active=false; $f[0]->save(); $this->get($url)->assertRedirect('/login');
        try { $this->draft($f); $this->fail('Inactive owner accepted'); } catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
    }
    public function test_regrading_keeps_prior_approval_and_cross_run_draft_head_is_enforced(): void {
        $f=$this->fixture(); $this->draft($f); $approved=$this->approve($f,1);
        $new=$this->completed($f[0],$f[1],$f[2]); $newF=$f; $newF[4]=$new;
        $this->fails(fn()=>$this->approve($newF,2));
        $this->draft($newF,2,$this->rows('0.75','New evidence review.'));
        $this->fails(fn()=>$this->approve($f,3));
        $this->assertSame($approved->id,GradeReview::where('status','approved')->first()->id);
        $newApproved=$this->approve($newF,3); $this->assertSame($approved->id,$newApproved->supersedes_approval_id);
        $this->get($this->url($newF))->assertOk()->assertSee('1.75')->assertSee('Menggantikan keputusan');
    }
    public function test_incomplete_or_tampered_proposal_cannot_be_approved_and_stale_approval_is_historical(): void {
        $f=$this->fixture(); $this->draft($f); $this->approve($f,1);
        $f[3]->update(['prompt'=>'Changed']);
        $this->get('/assessments/'.$f[1]->id.'/answers/'.$f[2]->id)->assertOk()->assertSee('Keputusan historis');
        $f[3]->update(['prompt'=>'1+1?']);
        DB::table('grading_runs')->where('id',$f[4]->id)->update(['status'=>'failed']);
        $this->fails(fn()=>$this->draft($f,2)); $this->get($this->url($f))->assertRedirect();
        DB::table('grading_runs')->where('id',$f[4]->id)->update(['status'=>'completed','input_hash'=>str_repeat('0',64)]);
        $this->fails(fn()=>$this->draft($f,2));
    }
    public function test_transaction_rollback_and_student_portal_never_publish_decisions(): void {
        $f=$this->fixture(); DB::beginTransaction(); $this->draft($f); $this->approve($f,1); DB::rollBack();
        $this->assertSame(0,GradeReview::count());
        $this->draft($f); $this->approve($f,1);
        $student=$this->user('student');
        \App\Models\StudentProfile::create(['user_id'=>$student->id,'participant_id'=>$f[2]->participant_id,'created_by'=>$f[0]->id]);
        $this->actingAs($student)->get('/student')->assertOk()->assertDontSee('Keputusan nilai guru')->assertDontSee('Partial reasoning only.');
        $this->get($this->url($f))->assertForbidden();
    }
}
