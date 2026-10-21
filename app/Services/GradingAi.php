<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class GradingAi
{
    public function ready(array $configuration): void
    {
        $expected=['pipeline_version'=>'m05_v1','provider'=>config('ai.provider'),'model'=>config('ai.model'),
            'enabled'=>(bool)config('ai.enabled'),'max_output_tokens'=>(int)config('ai.max_output_tokens',4000)];
        ksort($configuration); ksort($expected);
        if ($configuration!==$expected || !$expected['enabled'] || !app(AiConfiguration::class)->status()['configured']) {
            throw new GradingConfigurationException('Konfigurasi proses tidak sesuai. Buat proses baru dengan konfigurasi aktif.');
        }
    }

    public function call(string $stage,string $instructions,array $input,array $schema,array $configuration): array
    {
        $this->ready($configuration);
        try {
            $response=app(AiAudit::class)->request($stage,'m05_v1'.($configuration['provider']==='dinoiki'?'_dinoiki_contract':''),fn()=>Http::withToken(config('ai.api_key'))->acceptJson()->connectTimeout(10)->timeout(45)
                ->withOptions(['allow_redirects'=>false])->post(app(AiConfiguration::class)->endpoint(),[
                    'model'=>$configuration['model'],'store'=>false,
                    'instructions'=>app(AiConfiguration::class)->instructions($instructions,$schema),
                    'input'=>json_encode(['untrusted_data'=>$input],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    'max_output_tokens'=>max(1000,min(8000,$configuration['max_output_tokens'])),
                    'text'=>['format'=>['type'=>'json_schema','name'=>'m05_'.$stage,'strict'=>true,'schema'=>$schema]],
                ]));
            if(!$response->successful() || strlen($response->body())>1000000 || $response->json('status')!=='completed') throw new \RuntimeException();
            $text='';
            foreach($response->json('output',[]) as $item) foreach($item['content'] ?? [] as $part) {
                if(($part['type'] ?? '')==='refusal') throw new \RuntimeException();
                if(($part['type'] ?? '')==='output_text') $text.=$part['text'] ?? '';
            }
            $data=json_decode($text,true,64,JSON_THROW_ON_ERROR);
            if(!is_array($data) || array_is_list($data)) throw new \UnexpectedValueException();
            $usage=[];
            foreach(['input_tokens','output_tokens','total_tokens'] as $key) {
                $value=$response->json('usage.'.$key);
                if(is_int($value) && $value>=0) $usage[$key]=$value;
            }
            return ['data'=>$data,'metadata'=>['provider'=>$configuration['provider'],'model'=>$configuration['model'],
                'prompt_version'=>'m05_v1'.($configuration['provider']==='dinoiki'?'_dinoiki_contract':''),
                'usage'=>$usage]];
        } catch(\Throwable $e) {
            // Do not retain provider exceptions, headers, bodies, credentials or partial results.
            throw new \RuntimeException('Layanan AI tidak menghasilkan keluaran yang dapat dipakai.');
        }
    }
}
