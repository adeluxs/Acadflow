<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Academic Assistant Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for the AI Academic Assistant. All values can be
    | overridden at runtime via the tools_settings table (SettingService) so the
    | runtime config takes precedence over these defaults where applicable.
    |
    */

    // Default AI mode: rule_based | provider | hybrid | disabled
    'default_mode' => env('AI_DEFAULT_MODE', 'rule_based'),

    // Default provider used when mode is provider/hybrid
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'rule_based'),

    // Fallback provider used when the primary provider fails
    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'rule_based'),

    // Ordered priority list of providers (cheapest/fastest first)
    'provider_priority' => [
        'rule_based',
        'ollama',
        'deepseek',
        'gemini',
        'claude',
        'openai',
        'azure_openai',
    ],

    // Feature keys that are centrally toggleable
    'features' => [
        'submission_validator',
        'plagiarism',
        'writing_assistant',
        'citation_assistant',
        'project_assistant',
        'siwes_assistant',
        'study_assistant',
        'material_assistant',
        'lecturer_assistant',
        'discussion_assistant',
        'ai_search',
        'ai_analytics',
    ],

    // Default thresholds / limits
    'request_timeout' => env('AI_REQUEST_TIMEOUT', 30),
    'max_tokens' => env('AI_MAX_TOKENS', 2048),
    'daily_request_limit' => env('AI_DAILY_REQUEST_LIMIT', 1000),
    'monthly_request_limit' => env('AI_MONTHLY_REQUEST_LIMIT', 30000),
    'max_cost' => env('AI_MAX_COST', 100.0),

    // Plagiarism similarity threshold (percentage 0-100)
    'similarity_threshold' => env('AI_SIMILARITY_THRESHOLD', 20),

    // Supported citation styles
    'citation_styles' => ['apa', 'mla', 'chicago', 'harvard', 'ieee'],

    // Supported document formats
    'document_formats' => ['pdf', 'doc', 'docx', 'txt'],

    // Maximum document size in MB for AI analysis
    'max_document_size_mb' => env('AI_MAX_DOCUMENT_SIZE_MB', 20),

    // Toggle individual provider features
    'enable_rule_engine' => env('AI_ENABLE_RULE_ENGINE', true),
    'enable_external_ai' => env('AI_ENABLE_EXTERNAL_AI', false),
    'enable_hybrid_mode' => env('AI_ENABLE_HYBRID_MODE', true),
    'enable_cache' => env('AI_ENABLE_CACHE', true),
    'enable_logging' => env('AI_ENABLE_LOGGING', true),
    'hybrid_escalate_when_clean' => env('AI_HYBRID_ESCALATE_WHEN_CLEAN', false),

    // Async processing behaviour
    'queue_connection' => env('AI_QUEUE_CONNECTION', null), // null => app default

    // Institution-level layout requirements (overridable per-lecturer).
    // These defaults are stored in tools_settings via SettingService.
    // Null / empty values mean "no requirement enforced."
    'layout_requirements' => [
        'required_fonts' => ['Times New Roman', 'Arial'],
        'page_size' => env('AI_LAYOUT_PAGE_SIZE', 'A4'),
        'min_margin_inches' => env('AI_LAYOUT_MIN_MARGIN_INCHES', 1.0),
        'line_spacing' => env('AI_LAYOUT_LINE_SPACING', '1.5'),
        'min_font_size_pt' => env('AI_LAYOUT_MIN_FONT_SIZE', 10),
        'require_page_numbering' => env('AI_LAYOUT_REQUIRE_PAGE_NUMBERING', false),
        'require_institution_branding' => env('AI_LAYOUT_REQUIRE_BRANDING', false),
    ],

    // Providers API configuration (keys pulled from services.php / env)
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.2),
        ],
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-5-sonnet-latest'),
            'temperature' => env('CLAUDE_TEMPERATURE', 0.2),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'temperature' => env('GEMINI_TEMPERATURE', 0.2),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'temperature' => env('DEEPSEEK_TEMPERATURE', 0.2),
        ],
        'azure_openai' => [
            'api_key' => env('AZURE_OPENAI_API_KEY'),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
            'model' => env('AZURE_OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => env('AZURE_OPENAI_TEMPERATURE', 0.2),
        ],
        'ollama' => [
            'endpoint' => env('OLLAMA_ENDPOINT', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
            'temperature' => env('OLLAMA_TEMPERATURE', 0.2),
        ],
    ],

];
