<?php
return [
    'enabled' => env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai'),
    'model' => env('AI_MODEL', ''),
    'api_key' => env('AI_API_KEY', ''),
    'max_output_tokens' => env('AI_MAX_OUTPUT_TOKENS', 4000),
];
