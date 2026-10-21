<?php
namespace Tests\Feature;
use App\Models\{User,GradeReview};
use App\Services\{AnswerIntake,GradeReviewWorkflow,GradingOrchestrator,GradingStages,ResultReport,RubricWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class ResultReportTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role='teacher'): User {
        $u=new User(['name'=>'DO_NOT_EXPORT_NAME','email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role=$role; $u->is_active=true; $u->save(); return $u;
    }
    private function fixture(bool $approve=true,string $score='0.00'): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'DO_NOT_EXPORT_KEY','ai.max_output_tokens'=>4000]);
        $u=$this->user(); $c=$u->classrooms()->create(['name'=>'Synthetic','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'P001']);
        $a=$u->assessments()->make(['title'=>'DO_NOT_EXPORT_TITLE']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1 + 1? DO_NOT_EXPORT_QUESTION','answer_key'=>'2 DO_NOT_EXPORT_ANSWER_KEY','scoring_guidelines'=>'Two points for correct result.','max_score'=>'2.00']); $q->setRelation('assessment',$a);
        $rw=app(RubricWorkflow::class); $r=$rw->create($q,$u->id,(string)Str::uuid(),'manual'); $rw->save($q,$r->id,$r->revision,[],true,$u->id);
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'text','answer_text'=>'1 + 1 = 2 DO_NOT_EXPORT_TEXT']);
        $marker=['criteria'=>[['criterion_id'=>'C1','score'=>2,'evidence'=>['1 + 1 = 2'],'missing_evidence'=>false,'reason'=>'Synthetic match.','rule_ids'=>['R1']]]];
        $seq=Http::sequence(); foreach([$marker,$marker,['label'=>'High','reason'=>'Synthetic consistency.','concerns'=>[]]] as $data) $seq->push(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]]);
        Http::fake(['ai.dinoiki.com/*'=>$seq]); $w=app(GradingOrchestrator::class); $run=$w->start($a,$u->id,$answer->id,(string)Str::uuid());
        foreach(GradingStages::PLAN as $stage) { $run->refresh(); $w->process($run->id,$run->generation); }
        $this->assertSame('completed',$run->fresh()->status); $this->actingAs($u);
        $f=[$u,$a,$answer,$q,$run,$p,$c];
        if($approve) {
            $this->draft($f,0,$score);
            app(GradeReviewWorkflow::class)->save($a,$u->id,$run->id,(string)Str::uuid(),1,'approve',[],'',true);
        }
        return $f;
    }
    private function draft(array $f,int $expected,string $score): void {
        app(GradeReviewWorkflow::class)->save($f[1],$f[0]->id,$f[4]->id,(string)Str::uuid(),$expected,'draft',
            [['criterion_id'=>'C1','score'=>$score,'reason'=>'Synthetic correction.']],'Synthetic teacher check.');
    }
    private function report(array $f,?int $p=null,?int $q=null): array { return app(ResultReport::class)->build($f[1],$p,$q); }
    private function url(array $f): string { return '/assessments/'.$f[1]->id.'/results'; }
    private function csv($response): array {
        $text=$response->streamedContent(); $this->assertStringStartsWith("\xEF\xBB\xBF",$text);
        $stream=fopen('php://temp','w+'); fwrite($stream,substr($text,3)); rewind($stream); $header=fgetcsv($stream,0,',','"',''); $rows=[];
        while(($row=fgetcsv($stream,0,',','"',''))!==false) $rows[]=array_combine($header,$row);
        fclose($stream); return $rows;
    }
    public function test_real_approved_zero_is_final_while_missing_answer_is_blank(): void {
        $f=$this->fixture(); $f[6]->participants()->create(['code'=>'P002']); $r=$this->report($f);
        $this->assertSame('0.00',$r['summary'][0]['final_total']); $this->assertTrue($r['summary'][0]['complete']);
        $this->assertNull($r['summary'][1]['final_total']); $this->assertFalse($r['summary'][1]['complete']);
        $this->assertSame('2.00',$r['details'][0]['proposal_score']); $this->assertSame('0.00',$r['details'][0]['approved_score']);
        $this->assertSame('missing',$r['details'][1]['answer_status']);
        $this->get($this->url($f))->assertOk()->assertSee('Rekap dan ekspor nilai')->assertSee('0.00')->assertSee('belum lengkap');
        $this->get($this->url($f).'?participant='.$f[5]->id)->assertOk()->assertSee('Disahkan guru')->assertSee('Usulan AI terkini');
    }
    public function test_missing_question_approval_prevents_total_and_filter_scope_is_explicit(): void {
        $f=$this->fixture(true,'1.50'); $q2=$f[1]->questions()->create(['number'=>2,'prompt'=>'Next','answer_key'=>'Next','scoring_guidelines'=>'Next','max_score'=>'3.00']);
        $r=$this->report($f); $this->assertSame('1.50',$r['summary'][0]['approved_sum']); $this->assertNull($r['summary'][0]['final_total']); $this->assertSame('5.00',$r['summary'][0]['max_score']);
        $scope=$this->report($f,null,$f[3]->id); $this->assertSame('1.50',$scope['summary'][0]['final_total']); $this->assertSame(1,$scope['summary'][0]['questions']);
        $this->get($this->url($f).'?question='.$f[3]->id)->assertSee('Filter satu soal aktif');
    }
    public function test_new_draft_or_failed_regrading_does_not_erase_current_approval(): void {
        $f=$this->fixture(true,'1.00'); $this->draft($f,2,'0.50');
        $new=app(GradingOrchestrator::class)->start($f[1],$f[0]->id,$f[2]->id,(string)Str::uuid()); $new->update(['status'=>'failed']);
        $r=$this->report($f); $this->assertSame('1.00',$r['summary'][0]['final_total']); $this->assertSame(1,$r['summary'][0]['drafts']);
        $this->assertSame('failed',$r['details'][0]['latest_run_status']); $this->assertSame($f[4]->id,$r['details'][0]['proposal_run_id']);
    }
    public function test_source_or_answer_change_makes_scores_historical_without_zero_fallback(): void {
        $f=$this->fixture(); $f[3]->update(['prompt'=>'Changed']);
        $r=$this->report($f); $this->assertSame('historical',$r['details'][0]['proposal_status']); $this->assertSame('historical',$r['details'][0]['grade_status']);
        $this->assertNull($r['details'][0]['approved_score']); $this->assertNull($r['summary'][0]['final_total']); $this->assertSame(1,$r['summary'][0]['historical']);
        $f[3]->update(['prompt'=>'1 + 1? DO_NOT_EXPORT_QUESTION']);
        app(AnswerIntake::class)->revise($f[1],$f[0]->id,$f[2]->id,1,'Revision',['content_type'=>'text','answer_text'=>'Changed']);
        $r=$this->report($f); $this->assertSame('historical',$r['details'][0]['grade_status']); $this->assertNull($r['summary'][0]['final_total']);
    }
    public function test_csv_headers_privacy_scope_and_zero_blank_are_preserved(): void {
        $f=$this->fixture(); $f[6]->participants()->create(['code'=>'P002']);
        $response=$this->get($this->url($f).'/export?kind=summary')->assertOk()->assertHeader('Content-Type','text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $rows=$this->csv($response); $this->assertSame('0.00',$rows[0]['final_total']); $this->assertSame('',$rows[1]['final_total']); $this->assertSame('incomplete',$rows[1]['complete']);
        $detail=$this->get($this->url($f).'/export?kind=detail')->assertOk(); $rows=$this->csv($detail);
        $this->assertSame('2.00',$rows[0]['proposal_score']); $this->assertSame('0.00',$rows[0]['approved_score']); $this->assertSame('missing',$rows[1]['answer_status']);
        $text=$detail->streamedContent();
        foreach(['DO_NOT_EXPORT_NAME','DO_NOT_EXPORT_KEY','DO_NOT_EXPORT_TEXT','DO_NOT_EXPORT_ANSWER_KEY','DO_NOT_EXPORT_QUESTION','DO_NOT_EXPORT_TITLE'] as $secret) $this->assertStringNotContainsString($secret,$text);
        $rows=$this->csv($this->get($this->url($f).'/export?participant='.$f[5]->id.'&kind=detail'));
        $this->assertCount(1,$rows); Http::assertSentCount(3);
    }
    public function test_csv_formula_neutralization_quotes_unicode_and_all_pages(): void {
        $f=$this->fixture(false); $f[5]->update(['code'=>'=SUM(1,2)']);
        for($i=2;$i<=27;$i++) $f[6]->participants()->create(['code'=>'P'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]);
        $rows=$this->csv($this->get($this->url($f).'/export'));
        $this->assertCount(27,$rows); $this->assertSame("'=SUM(1,2)",$rows[0]['participant_code']);
        foreach(['=1','+cmd','-1','@SUM(A1)',"\t=1","\r+1"," \n@A"] as $text) $this->assertSame("'".$text,\App\Http\Controllers\ResultReportController::safeCell($text));
        $this->assertSame('0.00',\App\Http\Controllers\ResultReportController::safeCell('0.00'));
        $this->get($this->url($f).'?page=2')->assertOk()->assertSee('P026');
        $code="P, \"uji\"\nkelas"; $f[5]->update(['code'=>$code]);
        $rows=$this->csv($this->get($this->url($f).'/export?participant='.$f[5]->id)); $this->assertSame($code,$rows[0]['participant_code']);
    }
    public function test_foreign_admin_student_and_foreign_filters_cannot_export(): void {
        $f=$this->fixture(); foreach(['teacher','admin','student'] as $role) {
            $this->actingAs($this->user($role))->get($this->url($f))->assertForbidden();
            $this->get($this->url($f).'/export')->assertForbidden();
        }
        $this->actingAs($f[0]); $this->get($this->url($f).'?participant=99999')->assertNotFound();
        $this->get($this->url($f).'/export?question=99999')->assertNotFound();
        $this->get($this->url($f).'/export?kind=unknown')->assertRedirect();
    }
    public function test_empty_package_and_oversize_export_are_not_silently_truncated(): void {
        $u=$this->user(); $a=$u->assessments()->create(['title'=>'Empty']); $this->actingAs($u);
        $r=app(ResultReport::class)->build($a); $this->assertSame([],$r['summary']);
        $f=$this->fixture(false); $participants=[]; for($i=0;$i<1000;$i++) $participants[]=['classroom_id'=>$f[6]->id,'code'=>'X'.$i,'created_at'=>now(),'updated_at'=>now()];
        DB::table('participants')->insert($participants);
        $this->get($this->url($f))->assertOk()->assertSee('Pilihan terlalu besar');
        $this->get($this->url($f).'/export')->assertStatus(422);
        $rows=$this->csv($this->get($this->url($f).'/export?participant='.$f[5]->id)); $this->assertCount(1,$rows);
    }
    public function test_invalid_approved_total_is_held_without_exported_final_score(): void {
        $f=$this->fixture(); DB::table('grade_reviews')->where('status','approved')->update(['total_score'=>'99.00']);
        $r=$this->report($f); $this->assertSame('invalid',$r['details'][0]['grade_status']); $this->assertNull($r['summary'][0]['final_total']);
        $rows=$this->csv($this->get($this->url($f).'/export?kind=detail')); $this->assertSame('',$rows[0]['approved_score']);
    }
    public function test_photo_confirmation_and_corrupt_snapshot_are_not_promoted_to_final_grades(): void {
        $f=$this->fixture();
        DB::table('grading_runs')->where('id',$f[4]->id)->update(['input_hash'=>str_repeat('0',64)]);
        $r=$this->report($f); $this->assertSame('invalid',$r['details'][0]['proposal_status']); $this->assertSame('invalid',$r['details'][0]['grade_status']);
        \Illuminate\Support\Facades\Storage::fake('answers');
        $photo=\Illuminate\Http\UploadedFile::fake()->createWithContent('test.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='));
        $answer=app(AnswerIntake::class)->revise($f[1],$f[0]->id,$f[2]->id,1,'Photo revision',['content_type'=>'photo','photo'=>$photo]);
        $r=$this->report($f); $this->assertSame('photo_unconfirmed',$r['details'][0]['answer_status']); $this->assertNull($r['summary'][0]['final_total']);
        $answer->refresh(); $revision=$answer->latestRevision; $tw=app(\App\Services\TranscriptionWorkflow::class);
        $tw->save($f[1],$f[0]->id,$answer->id,$revision->id,(string)Str::uuid(),0,'draft','1 + 1 = 2','Copy');
        $tw->save($f[1],$f[0]->id,$answer->id,$revision->id,(string)Str::uuid(),1,'confirm','1 + 1 = 2','Checked',[],true);
        $r=$this->report($f); $this->assertSame('ready',$r['details'][0]['answer_status']); $this->assertSame('historical',$r['details'][0]['grade_status']);
    }
}
