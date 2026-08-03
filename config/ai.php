<?php

use App\Domain\Ai\Providers\OpenAiCompatibleProvider;

return [

    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'max_turns_per_conversation' => (int) env('AI_MAX_TURNS_PER_CONVERSATION', 40),

    'transcription' => [
        'enabled' => (bool) env('AI_TRANSCRIPTION_ENABLED', true),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('AI_TRANSCRIPTION_MODEL', 'whisper-1'),
        'language' => env('AI_TRANSCRIPTION_LANGUAGE', 'pt'),
    ],

    /**
     * Centavos de dólar por 1M de tokens.
     */
    'pricing' => [
        'gpt-4o-mini' => ['input' => 15, 'output' => 60],
        'gpt-5.4-mini' => ['input' => 75, 'output' => 450],
    ],

    'providers' => [

        'openai' => [
            'label' => 'ChatGPT',
            'class' => OpenAiCompatibleProvider::class,
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'models' => ['gpt-4o-mini', 'gpt-5.4-mini'],
            'token_param' => 'max_completion_tokens',

            /**
             * Modelos de raciocínio da linha gpt-5 recusam qualquer temperatura
             * fora do padrão. Nenhum dos modelos atuais está nessa situação.
             */
            'fixed_temperature_models' => [],
        ],

    ],

];
