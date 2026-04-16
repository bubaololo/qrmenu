<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Cascade Tiers
    |--------------------------------------------------------------------------
    |
    | Ordered lists of LLM providers to try for menu analysis. On failure,
    | the next provider in the tier is attempted. Tiers are selected based
    | on image count (small vs large packs).
    |
    | provider: 'gemini' | 'openrouter' | 'qwen-direct'
    |   gemini       → Prism Provider::Gemini
    |   openrouter   → Prism Provider::OpenRouter
    |   qwen-direct  → Direct HTTP to DashScope /chat/completions
    |
    */

    'tiers' => [
        'small' => [
            ['provider' => 'qwen-direct', 'model' => 'qwen3-vl-plus-2025-12-19'],
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            ['provider' => 'openrouter', 'model' => 'google/gemma-4-26b-a4b-it:free'],
        ],
        'large' => [
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            ['provider' => 'openrouter', 'model' => 'google/gemma-4-26b-a4b-it:free'],
        ],
    ],

    'thresholds' => [
        'small_max_images' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => env('LLM_QUEUE', 'llm-analysis'),
    'job_timeout' => (int) env('LLM_JOB_TIMEOUT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Token Pricing (cents per 1M tokens)
    |--------------------------------------------------------------------------
    */

    'pricing' => [
        'gemini-2.5-flash' => ['input' => 15, 'output' => 125],
        'qwen3-vl-plus' => ['input' => 20, 'output' => 160],
        'qwen3-vl-plus-2025-12-19' => ['input' => 20, 'output' => 160],
        'google/gemma-4-26b-a4b-it' => ['input' => 7, 'output' => 40],
        'google/gemma-4-26b-a4b-it:free' => ['input' => 0, 'output' => 0],
    ],

];
