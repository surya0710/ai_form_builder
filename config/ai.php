<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Controls which configured provider is resolved for AI form generation.
    | Supported: "openai". Additional providers can be registered and selected
    | via AI_PROVIDER without changing application services.
    |
    */

    'default' => env('AI_PROVIDER', 'openai'),

    'retry' => [
        'max_attempts' => (int) env('AI_PARSE_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Provider-specific settings. Secrets must come from the environment.
    | Do not hardcode API keys or other credentials here.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => (float) env('AI_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),
            'timeout' => (int) env('AI_TIMEOUT', 30),
            'retries' => (int) env('AI_RETRIES', 2),
            'retry_delay' => (int) env('AI_RETRY_DELAY', 1000),
        ],

    ],

];
