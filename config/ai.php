<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AcadFlow AI bootstrap defaults
    |--------------------------------------------------------------------------
    |
    | These values are installation/bootstrap fallbacks. Once AI Settings exist
    | in the database, AiRuntimeConfigService is the runtime source of truth.
    | Feature modules must not read provider selection directly from this file.
    |
    */

    'default_mode' => env('AI_DEFAULT_MODE', 'rule_based'),
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'rule_based'),
    'default_model' => env('AI_DEFAULT_MODEL', ''),
    'fallback_provider' => env('AI_FALLBACK_PROVIDER', ''),
    'fallback_model' => env('AI_FALLBACK_MODEL', ''),
    'secondary_fallback_provider' => env('AI_SECONDARY_FALLBACK_PROVIDER', ''),
    'secondary_fallback_model' => env('AI_SECONDARY_FALLBACK_MODEL', ''),

    'request_timeout' => env('AI_REQUEST_TIMEOUT', 30),
    'retry_count' => env('AI_RETRY_COUNT', 1),
    'retry_delay_ms' => env('AI_RETRY_DELAY_MS', 300),
    'fast_failover' => env('AI_FAST_FAILOVER', true),
    'temperature' => env('AI_GLOBAL_TEMPERATURE', 0.2),
    'max_tokens' => env('AI_MAX_TOKENS', 2048),
    'context_limit' => env('AI_CONTEXT_LIMIT', 16000),
    'daily_request_limit' => env('AI_DAILY_REQUEST_LIMIT', 1000),
    'monthly_request_limit' => env('AI_MONTHLY_REQUEST_LIMIT', 30000),
    'max_cost' => env('AI_MAX_COST', 100.0),
    'rate_limit_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 20),

    'similarity_threshold' => env('AI_SIMILARITY_THRESHOLD', 20),
    'citation_styles' => ['apa', 'mla', 'chicago', 'harvard', 'ieee', 'vancouver'],
    'cache_ttl' => env('AI_CACHE_TTL', 86400),
    'enable_cache' => env('AI_ENABLE_CACHE', true),
    'enable_logging' => env('AI_ENABLE_LOGGING', true),
    'hybrid_escalate_when_clean' => env('AI_HYBRID_ESCALATE_WHEN_CLEAN', false),
    'queue_connection' => env('AI_QUEUE_CONNECTION', null),

    // Shared provider HTTP transport. These are infrastructure controls, not
    // provider-routing settings, so database AI Settings still remain the
    // runtime source of truth for provider/model selection.
    'http' => [
        'connect_timeout' => env('AI_HTTP_CONNECT_TIMEOUT', 6),
        'ca_bundle' => env('AI_HTTP_CA_BUNDLE', ''),
        'proxy' => env('AI_HTTP_PROXY', ''),
        'force_ipv4' => env('AI_HTTP_FORCE_IPV4', false),
        'verify_tls' => env('AI_HTTP_VERIFY_TLS', true),
    ],

    // Feature keys currently present in the AcadFlow AI architecture.
    // Runtime-configurable AI features that have actual application entry
    // points in this build. Do not add dormant/future labels here: every item
    // shown in AI Settings must have a real consumer.
    'features' => [
        'submission_validator',
        'plagiarism',
        'writing_assistant',
        'citation_assistant',
        'study_assistant',
        'lecturer_assistant',
        'research_assistant',
        'research_validator',
        'assignment_assistant',
        'siwes_assistant',
        'project_assistant',
        'material_assistant',
        'discussion_assistant',
        'knowledge_publication_validator',
        'knowledge_moderation',
        'knowledge_companion',
    ],

    // In Hybrid mode these features are genuinely provider-backed when a valid
    // provider is configured. Rules remain validation/guardrails/fallback only.
    'provider_first_features' => [
        'submission_validator',
        'writing_assistant',
        'citation_assistant',
        'study_assistant',
        'lecturer_assistant',
        'research_assistant',
        'assignment_assistant',
        'siwes_assistant',
        'project_assistant',
        'material_assistant',
        'discussion_assistant',
        'research_validator',
        'knowledge_publication_validator',
        'knowledge_moderation',
        'knowledge_companion',
    ],


    // External provider features currently use structured JSON chat responses.
    // Local-only search/indexing features do not require an external provider.
    'feature_capabilities' => [
        'submission_validator' => ['chat', 'structured_output'],
        'plagiarism' => ['chat', 'structured_output'],
        'writing_assistant' => ['chat', 'structured_output'],
        'citation_assistant' => ['chat', 'structured_output'],
        'study_assistant' => ['chat', 'structured_output'],
        'lecturer_assistant' => ['chat', 'structured_output'],
        'research_assistant' => ['chat', 'structured_output'],
        'assignment_assistant' => ['chat', 'structured_output'],
        'siwes_assistant' => ['chat', 'structured_output'],
        'project_assistant' => ['chat', 'structured_output'],
        'material_assistant' => ['chat', 'structured_output'],
        'discussion_assistant' => ['chat', 'structured_output'],
        'research_validator' => ['chat', 'structured_output'],
        'knowledge_publication_validator' => ['chat', 'structured_output'],
        'knowledge_moderation' => ['chat', 'structured_output'],
        'knowledge_companion' => ['chat', 'structured_output'],
    ],


    /*
    | User-facing specialized assistant metadata. This is presentation/context
    | metadata only; provider/model routing still comes exclusively from the
    | centralized runtime settings and AiRouter.
    */
    'assistant_profiles' => [
        'research_assistant' => [
            'label' => 'Research Assistant',
            'module' => 'Research Studio',
            'description' => 'Research planning, methodology, evidence interpretation, source-aware writing guidance and revision support.',
            'suggestions' => [
                'Review my current research progress and suggest the next three steps.',
                'What weaknesses do you see in my methodology and how can I improve them?',
                'Help me distinguish evidence from interpretation in this research.',
            ],
        ],
        'assignment_assistant' => [
            'label' => 'Assignment Assistant',
            'module' => 'Assignments',
            'description' => 'Understand assignment requirements, plan the work, learn the concepts and self-check against the rubric without replacing the student\'s work.',
            'suggestions' => [
                'Break this assignment into a step-by-step plan.',
                'Explain what the assignment is asking me to demonstrate.',
                'Create a self-checklist from the instructions and rubric.',
            ],
        ],
        'siwes_assistant' => [
            'label' => 'SIWES Assistant',
            'module' => 'SIWES',
            'description' => 'Improve reflection, logbook quality, report structure, skills articulation and evaluation preparation using real placement records.',
            'suggestions' => [
                'Review my recent SIWES logs and suggest how to make the reflection stronger.',
                'Help me organize my SIWES report from the real activities recorded here.',
                'What skills and learning outcomes are supported by my current logbook?',
            ],
        ],
        'project_assistant' => [
            'label' => 'Project Assistant',
            'module' => 'Projects',
            'description' => 'Project structure review, methodology reasoning, revision planning, evidence checks and defense preparation.',
            'suggestions' => [
                'Review this project and identify the most important areas to improve.',
                'Give me likely defense questions based on my current project.',
                'Check whether my project structure and argument are coherent.',
            ],
        ],
        'material_assistant' => [
            'label' => 'Material / Study Assistant',
            'module' => 'Courses',
            'description' => 'Explain the current course material, connect concepts across authorized course resources and support revision.',
            'suggestions' => [
                'Explain the main ideas in this material in simpler terms.',
                'Create five study questions from this material.',
                'What concepts should I understand before moving to the next topic?',
            ],
        ],
        'discussion_assistant' => [
            'label' => 'Discussion Assistant',
            'module' => 'Discussions',
            'description' => 'Summarize viewpoints, identify unresolved questions and help compose constructive evidence-aware contributions.',
            'suggestions' => [
                'Summarize the main viewpoints in this discussion.',
                'What questions are still unresolved in this thread?',
                'Help me draft a constructive reply without inventing facts.',
            ],
        ],
    ],


    'layout_requirements' => [
        'required_fonts' => ['Times New Roman', 'Arial'],
        'page_size' => env('AI_LAYOUT_PAGE_SIZE', 'A4'),
        'min_margin_inches' => env('AI_LAYOUT_MIN_MARGIN_INCHES', 1.0),
        'line_spacing' => env('AI_LAYOUT_LINE_SPACING', '1.5'),
        'min_font_size_pt' => env('AI_LAYOUT_MIN_FONT_SIZE', 10),
        'require_page_numbering' => env('AI_LAYOUT_REQUIRE_PAGE_NUMBERING', false),
        'require_institution_branding' => env('AI_LAYOUT_REQUIRE_BRANDING', false),
    ],

    /*
    | Provider secrets remain valid secure bootstrap fallbacks. Administrators
    | may also save encrypted provider credentials in AI Settings. Database AI
    | settings take priority without requiring config:cache to be rebuilt.
    */
    // Known retired provider model identifiers that should no longer be
    // routed at runtime. The mapping is intentionally narrow and only covers
    // models whose upstream provider has shut them down. Administrator-selected
    // supported/custom model identifiers are otherwise preserved unchanged.
    'retired_model_replacements' => [
        'gemini' => [
            'gemini-1.5-flash' => 'gemini-3.6-flash',
            'gemini-1.5-flash-001' => 'gemini-3.6-flash',
            'gemini-1.5-flash-002' => 'gemini-3.6-flash',
            'gemini-1.5-flash-latest' => 'gemini-3.6-flash',
            'gemini-1.5-pro' => 'gemini-3.1-pro-preview',
            'gemini-1.5-pro-001' => 'gemini-3.1-pro-preview',
            'gemini-1.5-pro-002' => 'gemini-3.1-pro-preview',
            'gemini-1.5-pro-latest' => 'gemini-3.1-pro-preview',
            'gemini-2.0-flash' => 'gemini-3.6-flash',
            'gemini-2.0-flash-001' => 'gemini-3.6-flash',
            'gemini-2.0-flash-lite' => 'gemini-3.5-flash-lite',
            'gemini-2.0-flash-lite-001' => 'gemini-3.5-flash-lite',
        ],
    ],

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.2),
            'input_cost_per_million' => env('OPENAI_INPUT_COST_PER_MILLION', 0.15),
            'output_cost_per_million' => env('OPENAI_OUTPUT_COST_PER_MILLION', 0.60),
        ],
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-5'),
            'temperature' => env('CLAUDE_TEMPERATURE', 0.2),
            'input_cost_per_million' => env('CLAUDE_INPUT_COST_PER_MILLION', 3.00),
            'output_cost_per_million' => env('CLAUDE_OUTPUT_COST_PER_MILLION', 15.00),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
            'temperature' => env('GEMINI_TEMPERATURE', 0.2),
            'input_cost_per_million' => env('GEMINI_INPUT_COST_PER_MILLION', 0.10),
            'output_cost_per_million' => env('GEMINI_OUTPUT_COST_PER_MILLION', 0.40),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
            'temperature' => env('DEEPSEEK_TEMPERATURE', 0.2),
            'input_cost_per_million' => env('DEEPSEEK_INPUT_COST_PER_MILLION', 0.27),
            'output_cost_per_million' => env('DEEPSEEK_OUTPUT_COST_PER_MILLION', 1.10),
        ],
        'grok' => [
            'api_key' => env('XAI_API_KEY'),
            'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
            'model' => env('XAI_MODEL', 'grok-4.5'),
            'temperature' => env('XAI_TEMPERATURE', 0.2),
            // Keep cost tracking at zero unless the administrator explicitly
            // configures current xAI prices for the selected model.
            'input_cost_per_million' => env('XAI_INPUT_COST_PER_MILLION', 0),
            'output_cost_per_million' => env('XAI_OUTPUT_COST_PER_MILLION', 0),
        ],
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            'temperature' => env('OPENROUTER_TEMPERATURE', 0.2),
            'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
            'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'AcadFlow')),
            // OpenRouter can return usage.cost. Static rates are only a fallback.
            'input_cost_per_million' => env('OPENROUTER_INPUT_COST_PER_MILLION', 0),
            'output_cost_per_million' => env('OPENROUTER_OUTPUT_COST_PER_MILLION', 0),
        ],
        'azure_openai' => [
            'api_key' => env('AZURE_OPENAI_API_KEY'),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'model' => env('AZURE_OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => env('AZURE_OPENAI_TEMPERATURE', 0.2),
            'input_cost_per_million' => env('AZURE_OPENAI_INPUT_COST_PER_MILLION', 0.15),
            'output_cost_per_million' => env('AZURE_OPENAI_OUTPUT_COST_PER_MILLION', 0.60),
        ],
        'ollama' => [
            'endpoint' => env('OLLAMA_ENDPOINT', 'http://localhost:11434'),
            'api_key' => env('OLLAMA_API_KEY'),
            'model' => env('OLLAMA_MODEL', 'llama3'),
            'temperature' => env('OLLAMA_TEMPERATURE', 0.2),
            'input_cost_per_million' => env('OLLAMA_INPUT_COST_PER_MILLION', 0),
            'output_cost_per_million' => env('OLLAMA_OUTPUT_COST_PER_MILLION', 0),
        ],
    ],

];
