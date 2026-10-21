<?php
namespace Tests\Feature;
use App\Models\{User,BankBook,BankChapter,BankPage,BankSet,BankQuestionLink};
use App\Services\QuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,DB,Http};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class QuestionBankTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role='teacher') { $u=new User(['name'=>'Teacher','email'=>uniqid().'@example.test','password'=>'TestPassword123']); $u->role=$role; $u->is_active=true; $u->save(); return $u; }
    private function fixture(): array {
        Storage::fake('local'); Http::preventStrayRequests();
        $v=['book'=>['code'=>'kelas1-test','title'=>'Buku sumber kelas I','authors'=>'Penulis','edition'=>'2022','isbn'=>'test-isbn','grade'=>1,'source_hash'=>str_repeat('a',64)],'chapters'=>[['number'=>1,'title'=>'Bilangan','start_page'=>1,'end_page'=>2,'objectives'=>'Membilang sampai10.']],'pages'=>[],'sets'=>[]];
        foreach([1,2] as $n) { $bytes='TEST_IMAGE_'.$n; Storage::disk('local')->put('bank-books/kelas1-test/pages/'.$n.'.webp',$bytes); $v['pages'][]=['number'=>$n,'pdf_page'=>$n+12,'source_text'=>'SOURCE_PAGE_'.$n.' Hitung jumlah benda.','image_hash'=>hash('sha256',$bytes)]; }
        $v['sets'][]=['chapter'=>1,'code'=>'kelas1-test-latihan-1','kind'=>'latihan','title'=>'Latihan bilangan','start_page'=>1,'end_page'=>2,'source_text'=>implode("\n\n",array_column($v['pages'],'source_text'))];
        Storage::disk('local')->put('bank-books/kelas1-test/manifest.json',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $path=Storage::disk('local')->path('bank-books/kelas1-test/manifest.json'); return [$v,$path];
    }
    private function install() { [$v,$path]=$this->fixture(); $b=app(QuestionBank::class)->install($path); return [$b,BankSet::firstOrFail(),$path,$v]; }
    private function input(): array { return ['number'=>'1','prompt'=>'Ada 3 bola merah dan 2 biru. Berapa jumlahnya?','answer_key'=>'5 bola','scoring_guidelines'=>'2 poin untuk jumlah benar; terima gambar lima bola.','max_score'=>'2.00']; }
    private function fails(callable $f): void { try { $f(); $this->fail('Expected validation failure'); } catch(ValidationException) { $this->assertTrue(true); } }
    public function test_private_import_counts_idempotency_immutability_and_changed_manifest_rejected(): void {
        [$b,$s,$path,$v]=$this->install(); $again=app(QuestionBank::class)->install($path); $this->assertSame($b->id,$again->id);
        $this->assertDatabaseCount('bank_books',1); $this->assertDatabaseCount('bank_chapters',1); $this->assertDatabaseCount('bank_pages',2); $this->assertDatabaseCount('bank_sets',1);
        $this->assertSame(13,(int)BankPage::first()->pdf_page); $this->assertSame(2,$s->sourcePages()->count());
        try { $s->update(['title'=>'Changed']); $this->fail('Mutable'); } catch(\LogicException) { $this->assertTrue(true); }
        $v['book']['title']='Changed edition'; file_put_contents($path,json_encode($v)); $this->fails(fn()=>app(QuestionBank::class)->install($path)); Http::assertNothingSent();
    }
    public function test_missing_image_bad_range_duplicate_pages_and_wrong_folder_are_atomic_failures(): void {
        [$v,$path]=$this->fixture(); Storage::disk('local')->delete('bank-books/kelas1-test/pages/2.webp'); $this->fails(fn()=>app(QuestionBank::class)->install($path)); $this->assertDatabaseCount('bank_books',0);
        [$v,$path]=$this->fixture(); $v['sets'][0]['end_page']=3; file_put_contents($path,json_encode($v)); $this->fails(fn()=>app(QuestionBank::class)->install($path)); $this->assertDatabaseCount('bank_pages',0);
        [$v,$path]=$this->fixture(); $v['pages'][]=$v['pages'][0]; file_put_contents($path,json_encode($v)); $this->fails(fn()=>app(QuestionBank::class)->install($path)); $this->assertDatabaseCount('bank_books',0);
        $this->fails(fn()=>app(QuestionBank::class)->install(base_path('composer.json')));
    }
    public function test_teacher_search_filters_original_private_image_and_role_gates(): void {
        [$b,$s]=$this->install(); $u=$this->user(); $this->actingAs($u);
        $this->get('/question-bank')->assertOk()->assertSee('Latihan bilangan')->assertSee('2 halaman materi');
        $this->get('/question-bank?q=SOURCE_PAGE_2')->assertOk()->assertSee('Latihan bilangan');
        $this->get('/question-bank?kind=evaluasi')->assertOk()->assertDontSee('Latihan bilangan');
        $this->get('/question-bank?chapter=9999')->assertNotFound(); $this->get('/question-bank?book=9999')->assertNotFound();
        $this->get('/question-bank/sets/'.$s->id)->assertOk()->assertSee('SOURCE_PAGE_1')->assertSee('Kunci / alternatif jawaban sah');
        $p=BankPage::first(); $this->get('/question-bank/pages/'.$p->id)->assertOk()->assertHeader('Content-Type','image/webp')->assertHeader('X-Content-Type-Options','nosniff');
        foreach(['student','admin'] as $role) { $this->actingAs($this->user($role)); foreach(['/question-bank','/question-bank/sets/'.$s->id,'/question-bank/pages/'.$p->id] as $url) $this->get($url)->assertForbidden(); }
    }
    public function test_copy_requires_human_key_full_prompt_and_review_then_tracks_source_and_replays(): void {
        [$b,$s]=$this->install(); $u=$this->user(); $this->actingAs($u); $a=$u->assessments()->create(['title'=>'Kelas1']);
        $base='/question-bank/sets/'.$s->id.'/copy'; $payload=$this->input()+['assessment_id'=>$a->id,'creation_key'=>(string)Str::uuid()];
        $this->post($base,$payload)->assertSessionHasErrors('reviewed'); $this->assertDatabaseCount('questions',0);
        $bad=$payload+['reviewed'=>1]; $bad['answer_key']=''; $this->post($base,$bad)->assertSessionHasErrors('answer_key');
        $this->post($base,$payload+['reviewed'=>1])->assertRedirect(); $this->post($base,$payload+['reviewed'=>1])->assertRedirect();
        $this->assertDatabaseCount('questions',1); $this->assertDatabaseCount('bank_question_links',1); $q=$a->questions()->first();
        $this->assertStringContainsString('Sumber: Buku sumber kelas I',$q->prompt); $this->assertSame('5 bola',$q->answer_key); $this->assertNull($q->currentApprovedRubric());
        $link=BankQuestionLink::first(); $this->assertSame($s->content_hash,$link->source_snapshot['set_hash']);
        $this->post($base,array_replace($payload,['reviewed'=>1,'prompt'=>'Changed']))->assertSessionHasErrors('bank'); Http::assertNothingSent();
    }
    public function test_foreign_assessment_wrong_grade_number_collision_and_invalid_source_cannot_copy(): void {
        [$b,$s]=$this->install(); $u=$this->user(); $this->actingAs($u); $bank=app(QuestionBank::class); $foreign=$this->user()->assessments()->create(['title'=>'Foreign']);
        $this->post('/question-bank/sets/'.$s->id.'/copy',$this->input()+['assessment_id'=>$foreign->id,'creation_key'=>(string)Str::uuid(),'reviewed'=>1])->assertForbidden();
        $c=$u->classrooms()->create(['name'=>'Kelas4','grade'=>4,'school_year'=>'2026']); $a=$u->assessments()->make(['title'=>'Own']); $a->classroom()->associate($c); $a->save();
        $this->fails(fn()=>$bank->copy($s,$u->id,$a->id,(string)Str::uuid(),$this->input(),true)); $a->classroom_id=null; $a->save();
        $bank->copy($s,$u->id,$a->id,(string)Str::uuid(),$this->input(),true); $this->fails(fn()=>$bank->copy($s,$u->id,$a->id,(string)Str::uuid(),$this->input(),true));
        Storage::disk('local')->put('bank-books/kelas1-test/pages/1.webp','CORRUPTED'); $this->fails(fn()=>$bank->copy($s,$u->id,$a->id,(string)Str::uuid(),array_replace($this->input(),['number'=>'2']),true));
        $this->assertDatabaseCount('questions',1); $this->getJson('/question-bank/sets/'.$s->id)->assertStatus(422);
    }
    public function test_copy_transaction_rolls_back_question_if_receipt_fails_and_database_source_tamper_is_held(): void {
        [$b,$s]=$this->install(); $u=$this->user(); $this->actingAs($u); $a=$u->assessments()->create(['title'=>'Own']);
        BankQuestionLink::creating(fn()=>throw new \RuntimeException('Receipt failure'));
        try { app(QuestionBank::class)->copy($s,$u->id,$a->id,(string)Str::uuid(),$this->input(),true); $this->fail('Should fail'); } catch(\RuntimeException $e) { $this->assertSame('Receipt failure',$e->getMessage()); } finally { BankQuestionLink::flushEventListeners(); }
        $this->assertDatabaseCount('questions',0); DB::table('bank_sets')->where('id',$s->id)->update(['source_text'=>'INJECTED']);
        $this->fails(fn()=>app(QuestionBank::class)->validSet($s->fresh()));
    }
}
