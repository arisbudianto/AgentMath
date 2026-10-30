<?php
namespace Tests\Feature;
use App\Models\{AnswerDiagnosis,GradeReview,GradingRun,User};
use App\Services\{AnswerIntake,GradeReviewWorkflow,GradingOrchestrator,GradingStages,MisconceptionDiagnosisWorkflow,RubricWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiagnosisTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role='teacher'): User {
        $u=new User(['name'=>'Synthetic '.$role,'email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role=$role; $u->is_active=true; $u->save(); return $u;
    }
    private function fixture(): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'MOCK_ONLY','ai.max_output_tokens'=>4000]);
        $u=$this->user(); $c=$u->classrooms()->create(['name'=>'Synthetic','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'P001']);
        $a=$u->assessments()->make(['title'=>'Synthetic diagnosis']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1+1?','answer_key'=>'2','scoring_guidelines'=>'One point for steps, one for correct result.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $rw=app(RubricWorkflow::class); $r=$rw->create($q,$u->id,(string)Str::uuid(),'manual');
        $data=$r->only(['rules','criteria','grading_policy']); $data['criteria'][0]['max_score']='1.00'; $data['criteria'][]=array_replace($data['criteria'][0],['criterion_id'=>'C2']);
        $r=$rw->save($q,$r->id,$r->revision,$data,false,$u->id); $r=$rw->save($q,$r->id,$r->revision,[],true,$u->id);
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'1 + 1 = 3']);
        $run=$this->completed($u,$a,$answer);
        $this->actingAs($u); return [$u,$a,$answer,$q,$run];
    }
    private function completed($u,$a,$answer): GradingRun {
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        // C1 (steps) gets full marks, C2 (final result) misses - a genuine weak criterion for diagnosis.
        $marker=['criteria'=>[
            ['criterion_id'=>'C1','score'=>1,'evidence'=>['1 + 1'],'missing_evidence'=>false,'reason'=>'Steps shown.','rule_ids'=>['R1']],
            ['criterion_id'=>'C2','score'=>0,'evidence'=>['= 3'],'missing_evidence'=>false,'reason'=>'Wrong final result.','rule_ids'=>['R1']],
        ]];
        $sequence=Http::sequence(); foreach([$marker,$marker,['label'=>'High','reason'=>'Consistent.','concerns'=>[]]] as $data) $sequence->push(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]]);
        Http::fake(['ai.dinoiki.com/*'=>$sequence]);
        $w=app(GradingOrchestrator::class); $run=$w->start($a,$u->id,$answer->id,(string)Str::uuid());
        foreach(GradingStages::PLAN as $stage) { $run->refresh(); $w->process($run->id,$run->generation); }
        $this->assertSame('completed',$run->fresh()->status); return $run->fresh();
    }
    private function approvedReview(array $f): GradeReview {
        $rows=[['criterion_id'=>'C1','score'=>'1.00','reason'=>''],['criterion_id'=>'C2','score'=>'0.00','reason'=>'Wrong final result.']];
        $w=app(GradeReviewWorkflow::class); $w->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),0,'draft',$rows,'Checked against evidence.');
        return $w->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),1,'approve',[],'',true);
    }
    private function fails(callable $fn): void { try { $fn(); $this->fail('Expected validation error'); } catch(ValidationException) { $this->assertTrue(true); } }
    private function aiFake(array $items): void {
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        Http::fake(['ai.dinoiki.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['items'=>$items])]]]]])]);
    }

    public function test_eligible_criteria_only_includes_scores_below_max(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $weak=app(MisconceptionDiagnosisWorkflow::class)->eligibleCriteria($f[2]->fresh());
        $this->assertCount(1,$weak); $this->assertSame('C2',$weak[0]['criterion_id']);
    }
    public function test_no_approved_review_or_no_weak_criteria_blocks_generation(): void {
        $f=$this->fixture();
        $this->fails(fn()=>app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,(string)Str::uuid()));
        $full=[['criterion_id'=>'C1','score'=>'1.00','reason'=>''],['criterion_id'=>'C2','score'=>'1.00','reason'=>'Re-checked, final result is actually correct.']];
        $w=app(GradeReviewWorkflow::class); $w->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),0,'draft',$full,'All correct.');
        $w->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),1,'approve',[],'',true);
        $this->fails(fn()=>app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,(string)Str::uuid()));
    }
    public function test_generate_creates_draft_from_snapshot_and_is_idempotent_by_key(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $this->aiFake([['criterion_id'=>'C2','category'=>'perhitungan','explanation'=>'Salah hitung penjumlahan.','recommended_action'=>'Beri latihan penjumlahan sederhana.']]);
        $key=(string)Str::uuid();
        $d1=app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,$key);
        $this->assertSame('draft',$d1->status); $this->assertSame('ai',$d1->source); $this->assertSame(1,$d1->version); $this->assertCount(1,$d1->items);
        $d2=app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,$key);
        $this->assertSame($d1->id,$d2->id); $this->assertSame(1,AnswerDiagnosis::count());
    }
    public function test_ai_items_not_matching_weak_criteria_are_rejected(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $this->aiFake([['criterion_id'=>'C1','category'=>'konsep','explanation'=>'x','recommended_action'=>'y']]);
        $this->fails(fn()=>app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,(string)Str::uuid()));
        $this->assertSame(0,AnswerDiagnosis::count());
    }
    public function test_decide_approve_and_reject_create_new_immutable_versions_with_optimistic_lock(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $this->aiFake([['criterion_id'=>'C2','category'=>'perhitungan','explanation'=>'Salah hitung.','recommended_action'=>'Latihan tambahan.']]);
        $draft=app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,(string)Str::uuid());
        $w=app(MisconceptionDiagnosisWorkflow::class);
        $this->fails(fn()=>$w->decide($f[2]->fresh(),$draft->id,0,'approve',$f[0]->id));
        $approved=$w->decide($f[2]->fresh(),$draft->id,1,'approve',$f[0]->id);
        $this->assertSame('approved',$approved->status); $this->assertSame(2,$approved->version); $this->assertSame($draft->id,$approved->parent_id);
        $this->assertNotNull($approved->approved_at);
        foreach(['update','delete'] as $method) { try { $method==='update'?$approved->update(['status'=>'rejected']):$approved->delete(); $this->fail('Mutable diagnosis'); } catch(\LogicException) { $this->assertTrue(true); } }
        $this->fails(fn()=>$w->decide($f[2]->fresh(),$draft->id,1,'reject',$f[0]->id));
    }
    public function test_cannot_approve_diagnosis_after_new_grade_review_is_approved(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $this->aiFake([['criterion_id'=>'C2','category'=>'perhitungan','explanation'=>'Salah hitung.','recommended_action'=>'Latihan tambahan.']]);
        $draft=app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,(string)Str::uuid());

        $rows=[['criterion_id'=>'C1','score'=>'1.00','reason'=>''],['criterion_id'=>'C2','score'=>'0.00','reason'=>'Checked again.']];
        $reviews=app(GradeReviewWorkflow::class);
        $reviews->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),2,'draft',$rows,'Rechecked the answer.');
        $reviews->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),3,'approve',[],'',true);

        $this->fails(fn()=>app(MisconceptionDiagnosisWorkflow::class)->decide($f[2]->fresh(),$draft->id,$draft->version,'approve',$f[0]->id));
        $this->assertSame(1,AnswerDiagnosis::count());
        $this->assertSame('draft',$draft->fresh()->status);
        // Rejection remains possible to close an obsolete draft without publishing it.
        $rejected=app(MisconceptionDiagnosisWorkflow::class)->decide($f[2]->fresh(),$draft->id,$draft->version,'reject',$f[0]->id);
        $this->assertSame('rejected',$rejected->status);
    }
    public function test_decide_reject_with_teacher_edited_items(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $this->aiFake([['criterion_id'=>'C2','category'=>'perhitungan','explanation'=>'Salah hitung.','recommended_action'=>'Latihan tambahan.']]);
        $draft=app(MisconceptionDiagnosisWorkflow::class)->generate($f[2]->fresh(),$f[0]->id,(string)Str::uuid());
        $edited=[['criterion_id'=>'C2','category'=>'konsep','explanation'=>'Sebenarnya salah konsep, bukan hitung.','recommended_action'=>'Ulangi konsep penjumlahan.']];
        $rejected=app(MisconceptionDiagnosisWorkflow::class)->decide($f[2]->fresh(),$draft->id,1,'reject',$f[0]->id,$edited,'Kategori AI salah.');
        $this->assertSame('rejected',$rejected->status); $this->assertSame('teacher',$rejected->source); $this->assertSame('Kategori AI salah.',$rejected->reason);
        $this->assertSame('konsep',$rejected->items[0]['category']);
    }
    public function test_http_flow_shows_weak_criteria_and_requires_ownership(): void {
        $f=$this->fixture(); $this->approvedReview($f);
        $url='/assessments/'.$f[1]->id.'/answers/'.$f[2]->id.'/diagnosis';
        $this->get($url)->assertOk()->assertSee('C2');
        $this->get('/assessments/'.$f[1]->id.'/answers/'.$f[2]->id)->assertOk()->assertSee('Diagnosis kesalahan');
        $this->aiFake([['criterion_id'=>'C2','category'=>'perhitungan','explanation'=>'Salah hitung.','recommended_action'=>'Latihan tambahan.']]);
        $this->post($url,['creation_key'=>(string)Str::uuid()])->assertRedirect($url);
        $this->assertSame(1,AnswerDiagnosis::count());
        foreach(['teacher','admin','student'] as $role) {
            $actor=$this->user($role); $this->actingAs($actor)->get($url)->assertForbidden();
        }
    }
}
