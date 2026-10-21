<?php
$provider=env('AI_PROVIDER','openai');
return [
    'enabled' => env('AI_ENABLED', false),
    'transcription_enabled' => env('AI_TRANSCRIPTION_ENABLED', false),
    'provider' => $provider,
    'model' => $provider==='dinoiki' ? (env('DINOIKI_MODEL') ?: env('AI_MODEL','')) : env('AI_MODEL',''),
    // Do not send an OpenAI key to another provider by an implicit fallback.
    'api_key' => $provider==='dinoiki' ? env('DINOIKI_API_KEY','') : env('AI_API_KEY',''),
    'max_output_tokens' => env('AI_MAX_OUTPUT_TOKENS', 4000),
];
