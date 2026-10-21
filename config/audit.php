<?php
// Optional operator-supplied rates per one million tokens. No price is assumed.
return [
    'provider'=>env('AI_PRICE_PROVIDER',''), 'model'=>env('AI_PRICE_MODEL',''),
    'currency'=>env('AI_PRICE_CURRENCY',''), 'version'=>env('AI_PRICE_VERSION',''),
    'input'=>env('AI_PRICE_INPUT_PER_MILLION'), 'cached'=>env('AI_PRICE_CACHED_PER_MILLION'),
    'output'=>env('AI_PRICE_OUTPUT_PER_MILLION'),
];
