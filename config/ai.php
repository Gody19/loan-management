<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Feature Switch
    |--------------------------------------------------------------------------
    |
    | AI is an optional service. When disabled, the application never calls a
    | provider and every AI-aware endpoint returns a controlled "unavailable"
    | response. FinancePro's existing features never depend on this switch.
    |
    */

    'enabled' => (bool) env('AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default Provider & Model
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('AI_PROVIDER', 'openai'),

    'default_model' => env('AI_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Request Behaviour
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('AI_TIMEOUT', 30),

    'temperature' => (float) env('AI_TEMPERATURE', 0.7),

    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1024),

    /*
    |--------------------------------------------------------------------------
    | Conversation History
    |--------------------------------------------------------------------------
    |
    | Bounds how many stored messages are sent to the provider per request.
    | History is pruned oldest-first past this window.
    |
    */

    'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 12),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Provider credentials come exclusively from environment variables and are
    | never hard-coded. Each provider is isolated behind AiProviderInterface.
    |
    */

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('AI_MODEL', 'gpt-4o-mini'),
        ],

        // Offline provider used by tests and as a safe demo default.
        'fake' => [
            'model' => env('AI_MODEL', 'fake-model-1'),
        ],
    ],

];