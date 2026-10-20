<?php
namespace App\Services;
class AiConfiguration
{
    // Return readiness only. Never serialize or expose credentials to the browser.
    public function status(): array
    {
        return [
            'enabled' => (bool) config('ai.enabled'),
            'configured' => filled(config('ai.api_key')) && filled(config('ai.model')),
            'provider' => config('ai.provider'),
            'model' => config('ai.model'),
        ];
    }
}
