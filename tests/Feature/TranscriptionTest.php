<?php
namespace Tests\Feature;
use App\Models\{AnswerTranscription, User};
use App\Services\{AnswerIntake, TranscriptionWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class TranscriptionTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('answers'); Http::preventStrayRequests();
        $u=new User(['name'=>'Teacher','email'=>uniqid().'@example.test','password'=>'Password12345']); $u->role='teacher'; $u->is_active=true; $u->save();
        $c=$u->classrooms()->create(['name'=>'Class','grade'=>4,'school_year'=>'2026']); $p=$c->participants()->create(['code'=>'P001']);
        $a=$u->assessments()->make(['title'=>'Math']); $a->classroom()->associate($c); $a->save();
        $q=$a->questions()->create(['number'=>1,'prompt'=>'1 - 1 = ?','answer_key'=>'SECRET_KEY','scoring_guidelines'=>'SECRET_RULE','max_score'=>2]);
        $file=UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='));
        $answer=app(AnswerIntake::class)->create($a,$u->id,$p->id,$q->id,(string)Str::uuid(),['content_type'=>'photo','photo'=>$file]);
        $photo=$answer->latestRevision; $url='/assessments/'.$a->id.'/answers/'.$answer->id.'/revisions/'.$photo->id.'/transcription';
        $this->actingAs($u);
        return [$u,$a,$answer,$photo,$url,$q];
    }
    private function body(int $version=0,string $action='draft',string $text='0'): array {
        return ['creation_key'=>(string)Str::uuid(),'expected_version'=>$version,'action'=>$action,'transcript_text'=>$text,'reason'=>'Salin persis','reviewed'=>1,'privacy_checked'=>1];
    }
    private function enableAi(): void { config(['ai.enabled'=>true,'ai.transcription_enabled'=>true,'ai.model'=>'vision-test','ai.api_key'=>'test-secret']); }
    private function response(array $data): array { return ['id'=>'resp_test','status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]]]; }

    public function test_manual_draft_confirmation_replay_and_immutable_history(): void {
        [$u,$a,$answer,$photo,$url]=$this->fixture(); $workflow=app(TranscriptionWorkflow::class);
        $this->get($url)->assertOk()->assertSee('Transkripsi foto')->assertSee('AI transkripsi belum aktif');
        $this->post($url,$this->body(0,'confirm'))->assertSessionHasErrors('transcription');
        $this->assertNull($workflow->readyText($answer));
        $draft=$this->body(0,'draft',"  0\r\n1/2 cm  ");
        $this->post($url,$draft)->assertSessionDoesntHaveErrors(); $this->post($url,$draft)->assertSessionDoesntHaveErrors();
        $this->assertSame(1,AnswerTranscription::count());
        $text="  0\n1/2 cm  "; $this->assertSame($text,AnswerTranscription::first()->transcript_text);
        $this->assertNull($workflow->readyText($answer));
        $this->post($url,$this->body(1,'confirm','Different'))->assertSessionHasErrors('transcription');
        $missing=$this->body(1,'confirm',$text); unset($missing['reviewed']); $this->post($url,$missing)->assertSessionHasErrors('reviewed');
        $confirm=$this->body(1,'confirm',$text); $this->post($url,$confirm)->assertSessionDoesntHaveErrors(); $this->post($url,$confirm)->assertSessionDoesntHaveErrors();
        $this->assertSame(2,AnswerTranscription::count()); $this->assertSame($text,$workflow->readyText($answer));
        $this->assertNotNull(AnswerTranscription::where('status','confirmed')->first()->confirmed_at);
        $this->post($url,$this->body(0))->assertSessionHasErrors('transcription');
        $changed=$draft; $changed['transcript_text']='Changed'; $this->post($url,$changed)->assertSessionHasErrors('transcription');
        $this->get($url)->assertSee('Dikonfirmasi pengajar')->assertSee('Versi 1')->assertSee('Versi 2');
        try { AnswerTranscription::first()->update(['transcript_text'=>'Tampered']); $this->fail('Mutable history'); } catch(\LogicException) { $this->assertTrue(true); }
        $this->post($url,$this->body(2,'draft','Correction'))->assertSessionDoesntHaveErrors();
        $this->assertNull($workflow->readyText($answer));
        Http::assertNothingSent();
    }
    public function test_unreadable_marker_and_stale_photo_or_source_never_ready(): void {
        [$u,$a,$answer,$photo,$url,$q]=$this->fixture(); $workflow=app(TranscriptionWorkflow::class);
        $this->post($url,$this->body(0,'draft','[tidak terbaca]'))->assertSessionDoesntHaveErrors();
        $this->post($url,$this->body(1,'confirm','[tidak terbaca]'))->assertSessionHasErrors('transcription');
        $this->post($url,$this->body(1,'draft','0'))->assertSessionDoesntHaveErrors();
        $this->post($url,$this->body(2,'confirm','0'))->assertSessionDoesntHaveErrors();
        $this->assertSame('0',$workflow->readyText($answer));
        $q->update(['prompt'=>'Changed question']); $this->assertNull($workflow->readyText($answer));
        app(AnswerIntake::class)->revise($a,$u->id,$answer->id,1,'Replace answer',['content_type'=>'text','answer_text'=>'New answer']);
        $this->post($url,$this->body(3))->assertSessionHasErrors('transcription');
        $this->get($url)->assertOk()->assertSee('riwayat lama')->assertDontSee('Simpan draf transkripsi');
        $this->assertSame('New answer',$workflow->readyText($answer)); $this->assertSame(3,AnswerTranscription::count());
    }
    public function test_scoped_access_inactive_and_foreign_teacher(): void {
        [$u,$a,$answer,$photo,$url]=$this->fixture();
        $other=new User(['name'=>'Other','email'=>'other@example.test','password'=>'Password12345']); $other->role='teacher'; $other->is_active=true; $other->save();
        $this->actingAs($other)->get($url)->assertForbidden(); $this->post($url,$this->body())->assertForbidden();
        $this->actingAs($u)->get(str_replace('/revisions/'.$photo->id,'/revisions/999999',$url))->assertNotFound();
        $u->is_active=false; $u->save(); $this->get($url)->assertRedirect('/login');
        $this->assertSame(0,AnswerTranscription::count());
    }
    public function test_ai_requires_configuration_and_consent_and_never_auto_confirms(): void {
        [$u,$a,$answer,$photo,$url]=$this->fixture();
        $body=$this->body(0,'ai'); $this->post($url,$body)->assertSessionHasErrors('transcription');
        $this->enableAi(); $missing=$body; unset($missing['privacy_checked']); $this->post($url,$missing)->assertSessionHasErrors('privacy_checked');
        Http::fake(['api.openai.com/*'=>Http::response($this->response(['transcript_text'=>'[tidak terbaca] / 2','uncertainties'=>[['location'=>'Baris pertama','description'=>'Pembilang kabur']]]))]);
        $this->post($url,$body)->assertSessionDoesntHaveErrors(); $this->post($url,$body)->assertSessionDoesntHaveErrors();
        Http::assertSentCount(1); $entry=AnswerTranscription::firstOrFail();
        $this->assertSame('draft',$entry->status); $this->assertSame('ai',$entry->origin); $this->assertNull($entry->confirmed_at);
        $this->assertNull(app(TranscriptionWorkflow::class)->readyText($answer));
        Http::assertSent(function($request) {
            $json=json_encode($request->data(),JSON_UNESCAPED_SLASHES);
            return $request->url()==='https://api.openai.com/v1/responses' && $request['store']===false && str_contains($json,'data:image/png;base64,') && !str_contains($json,'SECRET_KEY') && !str_contains($json,'SECRET_RULE') && !str_contains($json,'P001') && !str_contains($json,'example.test');
        });
        $this->get($url)->assertSee('Pembilang kabur');
        $this->post($url,$this->body(1,'draft','1/2'))->assertSessionDoesntHaveErrors();
        $this->assertSame($entry->uncertainties,AnswerTranscription::orderByDesc('id')->first()->uncertainties);
        $this->post($url,$this->body(2,'confirm','1/2'))->assertSessionDoesntHaveErrors();
        $this->assertSame('1/2',app(TranscriptionWorkflow::class)->readyText($answer));
    }
    public function test_ai_failure_empty_uncertainties_corrupt_image_and_stale_request(): void {
        [$u,$a,$answer,$photo,$url]=$this->fixture(); $this->enableAi();
        Http::fake(['api.openai.com/*'=>Http::sequence()->push(['error'=>['message'=>'test-secret']],500)->push($this->response(['transcript_text'=>'0','uncertainties'=>[]]))]);
        $this->post($url,$this->body(0,'ai'))->assertSessionHasErrors('transcription'); $this->assertSame(0,AnswerTranscription::count());
        $this->post($url,$this->body(0,'ai'))->assertSessionDoesntHaveErrors();
        $this->assertSame([],AnswerTranscription::first()->uncertainties);
        Http::fake(); $this->post($url,$this->body(0,'ai'))->assertSessionHasErrors('transcription'); Http::assertNothingSent();
        Storage::disk('answers')->put($photo->photo_path,'corrupt');
        $this->post($url,$this->body(1,'ai'))->assertSessionHasErrors('transcription'); Http::assertNothingSent();
    }
    public function test_invalid_ai_outputs_are_rejected_without_leaking_provider_errors(): void {
        [$u,$a,$answer,$photo,$url]=$this->fixture(); $this->enableAi();
        Http::fake(['api.openai.com/*'=>Http::sequence()
            ->push(['status'=>'incomplete','output'=>[]])
            ->push(['status'=>'completed','output'=>[['content'=>[['type'=>'refusal','refusal'=>'test-secret']]]]])
            ->push(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>'invalid json']]]]])
            ->push($this->response(['transcript_text'=>'0','uncertainties'=>[['location'=>'','description'=>'bad']]]))]);
        for($i=0;$i<4;$i++) {
            $this->post($url,$this->body(0,'ai'))->assertSessionHasErrors('transcription');
            $this->get($url)->assertDontSee('test-secret')->assertDontSee('invalid json');
        }
        $this->assertSame(0,AnswerTranscription::count());
    }
    public function test_answer_replaced_during_ai_request_discards_result(): void {
        [$u,$a,$answer,$photo,$url]=$this->fixture(); $this->enableAi();
        Http::fake(function() use($u,$a,$answer) {
            app(AnswerIntake::class)->revise($a,$u->id,$answer->id,1,'Correction while AI runs',['content_type'=>'text','answer_text'=>'0']);
            return Http::response($this->response(['transcript_text'=>'Old photo','uncertainties'=>[]]));
        });
        $this->post($url,$this->body(0,'ai'))->assertSessionHasErrors('transcription');
        $this->assertSame(0,AnswerTranscription::count());
        $this->assertSame('0',app(TranscriptionWorkflow::class)->readyText($answer));
        $new=$answer->fresh()->latestRevision;
        $this->get(str_replace('/revisions/'.$photo->id,'/revisions/'.$new->id,$url))->assertNotFound();
    }
}
