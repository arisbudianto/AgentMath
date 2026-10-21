<?php
namespace App\Services;
use App\Models\{AiCall,Assessment};
use Illuminate\Http\Client\Response;

class AiAudit {
    private array $contexts=[];
    public function within(Assessment $assessment,string $kind,int $source,string $correlation,callable $operation): mixed {
        if(!in_array($kind,['rubric','transcription','grading','baseline'],true)) throw new \LogicException('Jenis audit tidak dikenal.');
        $this->contexts[]=['assessment_id'=>$assessment->id,'kind'=>$kind,'source_id'=>$source,'correlation_id'=>$correlation,'calls'=>[]];
        $outcome='rejected';
        try { $result=$operation(); $outcome='validated'; return $result; }
        finally {
            $context=array_pop($this->contexts);
            foreach($context['calls'] as $id) AiCall::findOrFail($id)->update(['outcome'=>$outcome]);
        }
    }
    public function tariff(string $provider,string $model): ?array {
        $c=config('audit');
        if($c['provider']!==$provider || $c['model']!==$model || !preg_match('/^[A-Z]{3}$/D',(string)$c['currency']) || !preg_match('/^[A-Za-z0-9._-]{1,80}$/D',(string)$c['version'])) return null;
        foreach(['input','cached','output'] as $k) if(!is_scalar($c[$k]) || !preg_match('/^\d{1,5}(?:\.\d{1,6})?$/D',(string)$c[$k]) || (float)$c[$k]>10000) return null;
        return ['version'=>$c['version'],'currency'=>$c['currency'],'input'=>(string)$c['input'],'cached'=>(string)$c['cached'],'output'=>(string)$c['output'],'unit'=>'per_million_tokens'];
    }
    public function cost(?array $tariff,?int $input,?int $output,?int $cached): ?string {
        if(!$tariff || $input===null || $output===null || ($cached===null && (float)$tariff['input']!==(float)$tariff['cached'])) return null;
        $cached ??= 0;
        return number_format((($input-$cached)*(float)$tariff['input']+$cached*(float)$tariff['cached']+$output*(float)$tariff['output'])/1000000,12,'.','');
    }
    private function tokens(mixed $value): ?int { return is_int($value) && $value>=0 && $value<=1000000000 ? $value : null; }
    public function request(string $stage,string $prompt,callable $send): Response {
        // Direct adapter tests may run without a domain operation. All application entrypoints supply one.
        if(!$this->contexts) return $send();
        $index=array_key_last($this->contexts); $context=$this->contexts[$index]; unset($context['calls']);
        $provider=(string)config('ai.provider'); $model=(string)config('ai.model'); $tariff=$this->tariff($provider,$model);
        $record=AiCall::create($context+['stage'=>$stage,'provider'=>$provider,'model'=>$model,'prompt_version'=>$prompt,'tariff'=>$tariff,'currency'=>$tariff['currency'] ?? null,'state'=>'in_flight','outcome'=>'pending','started_at'=>now()]);
        $this->contexts[$index]['calls'][]=$record->id; $start=microtime(true); $response=null;
        try { $response=$send(); return $response; }
        finally {
            $input=$output=$cached=null;
            if($response && strlen($response->body())<=1000000) {
                $input=$this->tokens($response->json('usage.input_tokens')); $output=$this->tokens($response->json('usage.output_tokens'));
                $cached=$this->tokens($response->json('usage.input_tokens_details.cached_tokens'));
                if($input===null || ($cached!==null && $cached>$input)) $cached=null;
            }
            $record->update(['state'=>$response?'response_received':'transport_failed','http_status'=>$response?->status(),
                'duration_ms'=>max(0,(int)round((microtime(true)-$start)*1000)),'input_tokens'=>$input,'output_tokens'=>$output,'cached_tokens'=>$cached,
                'estimated_cost'=>$this->cost($tariff,$input,$output,$cached),'finished_at'=>now()]);
        }
    }
}
