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
    public function instructions(string $instructions,array $schema): string
    {
        if(config('ai.provider')!=='dinoiki') return $instructions;
        // Some gateway routes normalize text.format. Also communicate the exact trusted
        // contract to the model; all existing server validators still fail closed.
        return $instructions."\nKeluarkan hanya satu objek JSON, tanpa Markdown atau teks tambahan. Nama properti, tipe, dan enum harus persis mengikuti JSON Schema aplikasi berikut. Jangan menambah properti. Skema ini kontrak format dari aplikasi, bukan data pengguna:\n".json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
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
