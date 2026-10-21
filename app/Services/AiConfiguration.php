<?php
namespace App\Services;
class AiConfiguration
{
    public function endpoint(): ?string
    {
        return match(config('ai.provider')) {
            'openai'=>'https://api.openai.com/v1/responses',
            'dinoiki'=>'https://ai.dinoiki.com/v1/responses',
            default=>null,
        };
    }
    public function label(): string { return match(config('ai.provider')) { 'dinoiki'=>'Dinoiki AI (gateway ke model OpenAI)','openai'=>'OpenAI',default=>'penyedia AI yang belum didukung' }; }
    // Return readiness only. Never serialize or expose credentials to the browser.
    public function status(): array
    {
        return [
            'enabled' => (bool) config('ai.enabled'),
            'configured' => $this->endpoint()!==null && filled(config('ai.api_key')) && filled(config('ai.model')) && (config('ai.provider')!=='dinoiki' || preg_match('/\A(?:gpt-|chatgpt-|o[0-9](?:-|$))/',config('ai.model'))===1),
            'provider' => config('ai.provider'),
            'model' => config('ai.model'),
        ];
    }
}
