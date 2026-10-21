<?php
namespace Tests\Feature;
use App\Models\AnswerRevision;
use App\Services\{AiConfiguration, RubricAi, TranscriptionAi};
use Illuminate\Support\Facades\{Http, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class DinoikiAiTest extends TestCase {
    private function enable(): void { config(['ai.enabled'=>true,'ai.transcription_enabled'=>true,'ai.provider'=>'dinoiki','ai.model'=>'gpt-5-mini','ai.api_key'=>'DINOIKI_TEST_SECRET']); Http::preventStrayRequests(); }
    private function response(array $data): array { return ['status'=>'completed','id'=>'resp_dinoiki','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($data)]]]],'usage'=>['input_tokens'=>10,'output_tokens'=>20]]; }
    public function test_dinoiki_rubric_uses_fixed_responses_endpoint_and_preserves_schema_validation(): void {
        $this->enable();
        $source=['prompt'=>'1 - 1?','answer_key'=>'0','scoring_guidelines'=>'Two points for zero.','instructions'=>'Show steps.','max_score'=>'2.00'];
        $rules=[['rule_id'=>'R1','source'=>'scoring_guidelines','quote'=>'Two points for zero.','description'=>'Use source']];
        $criteria=[['criterion_id'=>'C1','description'=>'Correct result','evidence'=>'Written zero','scoring'=>'Two points for zero.','max_score'=>2,'rule_ids'=>['R1']]];
        Http::fake(['ai.dinoiki.com/*'=>Http::sequence()->push($this->response(['rules'=>$rules]))->push($this->response(['criteria'=>$criteria,'grading_policy'=>'Use rules.']))]);
        $result=app(RubricAi::class)->generate($source);
        $this->assertSame('dinoiki',$result['metadata']['provider']); $this->assertSame('gpt-5-mini',$result['metadata']['model']);
        $this->assertSame('R1',$result['data']['rules'][0]['rule_id']); Http::assertSentCount(2);
        Http::assertSent(fn($request)=>$request->url()==='https://ai.dinoiki.com/v1/responses' && $request->hasHeader('Authorization','Bearer DINOIKI_TEST_SECRET') && $request['model']==='gpt-5-mini' && $request['store']===false && $request['text']['format']['strict']===true);
        $this->assertSame('https://ai.dinoiki.com/v1/responses',app(AiConfiguration::class)->endpoint());
        $this->assertStringNotContainsString('DINOIKI_TEST_SECRET',json_encode(app(AiConfiguration::class)->status()));
    }
    public function test_dinoiki_vision_uses_private_bytes_and_returns_unconfirmed_draft_data(): void {
        $this->enable(); Storage::fake('answers');
        $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j8Z0AAAAASUVORK5CYII='); Storage::disk('answers')->put('synthetic.png',$bytes);
        $revision=new AnswerRevision(['content_type'=>'photo','photo_path'=>'synthetic.png','photo_mime'=>'image/png','content_hash'=>hash('sha256','photo:'.hash('sha256',$bytes)),'participant_code_snapshot'=>'NEVER_SEND_CODE','question_snapshot'=>['answer_key'=>'NEVER_SEND_KEY']]);
        Http::fake(['ai.dinoiki.com/*'=>Http::response($this->response(['transcript_text'=>'0','uncertainties'=>[]]))]);
        $data=app(TranscriptionAi::class)->generate($revision);
        $this->assertSame('0',$data['text']); $this->assertSame('dinoiki',$data['metadata']['provider']); $this->assertArrayNotHasKey('confirmed_at',$data);
        Http::assertSent(function($request) { $body=json_encode($request->data(),JSON_UNESCAPED_SLASHES); return $request->url()==='https://ai.dinoiki.com/v1/responses' && str_contains($body,'data:image/png;base64,') && !str_contains($body,'NEVER_SEND_CODE') && !str_contains($body,'NEVER_SEND_KEY'); });
    }
    public function test_unknown_provider_and_non_openai_gateway_model_never_send_key(): void {
        $this->enable(); config(['ai.provider'=>'https://untrusted.example']);
        $this->assertNull(app(AiConfiguration::class)->endpoint());
        try { app(RubricAi::class)->generate([]); $this->fail('Unknown provider accepted'); } catch(ValidationException) { $this->assertTrue(true); }
        config(['ai.provider'=>'dinoiki','ai.model'=>'google/gemini-test']); $this->assertFalse(app(AiConfiguration::class)->status()['configured']);
        try { app(RubricAi::class)->generate([]); $this->fail('Unsupported route/model accepted'); } catch(ValidationException) { $this->assertTrue(true); }
        Http::assertNothingSent();
    }
    public function test_gateway_error_refusal_incomplete_or_wrong_protocol_never_become_valid_output(): void {
        $this->enable();
        Http::fake(['ai.dinoiki.com/*'=>Http::sequence()
            ->push(['error'=>['message'=>'DINOIKI_TEST_SECRET']],401)
            ->push(['status'=>'incomplete','output'=>[]])
            ->push(['status'=>'completed','output'=>[['content'=>[['type'=>'refusal','refusal'=>'NO']]]]])
            ->push(['choices'=>[['message'=>['content'=>'{}']]]])]);
        for($i=0;$i<4;$i++) {
            try { app(RubricAi::class)->generate(['scoring_guidelines'=>'Synthetic']); $this->fail('Invalid output accepted'); }
            catch(ValidationException $e) { $this->assertStringNotContainsString('DINOIKI_TEST_SECRET',$e->getMessage()); }
        }
        Http::assertSentCount(4);
    }
}
