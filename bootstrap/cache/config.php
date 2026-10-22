<?php return array (
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => '/Users/user/Desktop/acadflow/acadflow-web/resources/views',
    ),
    'compiled' => '/Users/user/Desktop/acadflow/acadflow-web/storage/framework/views',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => false,
  ),
  'academic_adoption' => 
  array (
    'plans' => 
    array (
      'department_core' => 
      array (
        'name' => 'Department Core',
        'scope' => 'department',
        'description' => 'Department administration and core academic work. Paid features use existing pricing.',
      ),
      'department_sponsored' => 
      array (
        'name' => 'Department Sponsored',
        'scope' => 'department',
        'description' => 'Request approved AI or paid-feature sponsorship for verified members.',
      ),
      'institution_core' => 
      array (
        'name' => 'Institution Core',
        'scope' => 'institution',
        'description' => 'Institution administration and affiliated departments.',
      ),
      'institution_sponsored' => 
      array (
        'name' => 'Institution Sponsored',
        'scope' => 'institution',
        'description' => 'Approved institution-wide commercial sponsorship.',
      ),
    ),
    'classifications' => 
    array (
      0 => 'undergraduate',
      1 => 'postgraduate',
      2 => 'mixed',
      3 => 'polytechnic',
      4 => 'college',
      5 => 'professional',
      6 => 'research',
    ),
    'invitation_days' => 7,
  ),
  'academic_catalog' => 
  array (
    'remote_sync' => true,
    'seed_templates' => true,
    'http_timeout' => 20,
    'nuc' => 
    array (
      'federal' => 'https://www.nuc.edu.ng/nigerian-univerisities/federal-univeristies/',
      'state' => 'https://www.nuc.edu.ng/nigerian-univerisities/state-univeristies/',
      'private' => 'https://www.nuc.edu.ng/nigerian-univerisities/private-univeristies/',
    ),
    'nbte' => 
    array (
      'polytechnics' => 'https://www.digitalnbte.nbte.gov.ng/Public/PUCPolytechnics',
      'approved_index' => 'https://www.web.nbte.gov.ng/tvet%20institutions',
    ),
    'fallback' => 
    array (
      'institutions_csv' => 'https://raw.githubusercontent.com/awesomegoodman/higher-institutions-ng/main/resources/NARR/NARR%20institution.csv',
    ),
  ),
  'ai' => 
  array (
    'default_mode' => 'rule_based',
    'default_provider' => 'rule_based',
    'default_model' => '',
    'fallback_provider' => '',
    'fallback_model' => '',
    'secondary_fallback_provider' => '',
    'secondary_fallback_model' => '',
    'request_timeout' => '18',
    'lifecycle_budget_seconds' => '24',
    'post_provider_reserve_seconds' => '4',
    'health_check_budget_seconds' => 12,
    'health_check_timeout' => 6,
    'health_check_connect_timeout' => 3,
    'retry_count' => '1',
    'retry_delay_ms' => '300',
    'fast_failover' => true,
    'temperature' => '0.2',
    'max_tokens' => '2048',
    'context_limit' => '16000',
    'daily_request_limit' => '1000',
    'monthly_request_limit' => '30000',
    'max_cost' => '100.0',
    'rate_limit_per_minute' => '20',
    'similarity_threshold' => '20',
    'citation_styles' => 
    array (
      0 => 'apa',
      1 => 'mla',
      2 => 'chicago',
      3 => 'harvard',
      4 => 'ieee',
      5 => 'vancouver',
    ),
    'cache_ttl' => '86400',
    'enable_cache' => true,
    'enable_logging' => true,
    'defer_analytics_after_response' => true,
    'contextual_ui_timeout_ms' => '28000',
    'hybrid_escalate_when_clean' => false,
    'queue_connection' => '',
    'http' => 
    array (
      'connect_timeout' => '6',
      'ca_bundle' => '',
      'proxy' => '',
      'force_ipv4' => false,
      'verify_tls' => true,
    ),
    'features' => 
    array (
      0 => 'submission_validator',
      1 => 'plagiarism',
      2 => 'writing_assistant',
      3 => 'citation_assistant',
      4 => 'study_assistant',
      5 => 'lecturer_assistant',
      6 => 'research_assistant',
      7 => 'research_validator',
      8 => 'assignment_assistant',
      9 => 'siwes_assistant',
      10 => 'project_assistant',
      11 => 'material_assistant',
      12 => 'discussion_assistant',
      13 => 'knowledge_publication_validator',
      14 => 'knowledge_moderation',
      15 => 'knowledge_companion',
      16 => 'study_planner',
      17 => 'practice_generator',
      18 => 'flashcard_generator',
      19 => 'reflection_coach',
      20 => 'annotation_summary',
    ),
    'provider_first_features' => 
    array (
      0 => 'submission_validator',
      1 => 'writing_assistant',
      2 => 'citation_assistant',
      3 => 'study_assistant',
      4 => 'lecturer_assistant',
      5 => 'research_assistant',
      6 => 'assignment_assistant',
      7 => 'siwes_assistant',
      8 => 'project_assistant',
      9 => 'material_assistant',
      10 => 'discussion_assistant',
      11 => 'research_validator',
      12 => 'knowledge_publication_validator',
      13 => 'knowledge_moderation',
      14 => 'knowledge_companion',
      15 => 'study_planner',
      16 => 'practice_generator',
      17 => 'flashcard_generator',
      18 => 'reflection_coach',
      19 => 'annotation_summary',
    ),
    'feature_capabilities' => 
    array (
      'submission_validator' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'plagiarism' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'writing_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'citation_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'study_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'lecturer_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'research_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'assignment_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'siwes_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'project_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'material_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'discussion_assistant' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'research_validator' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'knowledge_publication_validator' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'knowledge_moderation' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'knowledge_companion' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'study_planner' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'practice_generator' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'flashcard_generator' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'reflection_coach' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
      'annotation_summary' => 
      array (
        0 => 'chat',
        1 => 'structured_output',
      ),
    ),
    'assistant_profiles' => 
    array (
      'research_assistant' => 
      array (
        'label' => 'Research Assistant',
        'module' => 'Research Studio',
        'description' => 'Research planning, methodology, evidence interpretation, source-aware writing guidance and revision support.',
        'suggestions' => 
        array (
          0 => 'Review my current research progress and suggest the next three steps.',
          1 => 'What weaknesses do you see in my methodology and how can I improve them?',
          2 => 'Help me distinguish evidence from interpretation in this research.',
        ),
      ),
      'assignment_assistant' => 
      array (
        'label' => 'Assignment Assistant',
        'module' => 'Assignments',
        'description' => 'Understand assignment requirements, plan the work, learn the concepts and self-check against the rubric without replacing the student\'s work.',
        'suggestions' => 
        array (
          0 => 'Break this assignment into a step-by-step plan.',
          1 => 'Explain what the assignment is asking me to demonstrate.',
          2 => 'Create a self-checklist from the instructions and rubric.',
        ),
      ),
      'siwes_assistant' => 
      array (
        'label' => 'SIWES Assistant',
        'module' => 'SIWES',
        'description' => 'Improve reflection, logbook quality, report structure, skills articulation and evaluation preparation using real placement records.',
        'suggestions' => 
        array (
          0 => 'Review my recent SIWES logs and suggest how to make the reflection stronger.',
          1 => 'Help me organize my SIWES report from the real activities recorded here.',
          2 => 'What skills and learning outcomes are supported by my current logbook?',
        ),
      ),
      'project_assistant' => 
      array (
        'label' => 'Project Assistant',
        'module' => 'Projects',
        'description' => 'Project structure review, methodology reasoning, revision planning, evidence checks and defense preparation.',
        'suggestions' => 
        array (
          0 => 'Review this project and identify the most important areas to improve.',
          1 => 'Give me likely defense questions based on my current project.',
          2 => 'Check whether my project structure and argument are coherent.',
        ),
      ),
      'material_assistant' => 
      array (
        'label' => 'Material / Study Assistant',
        'module' => 'Courses',
        'description' => 'Explain the current course material, connect concepts across authorized course resources and support revision.',
        'suggestions' => 
        array (
          0 => 'Explain the main ideas in this material in simpler terms.',
          1 => 'Create five study questions from this material.',
          2 => 'What concepts should I understand before moving to the next topic?',
        ),
      ),
      'discussion_assistant' => 
      array (
        'label' => 'Discussion Assistant',
        'module' => 'Discussions',
        'description' => 'Summarize viewpoints, identify unresolved questions and help compose constructive evidence-aware contributions.',
        'suggestions' => 
        array (
          0 => 'Summarize the main viewpoints in this discussion.',
          1 => 'What questions are still unresolved in this thread?',
          2 => 'Help me draft a constructive reply without inventing facts.',
          3 => 'Create a short study-group summary with the key points and agreed next steps.',
          4 => 'List the action items this group should complete before the next session.',
        ),
      ),
    ),
    'layout_requirements' => 
    array (
      'required_fonts' => 
      array (
        0 => 'Times New Roman',
        1 => 'Arial',
      ),
      'page_size' => 'A4',
      'min_margin_inches' => '1.0',
      'line_spacing' => '1.5',
      'min_font_size_pt' => '10',
      'require_page_numbering' => false,
      'require_institution_branding' => false,
    ),
    'retired_model_replacements' => 
    array (
      'gemini' => 
      array (
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
      ),
      'openrouter' => 
      array (
        'google/gemini-3.5-flash' => 'google/gemini-2.5-flash',
        'google/gemini-3.5-flash-latest' => 'google/gemini-2.5-flash',
        'google/gemini-3.8-flash' => 'google/gemini-2.5-flash',
        'google/gemini-3.8-flash-latest' => 'google/gemini-2.5-flash',
      ),
    ),
    'providers' => 
    array (
      'openai' => 
      array (
        'api_key' => '',
        'base_url' => 'https://api.openai.com/v1',
        'organization' => '',
        'project' => '',
        'model' => 'gpt-4o-mini',
        'models' => 
        array (
          0 => 'gpt-4o-mini',
          1 => 'gpt-4o',
          2 => 'gpt-4.1-mini',
          3 => 'gpt-4.1',
          4 => 'o4-mini',
          5 => 'o3-mini',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '0.15',
        'output_cost_per_million' => '0.60',
      ),
      'claude' => 
      array (
        'api_key' => '',
        'base_url' => 'https://api.anthropic.com/v1',
        'api_version' => '2023-06-01',
        'model' => 'claude-sonnet-5',
        'models' => 
        array (
          0 => 'claude-sonnet-5',
          1 => 'claude-3-7-sonnet-latest',
          2 => 'claude-3-5-sonnet-latest',
          3 => 'claude-3-5-haiku-latest',
          4 => 'claude-3-opus-latest',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '3.00',
        'output_cost_per_million' => '15.00',
      ),
      'gemini' => 
      array (
        'api_key' => '',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => 'gemini-3.6-flash',
        'models' => 
        array (
          0 => 'gemini-3.6-flash',
          1 => 'gemini-3.5-flash-lite',
          2 => 'gemini-3.1-pro-preview',
          3 => 'gemini-2.5-flash',
          4 => 'gemini-2.5-pro',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '0.10',
        'output_cost_per_million' => '0.40',
      ),
      'deepseek' => 
      array (
        'api_key' => '',
        'base_url' => 'https://api.deepseek.com',
        'model' => 'deepseek-v4-flash',
        'models' => 
        array (
          0 => 'deepseek-v4-flash',
          1 => 'deepseek-chat',
          2 => 'deepseek-reasoner',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '0.27',
        'output_cost_per_million' => '1.10',
      ),
      'grok' => 
      array (
        'api_key' => '',
        'base_url' => 'https://api.x.ai/v1',
        'model' => 'grok-4.5',
        'models' => 
        array (
          0 => 'grok-4.5',
          1 => 'grok-4',
          2 => 'grok-3',
          3 => 'grok-3-mini',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '0',
        'output_cost_per_million' => '0',
      ),
      'openrouter' => 
      array (
        'api_key' => '',
        'base_url' => 'https://openrouter.ai/api/v1',
        'model' => 'openai/gpt-4o-mini',
        'models' => 
        array (
          0 => 'openai/gpt-4o-mini',
          1 => 'openai/gpt-4o',
          2 => 'anthropic/claude-sonnet-5',
          3 => 'anthropic/claude-3.7-sonnet',
          4 => 'google/gemini-2.5-flash',
          5 => 'deepseek/deepseek-chat',
          6 => 'x-ai/grok-4',
        ),
        'temperature' => '0.2',
        'site_url' => 'http://127.0.0.1:8001',
        'app_name' => 'AcadSyntra',
        'input_cost_per_million' => '0',
        'output_cost_per_million' => '0',
      ),
      'azure_openai' => 
      array (
        'api_key' => '',
        'endpoint' => '',
        'api_version' => '2024-10-21',
        'model' => 'gpt-4o-mini',
        'models' => 
        array (
          0 => 'gpt-4o-mini',
          1 => 'gpt-4o',
          2 => 'gpt-4.1-mini',
          3 => 'gpt-4.1',
          4 => 'o4-mini',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '0.15',
        'output_cost_per_million' => '0.60',
      ),
      'ollama' => 
      array (
        'endpoint' => 'http://localhost:11434',
        'api_key' => '',
        'model' => 'llama3',
        'models' => 
        array (
          0 => 'llama3',
          1 => 'llama3.1',
          2 => 'llama3.2',
          3 => 'mistral',
          4 => 'mixtral',
          5 => 'qwen2.5',
          6 => 'phi3',
        ),
        'temperature' => '0.2',
        'input_cost_per_million' => '0',
        'output_cost_per_million' => '0',
      ),
    ),
  ),
  'app' => 
  array (
    'name' => 'AcadSyntra',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://127.0.0.1:8001',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'Africa/Lagos',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:dWJi6PDS9CZjIuZ4kKaf+VEQm5P1JgTl/NqgKgAjyvI=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
      24 => 'App\\Providers\\AuthServiceProvider',
      25 => 'App\\Providers\\EventServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => '10800',
    'two_factor_session_hours' => '12',
  ),
  'broadcasting' => 
  array (
    'default' => 'log',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => '',
        'secret' => '',
        'app_id' => '',
        'options' => 
        array (
          'host' => '',
          'port' => '8080',
          'scheme' => 'http',
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => '',
        'secret' => '',
        'app_id' => '',
        'options' => 
        array (
          'cluster' => '',
          'useTLS' => true,
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'cache' => 
  array (
    'default' => 'database',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'lock_connection' => NULL,
        'lock_table' => 'cache_locks',
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => '/Users/user/Desktop/acadflow/acadflow-web/storage/framework/cache/data',
        'lock_path' => '/Users/user/Desktop/acadflow/acadflow-web/storage/framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => '',
        'sasl' => 
        array (
          0 => '',
          1 => '',
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => '11211',
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => '',
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
    ),
    'prefix' => 'acadflow_cache_',
  ),
  'database' => 
  array (
    'default' => 'sqlite',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => '',
        'database' => 'database/database2.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 15000,
        'journal_mode' => 'WAL',
        'synchronous' => NULL,
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => '',
        'host' => '',
        'port' => '',
        'database' => 'database/database2.sqlite',
        'username' => '',
        'password' => '',
        'unix_socket' => '',
        'charset' => '',
        'collation' => '',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => '',
        'host' => '',
        'port' => '',
        'database' => 'database/database2.sqlite',
        'username' => '',
        'password' => '',
        'unix_socket' => '',
        'charset' => '',
        'collation' => '',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => '',
        'host' => '',
        'port' => '',
        'database' => 'database/database2.sqlite',
        'username' => '',
        'password' => '',
        'charset' => '',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => '',
        'host' => '',
        'port' => '',
        'database' => 'database/database2.sqlite',
        'username' => '',
        'password' => '',
        'charset' => '',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => '',
      ),
      'default' => 
      array (
        'url' => '',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
      ),
      'cache' => 
      array (
        'url' => '',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
      ),
    ),
  ),
  'features' => 
  array (
    'definitions' => 
    array (
      'dashboard' => 
      array (
        'title' => 'Dashboards',
        'category' => 'Core Academic',
        'description' => 'Student, lecturer, member and role-specific dashboard views.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'dashboard',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Normal users will not be able to open their dashboard while this feature is unavailable.',
      ),
      'courses' => 
      array (
        'title' => 'Courses & Enrolment',
        'category' => 'Core Academic',
        'description' => 'Course lists, course details, lecturer course workspaces, enrolment and invitation flows.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'courses.*',
          1 => 'lecturer.courses*',
          2 => 'admin.courses*',
        ),
        'paths' => 
        array (
          0 => 'api/v1/courses*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Students and lecturers will lose access to course workspaces and course API actions.',
      ),
      'course_materials' => 
      array (
        'title' => 'Course Materials',
        'category' => 'Core Academic',
        'description' => 'Course resources, lecturer material management, previews, downloads and exports.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'materials.*',
          1 => 'lecturer.materials.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'courses',
        ),
        'impact' => 'Course material browsing, upload, editing and download actions will be unavailable to normal users.',
      ),
      'assignments' => 
      array (
        'title' => 'Assignments',
        'category' => 'Core Academic',
        'description' => 'Assignment creation, publication, attachments, extensions and student assignment views.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'submission-tasks.*',
          1 => 'courses.assignments',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'courses',
        ),
        'impact' => 'Students cannot open assignments and lecturers cannot create or manage them.',
      ),
      'submissions' => 
      array (
        'title' => 'Submissions & Grading',
        'category' => 'Core Academic',
        'description' => 'Student submissions, versions, lecturer review, grading, correction and approval workflows.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'submissions.*',
          1 => 'submission-versions.*',
        ),
        'paths' => 
        array (
          0 => 'api/v1/submissions*',
        ),
        'depends_on' => 
        array (
          0 => 'courses',
          1 => 'assignments',
        ),
        'impact' => 'Students cannot submit work and lecturers cannot review or grade submissions.',
      ),
      'attendance' => 
      array (
        'title' => 'Attendance',
        'category' => 'Core Academic',
        'description' => 'Attendance sessions, QR/check-in, records, lecturer controls and attendance exports.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'attendance.*',
        ),
        'paths' => 
        array (
          0 => 'api/v1/attendance*',
        ),
        'depends_on' => 
        array (
          0 => 'courses',
        ),
        'impact' => 'Attendance sessions, check-in and attendance records will be unavailable to normal users.',
      ),
      'course_discussions' => 
      array (
        'title' => 'Course Discussions',
        'category' => 'Communication',
        'description' => 'Course discussion boards, replies, reactions, subscriptions and reports.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'discussions.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'courses',
        ),
        'impact' => 'Course discussion and reply actions will be unavailable to normal users.',
      ),
      'group_collaboration' => 
      array (
        'title' => 'Groups & Collaboration',
        'category' => 'Collaboration',
        'description' => 'Groups, memberships, invitations, tasks, resources, comments and collaboration workflows.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'groups.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Group workspaces and collaboration actions will be unavailable to normal users.',
      ),
      'research_studio' => 
      array (
        'title' => 'Research Studio',
        'category' => 'Research Studio',
        'description' => 'Research projects, structured writing, literature, meetings, milestones, archives and workflow controls.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'research.*',
        ),
        'paths' => 
        array (
          0 => 'api/v1/research-projects*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Research workspaces and research API actions will be unavailable to normal users.',
      ),
      'research_literature' => 
      array (
        'title' => 'Research Literature & Reference Library',
        'category' => 'Research Studio',
        'description' => 'Scholarly discovery, multi-source paper search, saved project references and structured literature review notes.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'research.literature.*',
          1 => 'research.references.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Literature discovery and project reference-library actions will be unavailable to normal users.',
      ),
      'research_institutional_expansion' => 
      array (
        'title' => 'Research Institutional Expansion',
        'category' => 'Research Studio',
        'description' => 'Institution-linked research mode, department selection and institution supervisor invitation workflows. Independent research remains available when disabled.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.invitations.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Normal users will not see institutional research mode, department controls or supervisor invitations. Existing institutional projects remain preserved.',
      ),
      'research_meetings' => 
      array (
        'title' => 'Research Meetings & Action Items',
        'category' => 'Research Studio',
        'description' => 'Supervisor/team meetings, attendee tracking, notes, calendar export and follow-up action items.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.meetings.*',
          1 => 'research.action-items.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Meeting scheduling, attendance, notes and meeting action-item actions will be unavailable to normal users.',
      ),
      'research_collaboration' => 
      array (
        'title' => 'Research Collaboration & Contribution Tracking',
        'category' => 'Research Studio',
        'description' => 'Research-member management, granular permissions and measured/declared authorship contribution tracking.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.members.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Advanced collaborator management and contribution-evidence tools will be unavailable to normal users.',
      ),
      'research_archives' => 
      array (
        'title' => 'Research Archives & Controlled Amendments',
        'category' => 'Research Studio',
        'description' => 'Immutable research archive packages, checksums and post-approval amendment workflows.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.archives.*',
          1 => 'research.amendments.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Archive sealing/download and controlled amendment workflows will be unavailable to normal users.',
      ),
      'research_datasets' => 
      array (
        'title' => 'Research Dataset Management',
        'category' => 'Research Studio',
        'description' => 'Secure project dataset uploads, access levels, schema metadata and ethics metadata.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.datasets.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Research dataset upload and management actions will be unavailable to normal users.',
      ),
      'research_to_knowledge_hub' => 
      array (
        'title' => 'Research → Knowledge Hub Publishing',
        'category' => 'Research Studio',
        'description' => 'Publishes approved research into linked Knowledge Hub drafts.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.publish',
        ),
        'paths' => 
        array (
          0 => 'api/v1/research-projects/*/publish',
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
          1 => 'knowledge_hub',
        ),
        'impact' => 'Publishing research into Knowledge Hub drafts will be unavailable to normal users.',
      ),
      'siwes_module' => 
      array (
        'title' => 'SIWES Workspace',
        'category' => 'Research Studio',
        'description' => 'SIWES placements, logs, attendance and evaluations within Research Studio.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.siwes.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'SIWES placement, logbook and evaluation actions will be unavailable to normal users.',
      ),
      'seminar_module' => 
      array (
        'title' => 'Seminar Workspace',
        'category' => 'Research Studio',
        'description' => 'Seminar scheduling, panels, questions, scoring and completion workflows.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'research.seminar.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Seminar workflows will be unavailable to normal users.',
      ),
      'final_year_project' => 
      array (
        'title' => 'Project Defense',
        'category' => 'Research Studio',
        'description' => 'Final-year project defense scheduling and related defense workflow actions.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'defenses.*',
          1 => 'research.defenses.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'research_studio',
        ),
        'impact' => 'Project defense scheduling and defense actions will be unavailable to normal users.',
      ),
      'knowledge_hub' => 
      array (
        'title' => 'Knowledge Hub',
        'category' => 'Knowledge Hub',
        'description' => 'Academic publishing, discovery, creator profiles, comments, reactions, citations and publication management.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'knowledge.index',
          1 => 'knowledge.search',
          2 => 'knowledge.show',
          3 => 'knowledge.creator*',
          4 => 'knowledge.leaderboard',
          5 => 'knowledge.follow',
          6 => 'knowledge.reactions',
          7 => 'knowledge.comments.*',
          8 => 'knowledge.citations.*',
          9 => 'knowledge.reports',
          10 => 'knowledge.manage*',
          11 => 'knowledge.verification.*',
          12 => 'knowledge.shares',
        ),
        'paths' => 
        array (
          0 => 'api/v1/knowledge*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Knowledge discovery, publishing and related API actions will be unavailable to normal users.',
      ),
      'knowledge_communities' => 
      array (
        'title' => 'Knowledge Communities',
        'category' => 'Knowledge Hub',
        'description' => 'Communities, community posts, polls, memberships, invitations, comments and moderation.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.communities.*',
          1 => 'knowledge.community-posts.*',
          2 => 'knowledge.polls.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Community pages, posts and polls will be unavailable to normal users.',
      ),
      'learning_paths' => 
      array (
        'title' => 'Learning Paths',
        'category' => 'Knowledge Hub',
        'description' => 'Curated learning paths, enrolment, learning items and learner progress.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.learning.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Learning paths and learner progress actions will be unavailable to normal users.',
      ),
      'reading_lists' => 
      array (
        'title' => 'Reading Lists',
        'category' => 'Knowledge Hub',
        'description' => 'Reading-list creation, sharing, members, exports and list items.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.reading.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Reading lists and their sharing/export actions will be unavailable to normal users.',
      ),
      'academic_events' => 
      array (
        'title' => 'Academic Events & Calendar',
        'category' => 'Knowledge Hub',
        'description' => 'Academic events, registrations, invitations, attendance and event calendar views.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.events.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Academic event discovery, registration and event-management actions will be unavailable to normal users.',
      ),
      'academic_challenges' => 
      array (
        'title' => 'Academic Challenges',
        'category' => 'Knowledge Hub',
        'description' => 'Challenges, entries, judging, voting, comments and results.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.challenges.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Academic challenge participation and challenge-management actions will be unavailable to normal users.',
      ),
      'course_certificates' => 
      array (
        'title' => 'Certificates',
        'category' => 'Knowledge Hub',
        'description' => 'Certificate verification and downloadable academic certificates.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.certificates.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Certificate verification and downloads will be unavailable to normal users.',
      ),
      'knowledge_ai_companion' => 
      array (
        'title' => 'Knowledge AI Companion',
        'category' => 'AI & Intelligence',
        'description' => 'Context-grounded AI companion inside Knowledge Hub resources.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'knowledge.companion.*',
        ),
        'paths' => 
        array (
          0 => 'api/v1/knowledge/*/companion',
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
          1 => 'ai_assistant',
        ),
        'impact' => 'The AI companion inside Knowledge Hub resources will be unavailable to normal users.',
      ),
      'ai_assistant' => 
      array (
        'title' => 'AI Assistant',
        'category' => 'AI & Intelligence',
        'description' => 'AcadSyntra AI workspace plus centralized student, lecturer, writing, citation, research, assignment, SIWES, project, material and discussion AI assistance.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'ai.assistant',
          1 => 'ai.assistant.*',
          2 => 'ai.writing',
          3 => 'ai.citation',
          4 => 'ai.submission.*',
          5 => 'ai.context.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'AI Assistant and user-facing AI assistance will be unavailable to normal users. AI Settings remain accessible to administrators.',
      ),
      'notifications' => 
      array (
        'title' => 'Notifications',
        'category' => 'Communication',
        'description' => 'Notification centre, user preferences, admin announcements and notification management.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'notifications.*',
          1 => 'admin.notifications.*',
        ),
        'paths' => 
        array (
          0 => 'api/v1/notifications*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'The notification centre and notification API actions will be unavailable to normal users.',
      ),
      'push_notifications' => 
      array (
        'title' => 'Push Notifications',
        'category' => 'Communication',
        'description' => 'Browser/mobile push subscription, unsubscribe, VAPID key and push-test endpoints.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'browser-push.subscribe',
          1 => 'browser-push.test',
        ),
        'paths' => 
        array (
          0 => 'api/v1/push*',
        ),
        'depends_on' => 
        array (
          0 => 'notifications',
        ),
        'impact' => 'Push subscription and push-delivery endpoints will be unavailable to normal users.',
      ),
      'institution_self_service' => 
      array (
        'title' => 'Institution Self-Service Onboarding',
        'category' => 'Institution Expansion',
        'description' => 'Public institution claiming, department requests and institution-access request flows. Admin review tools remain available to administrators.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'institution.onboarding.*',
          1 => 'institution.access',
          2 => 'institution.access.store',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Normal users cannot start institution claim/access requests while disabled. Existing requests and all administrator review tools remain preserved.',
      ),
      'monetization_commerce' => 
      array (
        'title' => 'Monetization & Commerce',
        'category' => 'Commerce',
        'description' => 'Wallet funding, marketplace purchases, refunds, creator earnings, withdrawals, institutional billing and monetization administration.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'commerce.*',
          1 => 'billing.*',
          2 => 'admin.monetization',
          3 => 'admin.monetization.*',
          4 => 'institution.commercial-account',
          5 => 'institution.commercial-account.*',
          6 => 'subscription.payment.callback',
        ),
        'paths' => 
        array (
          0 => 'api/v1/invoices*',
          1 => 'api/v1/payments*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Wallet, marketplace, creator payout and institutional billing actions are unavailable to normal users while disabled. Historical payment reconciliation remains preserved.',
      ),
      'knowledge_hub_premium' => 
      array (
        'title' => 'Premium Knowledge Resources',
        'category' => 'Commerce',
        'description' => 'Paid/premium Knowledge Hub resources and premium entitlement checks.',
        'default_status' => 'disabled',
        'routes' => 
        array (
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
        ),
        'impact' => 'Premium Knowledge Hub resources will not be available for purchase/access by normal users.',
      ),
      'wallet_billing' => 
      array (
        'title' => 'Wallet & Paid Feature Access',
        'category' => 'Commerce',
        'description' => 'Personal wallet, wallet funding and paid feature/AI entitlement access. Keep this enabled when AI billing is enabled.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'commerce.wallet',
          1 => 'commerce.wallet.fund',
          2 => 'commerce.wallet.callback',
          3 => 'commerce.features',
          4 => 'commerce.features.purchase',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'monetization_commerce',
        ),
        'impact' => 'Users cannot fund/view their wallet or purchase paid feature access while disabled. Existing balances and billing history are preserved.',
      ),
      'commerce_marketplace' => 
      array (
        'title' => 'Knowledge Marketplace',
        'category' => 'Commerce',
        'description' => 'Knowledge/publication and learning-path purchases, marketplace orders and buyer refunds.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'commerce.purchase',
          1 => 'commerce.learning.purchase',
          2 => 'commerce.orders',
          3 => 'commerce.refunds.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'knowledge_hub',
          1 => 'monetization_commerce',
        ),
        'impact' => 'Marketplace purchases, orders and buyer refund requests are unavailable to normal users. Existing records remain preserved.',
      ),
      'creator_payouts' => 
      array (
        'title' => 'Creator Payouts & Withdrawals',
        'category' => 'Commerce',
        'description' => 'Creator payout account verification and withdrawal requests/processes.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'commerce.payout-accounts.*',
          1 => 'commerce.withdrawals.*',
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
          0 => 'monetization_commerce',
        ),
        'impact' => 'Creator payout account and withdrawal actions are hidden/unavailable while disabled. Earnings records remain intact.',
      ),
      'documents_exports' => 
      array (
        'title' => 'Documents & Academic Exports',
        'category' => 'Utilities',
        'description' => 'Generated documents, transcripts, grade reports, batch exports and document downloads.',
        'default_status' => 'enabled',
        'routes' => 
        array (
          0 => 'documents.*',
          1 => 'export.grade-report',
          2 => 'export.transcript',
        ),
        'paths' => 
        array (
          0 => 'api/v1/documents*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Document generation, academic exports and document API actions will be unavailable to normal users.',
      ),
      'advanced_analytics' => 
      array (
        'title' => 'Reports & Advanced Analytics',
        'category' => 'Reporting',
        'description' => 'Administrative reports, AI analytics and reporting/export API endpoints.',
        'default_status' => 'disabled',
        'routes' => 
        array (
          0 => 'admin.reports*',
          1 => 'ai.analytics',
        ),
        'paths' => 
        array (
          0 => 'api/v1/reports*',
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'Reporting and analytics views/API endpoints will be unavailable to normal non-admin consumers. Administrators retain preview access.',
      ),
      'pwa_enabled' => 
      array (
        'title' => 'Progressive Web App (PWA)',
        'category' => 'Mobile & PWA',
        'description' => 'PWA installation prompt, service-worker registration and offline application capability.',
        'default_status' => 'enabled',
        'routes' => 
        array (
        ),
        'paths' => 
        array (
        ),
        'depends_on' => 
        array (
        ),
        'impact' => 'PWA installation and service-worker registration will be disabled for normal users. The web application itself remains available.',
      ),
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => '/Users/user/Desktop/acadflow/acadflow-web/storage/app/private',
        'serve' => true,
        'throw' => false,
        'report' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => '/Users/user/Desktop/acadflow/acadflow-web/storage/app/public',
        'url' => 'http://127.0.0.1:8001/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => '',
        'endpoint' => '',
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      '/Users/user/Desktop/acadflow/acadflow-web/public/storage' => '/Users/user/Desktop/acadflow/acadflow-web/storage/app/public',
    ),
  ),
  'help_guides' => 
  array (
    0 => 
    array (
      'topic' => 'Students & independent registration',
      'question' => 'Can I start as a student before my school adopts AcadSyntra?',
      'answer' => 'Yes. Your personal account, independent classrooms and research do not depend on an institution buying access.',
      'steps' => 
      array (
        0 => 'Open Create account and choose Student.',
        1 => 'Verify your email when required, then complete your profile and interests. You may leave optional institutional affiliation unset.',
        2 => 'Use your dashboard, independent classrooms and Research Studio. Institutional courses still require an approved membership or authorised invitation.',
        3 => 'When your department becomes available, connect this account through My Academic Affiliations; do not register again.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Create your account',
          'route' => 'register',
        ),
        1 => 
        array (
          'label' => 'Continue personal setup',
          'route' => 'onboarding.show',
        ),
      ),
    ),
    1 => 
    array (
      'topic' => 'Students & independent registration',
      'question' => 'Will joining my department move or expose my personal research?',
      'answer' => 'Joining creates a reviewed academic membership. It preserves your account, research, documents and academic history. Personal research remains restricted to its explicit participants.',
      'steps' => 
      array (
        0 => 'Sign in with your existing account.',
        1 => 'Open My Academic Affiliations and request membership or accept an email-bound invitation.',
        2 => 'Wait for role-specific approval. A matching email domain does not grant privileges.',
        3 => 'Switch to the approved workspace when you need its academic tools; return to Personal workspace for independent work.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'My Academic Affiliations',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    2 => 
    array (
      'topic' => 'Lecturers & researchers',
      'question' => 'How does a lecturer or researcher get started independently?',
      'answer' => 'Register your own account first. Teaching assignments, supervision and organisational permissions are granted separately from your self-declared profile.',
      'steps' => 
      array (
        0 => 'Choose Lecturer / Researcher during registration and finish personal setup.',
        1 => 'Create independent academic work or use the tools enabled for your account.',
        2 => 'Discover your adopted department or accept its secure invitation using the same verified email address.',
        3 => 'Wait for approval of your lecturer or researcher membership. A department administrator can then assign authorised courses and supervision work.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Register',
          'route' => 'register',
        ),
        1 => 
        array (
          'label' => 'Discover academic organisations',
          'route' => 'institution.access',
        ),
      ),
    ),
    3 => 
    array (
      'topic' => 'Lecturers & researchers',
      'question' => 'What happens when a lecturer changes institutions or departments?',
      'answer' => 'Multiple academically valid affiliations can coexist. Transfer or revocation ends the selected membership without deleting the account or personal records.',
      'steps' => 
      array (
        0 => 'Request or accept the new organisation membership.',
        1 => 'Wait for its approval before switching workspaces.',
        2 => 'Use My Academic Affiliations to end the old membership as transferred, or ask its administrator to record the move.',
        3 => 'Course and institutional access follow the current approved scope; unrelated departments stay private.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Manage affiliations',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    4 => 
    array (
      'topic' => 'Departmental onboarding',
      'question' => 'Can a department adopt AcadSyntra before its university, polytechnic or college?',
      'answer' => 'Yes. An institution can exist in the academic directory without being an AcadSyntra adopter. Your department may adopt independently within that directory hierarchy.',
      'steps' => 
      array (
        0 => 'Choose Register My Department and sign in or create your personal account.',
        1 => 'Complete personal setup, then open the department wizard.',
        2 => 'Search for the institution, choose its faculty/school and department, or provide the missing academic names.',
        3 => 'Submit the representative, official contacts, classification, requested plan and authority evidence.',
        4 => 'Platform Admin reviews the application. Approval activates department governance without taking over the parent institution.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Register my department',
          'route' => 'department.onboarding',
        ),
      ),
    ),
    5 => 
    array (
      'topic' => 'Departmental onboarding',
      'question' => 'Who can register a department, and what information is required?',
      'answer' => 'A head of department, departmental administrator or other authorised representative may apply. Stating a title or owning an institutional email is not itself verification.',
      'steps' => 
      array (
        0 => 'Prepare the institution, faculty/school and department names and academic classification.',
        1 => 'Provide your representative title, official email address and official telephone number.',
        2 => 'Select the department adoption arrangement and describe evidence of your authority.',
        3 => 'Attach a verification document when needed. Evidence is stored privately and is available only to the applicant and authorised platform reviewer.',
        4 => 'Confirm you are authorised to submit the application.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Department application',
          'route' => 'department.onboarding',
        ),
      ),
    ),
    6 => 
    array (
      'topic' => 'Departmental onboarding',
      'question' => 'What if my institution, faculty or department is missing from the directory?',
      'answer' => 'Request a missing institution from the department wizard. A directory addition creates academic identity only; it does not adopt the platform or appoint you institution administrator.',
      'steps' => 
      array (
        0 => 'Search using at least two characters and try the official name.',
        1 => 'If no institution matches, submit a missing-institution request from the wizard.',
        2 => 'Wait for review, then select the approved directory entry.',
        3 => 'For a missing faculty or department, provide its official name in the wizard. Existing canonical names are reused when a match exists.',
        4 => 'Contact support if two entries appear to describe the same department; do not submit another adoption application to bypass review.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Open the wizard',
          'route' => 'department.onboarding',
        ),
        1 => 
        array (
          'label' => 'Contact support',
          'route' => 'public.contact',
        ),
      ),
    ),
    7 => 
    array (
      'topic' => 'Departmental onboarding',
      'question' => 'How do autosave, verification and approval work?',
      'answer' => 'The four-step wizard saves your own progress. Submitting an application places it in the platform review workflow; saving a draft does not grant access or create a charge.',
      'steps' => 
      array (
        0 => 'Complete each step and wait for Progress saved before leaving.',
        1 => 'Return using the same account to resume your draft.',
        2 => 'Review the application and submit it once your authority evidence and contacts are correct.',
        3 => 'Check the status and any review note on the department onboarding page.',
        4 => 'Once approved, use the department dashboard and My Academic Affiliations. If rejected, follow the review note or ask support for clarification.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Resume department setup',
          'route' => 'department.onboarding',
        ),
      ),
    ),
    8 => 
    array (
      'topic' => 'Departmental onboarding',
      'question' => 'How do lecturers and students join an adopted department?',
      'answer' => 'They keep their existing accounts. Membership is connected through reviewed requests or secure invitations, with the appropriate academic role.',
      'steps' => 
      array (
        0 => 'The department administrator opens My Academic Affiliations and selects the department scope.',
        1 => 'Create an invitation for the recipient email and ordinary academic role, then share the secure link through an authorised channel.',
        2 => 'The recipient signs in with the matching verified email and accepts before the link expires.',
        3 => 'Alternatively the user discovers the department and submits an access request.',
        4 => 'Review and approve requests in the existing membership workflow. Privileged administrator appointments remain platform-reviewed.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Invitations and members',
          'route' => 'academic.organisations',
        ),
        1 => 
        array (
          'label' => 'Request academic access',
          'route' => 'institution.access',
        ),
      ),
    ),
    9 => 
    array (
      'topic' => 'Departmental onboarding',
      'question' => 'What can a department administrator manage?',
      'answer' => 'Department administrators manage their authorised department’s members, courses, academic periods and enabled modules. They cannot access unrelated departments or change institution-wide provider credentials.',
      'steps' => 
      array (
        0 => 'Switch to the approved department workspace.',
        1 => 'Use the department dashboard for academic management.',
        2 => 'Use My Academic Affiliations to invite members, review approved memberships and record graduation, transfer or revocation.',
        3 => 'Adjust departmental module preferences. A department cannot enable a platform-disabled module.',
        4 => 'Request commercial terms using the existing department commercial-account page when sponsored features are needed.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Manage department affiliations',
          'route' => 'academic.organisations',
        ),
        1 => 
        array (
          'label' => 'Department commercial account',
          'route' => 'institution.commercial-account',
        ),
      ),
    ),
    10 => 
    array (
      'topic' => 'Institutional onboarding',
      'question' => 'How does a university, polytechnic or college adopt AcadSyntra?',
      'answer' => 'Use the existing institution onboarding process. Registration, claim verification, administrator appointment and activation are separate from an institution merely appearing in the directory.',
      'steps' => 
      array (
        0 => 'Choose Register My Institution, create or sign in to the representative account and finish personal setup.',
        1 => 'Search for the institution and submit an institution adoption request with official contacts and authority evidence.',
        2 => 'Respond to verification requests and wait for platform review.',
        3 => 'An approved institution administrator completes the academic setup and activation workflow.',
        4 => 'After activation, invite members and propose affiliations with independently adopted departments.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Register my institution',
          'route' => 'institution.onboarding.request',
        ),
      ),
    ),
    11 => 
    array (
      'topic' => 'Institutional onboarding',
      'question' => 'How are institution administrators verified?',
      'answer' => 'An institution administrator appointment requires authorised review. A public email domain, matching profile field or self-declared affiliation never automatically grants administrative privileges.',
      'steps' => 
      array (
        0 => 'Submit the institution request as an authorised representative.',
        1 => 'Provide evidence and official contact information for verification.',
        2 => 'Wait for the platform to approve the claim and administrator appointment.',
        3 => 'Complete the remaining institution setup under the approved administrative scope.',
        4 => 'Use invitations or reviewed memberships for other staff; do not share administrator accounts.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Institution onboarding',
          'route' => 'institution.onboarding.request',
        ),
      ),
    ),
    12 => 
    array (
      'topic' => 'Institutional onboarding',
      'question' => 'How do institution administrators discover and affiliate existing departments?',
      'answer' => 'An active institution can see candidate independent departments attached to its directory identity. Discovery does not grant access to departmental records.',
      'steps' => 
      array (
        0 => 'Open My Academic Affiliations as an approved institution administrator.',
        1 => 'Review Independent departments that may affiliate.',
        2 => 'Send a proposal specifying academic-data sharing, feature inheritance and preferred billing ownership.',
        3 => 'The current department administrator must consent, and Platform Admin must verify both parties.',
        4 => 'Manage departmental information only when the approved policy permits that access.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Discover affiliation candidates',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    13 => 
    array (
      'topic' => 'Institutional onboarding',
      'question' => 'How do independent students and lecturers connect after institution adoption?',
      'answer' => 'They connect their existing accounts through approved memberships. Their projects, documents and personal data are retained.',
      'steps' => 
      array (
        0 => 'Ask users to sign in with their existing verified accounts.',
        1 => 'Invite them into the appropriate institutional or departmental scope, or direct them to request access.',
        2 => 'Review their requested role and organisational affiliation.',
        3 => 'Users switch to an approved workspace in My Academic Affiliations.',
        4 => 'Institution-wide features depend on the approved department affiliation policy and active commercial access.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Academic access requests',
          'route' => 'institution.access',
        ),
      ),
    ),
    14 => 
    array (
      'topic' => 'Institutional onboarding',
      'question' => 'How do institutional permissions, billing and reporting work?',
      'answer' => 'Permissions are scoped to approved organisations. Independently adopted departments retain control until a verified agreement grants specific institutional access.',
      'steps' => 
      array (
        0 => 'Complete institution verification and activation.',
        1 => 'Use approved membership roles for administrators, lecturers and students.',
        2 => 'Review commercial terms and sponsored entitlements through the existing billing system.',
        3 => 'Access institutional reports within your approved institutional and departmental scope.',
        4 => 'Use department consent and platform verification before claiming management of an existing independent department.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'My Academic Affiliations',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    15 => 
    array (
      'topic' => 'Department affiliation',
      'question' => 'Our department adopted first. What happens when our institution adopts six months later?',
      'answer' => 'Your department can connect through a two-party affiliation agreement. The platform does not delete, recreate, duplicate, overwrite or reparent departmental academic data during affiliation.',
      'steps' => 
      array (
        0 => 'The institution completes verification and becomes active.',
        1 => 'Its approved administrator proposes affiliation with your existing department.',
        2 => 'Your department administrator reviews the proposal, sharing permissions, feature inheritance and billing preference.',
        3 => 'Accept or decline. Acceptance waits for Platform Admin verification of both approvals.',
        4 => 'After verification, the existing department becomes institution-affiliated. Existing IDs, memberships, courses and research remain intact.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Review affiliation requests',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    16 => 
    array (
      'topic' => 'Department affiliation',
      'question' => 'Can a department decline affiliation or remain independent later?',
      'answer' => 'Yes. A department can decline the proposal or retain control under its agreed policy. Either organisation can end an active affiliation without deleting the department.',
      'steps' => 
      array (
        0 => 'Review the proposal as the current department administrator.',
        1 => 'Decline if the terms or claimed authority are unsuitable.',
        2 => 'For an active agreement, use End affiliation; retain department.',
        3 => 'The department returns to independent operation; agreement and audit history remain.',
        4 => 'Review which sponsored features remain available. Department balances and personal purchases are preserved.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Manage agreements',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    17 => 
    array (
      'topic' => 'Department affiliation',
      'question' => 'How are unauthorised claims, duplicate departments and affiliation disputes resolved?',
      'answer' => 'Affiliation requires current authority on both sides and platform verification. Do not create another department record or use an email-domain match to bypass a disputed claim.',
      'steps' => 
      array (
        0 => 'Leave a pending proposal unapproved if the directory match or representative is incorrect.',
        1 => 'Check the institution, faculty and department identity against official records.',
        2 => 'Ask Platform Admin or support to review the authority evidence and agreement history.',
        3 => 'A rejected or withdrawn proposal does not grant access. A revoked representative cannot supply valid consent.',
        4 => 'Use a reviewed correction to canonical directory records; existing academic data must remain intact.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Review agreements',
          'route' => 'academic.organisations',
        ),
        1 => 
        array (
          'label' => 'Resolve a directory or affiliation issue',
          'route' => 'public.contact',
        ),
      ),
    ),
    18 => 
    array (
      'topic' => 'Invitations & membership',
      'question' => 'My invitation was rejected, expired or sent to another email. What should I do?',
      'answer' => 'Invitation links are single-use, expire and are bound to the intended verified email. A link does not grant administrator privileges.',
      'steps' => 
      array (
        0 => 'Sign in to the account with the exact recipient email and verify it.',
        1 => 'If the link expired or was revoked, ask the administrator for a fresh invitation.',
        2 => 'If the wrong address was used, ask the administrator to revoke that invitation and issue a corrected one.',
        3 => 'For a rejected access request, review its note and provide the requested evidence through the review workflow.',
        4 => 'Keep your original account; contact support before creating duplicates to work around a failed invitation.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Manage academic affiliations',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    19 => 
    array (
      'topic' => 'Invitations & membership',
      'question' => 'What do pending, approved, rejected and revoked membership statuses mean?',
      'answer' => 'Pending awaits review; approved grants only the reviewed role and scope; rejected records a declined request; revoked ends the grant. Graduation and transfer preserve membership history.',
      'steps' => 
      array (
        0 => 'Check the organisation and role shown in My Academic Affiliations.',
        1 => 'Use only an approved, unexpired membership to switch workspaces.',
        2 => 'Request review from the appropriate administrator when a pending request needs attention.',
        3 => 'After revocation or transfer, use your personal workspace and any other valid affiliations.',
        4 => 'A profile department or email domain is academic identity, not an access grant.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Check memberships',
          'route' => 'academic.organisations',
        ),
      ),
    ),
    20 => 
    array (
      'topic' => 'Plans & access',
      'question' => 'Do I need a subscription, and what are the department and institution plans?',
      'answer' => 'AcadSyntra currently uses the existing commercial-account, wallet and entitlement system; the retired recurring-subscription system is not restarted. Core and Sponsored adoption arrangements request the appropriate scope and commercial review.',
      'steps' => 
      array (
        0 => 'Choose a department or institution arrangement in onboarding.',
        1 => 'Approval may open a commercial setup request; it does not automatically charge you.',
        2 => 'Use Paid Features to inspect what is free, personally purchased or sponsored.',
        3 => 'Use the existing commercial-account request workflow for organisation-funded access.',
        4 => 'Feature release controls and your approved academic scope still apply.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Paid features and sponsorship',
          'route' => 'commerce.features',
        ),
      ),
    ),
    21 => 
    array (
      'topic' => 'Plans & access',
      'question' => 'What happens to paid access when a department affiliates or a plan expires?',
      'answer' => 'Affiliation does not move balances, cancel personal purchases or create duplicate recurring subscriptions. Sponsorship follows the approved billing preference and active commercial terms.',
      'steps' => 
      array (
        0 => 'Review the proposed department-first or institution-first billing preference.',
        1 => 'Only one eligible commercial account funds a reservation.',
        2 => 'If sponsorship expires or pauses, another eligible agreed sponsor may apply; otherwise personal access and wallet rules apply.',
        3 => 'Refunds use the original reservation account even if the user changes academic workspace.',
        4 => 'Contact the organisation administrator if a sponsored feature becomes unavailable; your personal academic records remain.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Check access',
          'route' => 'commerce.features',
        ),
      ),
    ),
    22 => 
    array (
      'topic' => 'Research Studio & academic workflows',
      'question' => 'How do I start and write a research or final-year project?',
      'answer' => 'Research Studio brings project planning, chapter writing, references, data analysis, supervision and review into one workspace. Availability depends on the enabled tools and your approved academic context.',
      'steps' => 
      array (
        0 => 'Open Research Studio and choose New research project or Final-Year Project.',
        1 => 'Complete the project setup and plan before drafting chapters.',
        2 => 'Select a chapter in the writing workspace. Collapse chapter navigation or use focus mode to make room for writing.',
        3 => 'Save changes and review the save status. Use chapter history to inspect earlier versions.',
        4 => 'Use references, analysis and supervisor review for the corresponding tasks; check the built-in workspace guide when unsure.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Open Research Studio',
          'route' => 'research.index',
        ),
        1 => 
        array (
          'label' => 'Start research',
          'route' => 'research.create',
        ),
        2 => 
        array (
          'label' => 'Start a final-year project',
          'route' => 'research.create',
          'params' => 
          array (
            'studio_mode' => 'final_year',
          ),
        ),
      ),
    ),
    23 => 
    array (
      'topic' => 'Research Studio & academic workflows',
      'question' => 'How do supervision, corrections, milestones and AI results work?',
      'answer' => 'Supervision and collaboration use explicit invitations and roles. Review updates and reminders link back to the relevant authorised project; optional AI assistance does not replace academic judgment.',
      'steps' => 
      array (
        0 => 'Invite a supervisor or collaborator through the project workflow.',
        1 => 'Review feedback and corrections inside the authorised workspace.',
        2 => 'Track milestone dates and complete or resolve the corresponding work to stop future reminders.',
        3 => 'For background AI work, check its saved status or completion notification; review the generated result before using it.',
        4 => 'AI usage and exports follow the existing feature, access and billing policies.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Research Studio',
          'route' => 'research.index',
        ),
      ),
    ),
    24 => 
    array (
      'topic' => 'Notifications & preferences',
      'question' => 'How do I enable push notifications on desktop, Android, iPhone or iPad?',
      'answer' => 'Push is optional and requires a supported browser, HTTPS, browser permission, a valid device subscription and configured delivery. Where the browser and OS support it, push can arrive when the site is closed.',
      'steps' => 
      array (
        0 => 'Sign in and choose Enable Notifications when invited, or open Notification Preferences.',
        1 => 'On iPhone/iPad, add AcadSyntra to the Home Screen and open it from that icon first.',
        2 => 'Click Enable this browser; browser permission is requested only after your click.',
        3 => 'Keep Push Notifications enabled and use Send test notification to check provider acceptance.',
        4 => 'If permission is denied, change the browser/OS notification setting manually. AcadSyntra does not repeatedly trigger denied permission prompts.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Notification preferences',
          'route' => 'notifications.settings',
        ),
      ),
    ),
    25 => 
    array (
      'topic' => 'Notifications & preferences',
      'question' => 'Can I control reminders, quiet hours and individual devices?',
      'answer' => 'Yes. Academic, organisational, research, onboarding and optional engagement notifications have separate controls. Optional engagement is off until you opt in.',
      'steps' => 
      array (
        0 => 'Open Notification Preferences.',
        1 => 'Choose email, push and in-app channels and the categories you want.',
        2 => 'Set your time zone and quiet-hour start/end; queued email and push wait until quiet hours end.',
        3 => 'Use Not Now to snooze the browser invitation for seven days, or remove a specific device from the device list.',
        4 => 'Optional reminder emails provide an unsubscribe link. Account security messages retain their separate authentication workflow.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Manage notification settings',
          'route' => 'notifications.settings',
        ),
      ),
    ),
    26 => 
    array (
      'topic' => 'Notifications & preferences',
      'question' => 'How often are onboarding and inactivity reminders sent?',
      'answer' => 'The default onboarding schedule starts after 12 hours, has a minimum 12-hour gap, stops when the relevant setup is complete and slows after repeated ignored reminders. Administrators can reduce volume or configure the limit.',
      'steps' => 
      array (
        0 => 'Open the reminder to resume the specific personal, departmental or institutional setup stage.',
        1 => 'Submitted applications waiting for review are not treated as an unfinished department draft.',
        2 => 'Optional inactivity reminders require consent and useful unfinished work. The planned stages are 24h, 48h, 7 days, 14 days and a reduced-frequency follow-up.',
        3 => 'Returning or finishing the work cancels relevant queued inactivity reminders.',
        4 => 'Disable onboarding or engagement reminders whenever you prefer.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Continue setup',
          'route' => 'onboarding.show',
        ),
        1 => 
        array (
          'label' => 'Reminder preferences',
          'route' => 'notifications.settings',
        ),
      ),
    ),
    27 => 
    array (
      'topic' => 'Notifications & preferences',
      'question' => 'A message says accepted, but I did not receive it. Why?',
      'answer' => 'Provider acceptance is not proof of device display or inbox delivery. Confirmed delivery is shown only when a supported provider reports it.',
      'steps' => 
      array (
        0 => 'Check browser permission, OS notification settings, HTTPS and whether the device is enrolled.',
        1 => 'Check spam/junk folders and your email preference.',
        2 => 'Ask an administrator to inspect Notification Analytics for configured SMTP, VAPID, workers, retry status or expired subscriptions.',
        3 => 'Log/array email transports are local simulations and cannot deliver to an inbox.',
        4 => 'Contact support without sending passwords, subscription endpoints, encryption keys or confidential academic records.',
      ),
      'links' => 
      array (
        0 => 
        array (
          'label' => 'Notification settings',
          'route' => 'notifications.settings',
        ),
        1 => 
        array (
          'label' => 'Contact support',
          'route' => 'public.contact',
        ),
      ),
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => '/Users/user/Desktop/acadflow/acadflow-web/storage/logs/laravel.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => '/Users/user/Desktop/acadflow/acadflow-web/storage/logs/laravel.log',
        'level' => 'debug',
        'days' => '14',
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => '',
        'username' => 'AcadFlow Log',
        'emoji' => ':boom:',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => '',
          'port' => '',
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'formatter' => '',
        'with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => '8',
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => '/Users/user/Desktop/acadflow/acadflow-web/storage/logs/laravel.log',
      ),
      'ai_provider' => 
      array (
        'driver' => 'daily',
        'path' => '/Users/user/Desktop/acadflow/acadflow-web/storage/logs/ai-provider.log',
        'level' => 'info',
        'days' => '14',
        'replace_placeholders' => true,
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'log',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'scheme' => NULL,
        'url' => '',
        'host' => '127.0.0.1',
        'port' => '2525',
        'username' => NULL,
        'password' => NULL,
        'timeout' => 15,
        'local_domain' => '',
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => '',
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => 'AcadSyntra',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => '/Users/user/Desktop/acadflow/acadflow-web/resources/views/vendor/mail',
      ),
      'extensions' => 
      array (
      ),
    ),
  ),
  'media' => 
  array (
    'scanner' => 'none',
    'scan_fail_closed' => true,
    'download_token_minutes' => 15,
    'preview_token_minutes' => 5,
    'clamav' => 
    array (
      'binary' => 'clamscan',
      'timeout' => 30.0,
    ),
  ),
  'monetization' => 
  array (
    'pricing_features' => 
    array (
      'allow_group_submissions' => 
      array (
        'title' => 'Group Submissions',
        'category' => 'Assignments & Assessment',
        'description' => 'Allows lecturers to create assignments that students can complete and submit as a group.',
        'pricing_type' => 'pay_per_use',
        'unit_label' => 'assignment created',
      ),
      'allow_rubrics' => 
      array (
        'title' => 'Rubric-Based Grading',
        'category' => 'Assignments & Assessment',
        'description' => 'Allows lecturers to attach a grading rubric to an assignment and use rubric-based assessment workflows.',
        'pricing_type' => 'pay_per_use',
        'unit_label' => 'rubric-enabled assignment',
      ),
      'attendance_tracking' => 
      array (
        'title' => 'Attendance Tracking',
        'category' => 'Courses & Teaching',
        'description' => 'Allows authorized lecturers to start attendance-tracking sessions.',
        'pricing_type' => 'pay_per_use',
        'unit_label' => 'attendance session',
      ),
      'document_generation' => 
      array (
        'title' => 'Document & PDF Generation',
        'category' => 'Documents & Exports',
        'description' => 'Covers transcript, grade-report, course-material and supported batch PDF generation actions.',
        'pricing_type' => 'pay_per_use',
        'unit_label' => 'generation action',
      ),
      'knowledge_hub_premium' => 
      array (
        'title' => 'Knowledge Hub Premium Access',
        'category' => 'Knowledge Hub',
        'description' => 'Unlocks premium Knowledge Hub discovery/content protected by AcadSyntra commercial entitlements.',
        'pricing_type' => 'fixed_access',
        'default_duration_days' => 30,
        'unit_label' => 'access period',
      ),
    ),
    'ai_features' => 
    array (
      'study_assistant' => 
      array (
        'title' => 'Student / Study Assistant',
        'category' => 'AI Assistants',
      ),
      'lecturer_assistant' => 
      array (
        'title' => 'Lecturer Assistant',
        'category' => 'AI Assistants',
      ),
      'writing_assistant' => 
      array (
        'title' => 'Writing Assistant',
        'category' => 'AI Assistants',
      ),
      'citation_assistant' => 
      array (
        'title' => 'Citation Assistant',
        'category' => 'AI Assistants',
      ),
      'research_assistant' => 
      array (
        'title' => 'Research Assistant',
        'category' => 'Research Studio',
      ),
      'research_validator' => 
      array (
        'title' => 'Research Validator',
        'category' => 'Research Studio',
      ),
      'assignment_assistant' => 
      array (
        'title' => 'Assignment Assistant',
        'category' => 'Assignments & Assessment',
      ),
      'submission_validator' => 
      array (
        'title' => 'Submission Validator',
        'category' => 'Assignments & Assessment',
      ),
      'plagiarism' => 
      array (
        'title' => 'Academic Integrity / Plagiarism AI',
        'category' => 'Assignments & Assessment',
      ),
      'siwes_assistant' => 
      array (
        'title' => 'SIWES Assistant',
        'category' => 'Academic Workflows',
      ),
      'project_assistant' => 
      array (
        'title' => 'Project Assistant',
        'category' => 'Academic Workflows',
      ),
      'material_assistant' => 
      array (
        'title' => 'Material / Study Assistant',
        'category' => 'Courses & Teaching',
      ),
      'discussion_assistant' => 
      array (
        'title' => 'Discussion Assistant',
        'category' => 'Courses & Teaching',
      ),
      'knowledge_publication_validator' => 
      array (
        'title' => 'Publication Validator',
        'category' => 'Knowledge Hub',
      ),
      'knowledge_moderation' => 
      array (
        'title' => 'Knowledge Moderator',
        'category' => 'Knowledge Hub',
      ),
      'knowledge_companion' => 
      array (
        'title' => 'Grounded AI Companion',
        'category' => 'Knowledge Hub',
      ),
    ),
    'pricing_types' => 
    array (
      'fixed_access' => 'Fixed access',
      'pay_per_use' => 'Pay per use',
      'ai_usage' => 'AI usage',
    ),
  ),
  'notification_delivery' => 
  array (
    'queue' => 'notifications',
    'connection' => 'database',
    'provider_webhook_secret' => NULL,
    'onboarding_interval_hours' => 12,
    'onboarding_max_reminders' => 6,
    'daily_email_limit' => 10,
    'daily_push_limit' => 20,
    'retention_days' => 180,
    'failure_alert_percent' => 20,
    'failure_alert_minimum' => 10,
    'summary_reports_enabled' => false,
    'engagement_enabled' => true,
    'categories' => 
    array (
      'academic' => 'Academic alerts',
      'organisation' => 'Departments & institutions',
      'research' => 'Research updates',
      'onboarding' => 'Onboarding reminders',
      'engagement' => 'Optional engagement',
      'billing' => 'Billing & access',
    ),
  ),
  'performance' => 
  array (
    'queued_export_bytes' => 250000,
    'export_disk' => 'local',
    'server_timing' => false,
    'log_slow_requests' => false,
    'slow_request_ms' => 1000,
  ),
  'public_pages' => 
  array (
    'help' => 
    array (
      'title' => 'Help Center',
      'intro' => 'Guidance for joining AcadSyntra, publishing knowledge, collaborating, and managing an institution.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Getting started',
          1 => 'Create an account, verify your email, complete the onboarding path that matches your role, and use the dashboard recommendations to find relevant research, communities, events, and challenges.',
        ),
        1 => 
        array (
          0 => 'Account support',
          1 => 'Use password recovery from the login page. After signing in, notification and privacy preferences can be changed from your personal settings.',
        ),
        2 => 
        array (
          0 => 'Reporting problems',
          1 => 'Use the report action on communities, groups, events, challenges, publications, and discussions. Reports are retained for authorized human moderation.',
        ),
      ),
    ),
    'documentation' => 
    array (
      'title' => 'Product Documentation',
      'intro' => 'A practical overview of AcadSyntra’s integrated academic ecosystem.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Research Studio',
          1 => 'Configure a research type and workflow, create a project, write versioned sections, manage references, collaborate with supervisors, use the Research/SIWES/Project AI assistants where enabled, validate, approve, archive, and publish eligible work.',
        ),
        1 => 
        array (
          0 => 'Knowledge Hub',
          1 => 'Create and moderate publications, use the publication-grounded AI Companion, build creator reputation, organize learning paths and reading lists, join communities, attend events, and participate in academic challenges.',
        ),
        2 => 
        array (
          0 => 'Institutions',
          1 => 'University and department administrators manage academic structures, users, courses, billing, settings, reports, and tenant-aware AI controls. Super administrators also manage centralized feature availability and platform AI routing.',
        ),
      ),
    ),
    'status' => 
    array (
      'title' => 'Platform Status',
      'intro' => 'This installation reports application health from its own configured environment.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Application',
          1 => 'The web application is responding. Administrators should use queue, scheduler, mail, database, storage, search, and AI-provider health checks in their deployment monitoring.',
        ),
        1 => 
        array (
          0 => 'Background processing',
          1 => 'Production deployments must run queue workers and the Laravel scheduler. Failed jobs and notification retries are available to authorized administrators.',
        ),
        2 => 
        array (
          0 => 'Incident reporting',
          1 => 'Operational incidents should be recorded in the organization’s support process without exposing credentials, stack traces, or private academic records.',
        ),
      ),
    ),
    'about' => 
    array (
      'title' => 'About AcadSyntra',
      'intro' => 'AcadSyntra connects academic work, people, institutions, and trusted knowledge in one platform.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Mission',
          1 => 'Help students, lecturers, researchers, institutions, independent professionals, authors, and publishers learn, collaborate, publish, build reputation, and share verified knowledge.',
        ),
        1 => 
        array (
          0 => 'Product boundaries',
          1 => 'Research Studio owns formal supervised research. Knowledge Hub owns public and institution-visible publishing, discovery, communities, events, learning, and monetization. Shared platform services prevent duplicate systems.',
        ),
        2 => 
        array (
          0 => 'Human authority',
          1 => 'AI provides structured assistance and evidence. Authorized people remain responsible for grading, supervision, approval, verification, moderation, sanctions, and final decisions.',
        ),
      ),
    ),
    'careers' => 
    array (
      'title' => 'Careers and Partnerships',
      'intro' => 'AcadSyntra supports institutions and professional partners building better academic infrastructure.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Opportunities',
          1 => 'Published openings and partnership opportunities should be announced through verified institution or organization profiles and moderated Knowledge Hub publications.',
        ),
        1 => 
        array (
          0 => 'Contributors',
          1 => 'Academic experts can publish resources, create learning paths, mentor communities, organize events, judge challenges, and collaborate on research subject to platform permissions.',
        ),
        2 => 
        array (
          0 => 'Contact',
          1 => 'Use the contact channel configured by the administrator for employment, partnership, or procurement enquiries.',
        ),
      ),
    ),
    'api' => 
    array (
      'title' => 'API Overview',
      'intro' => 'AcadSyntra exposes authenticated APIs using the project’s versioned routes and authorization rules.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Authentication',
          1 => 'API access uses Laravel Sanctum. Tokens must be scoped to a real user, stored securely, rotated when compromised, and never embedded in public clients.',
        ),
        1 => 
        array (
          0 => 'Tenant boundaries',
          1 => 'Every API consumer must preserve institution, department, role, visibility, and entitlement boundaries. A successful request never bypasses policy checks.',
        ),
        2 => 
        array (
          0 => 'Integration guidance',
          1 => 'Use the route list and generated API documentation from the deployed source as the contract. Long-running AI, indexing, export, and validation operations return job or status resources.',
        ),
      ),
    ),
    'source' => 
    array (
      'title' => 'Source and Contributions',
      'intro' => 'Source repository information is controlled by the owner of this AcadSyntra installation.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Repository',
          1 => 'Administrators can publish the approved repository URL through centralized settings. No repository link is exposed until one is explicitly configured.',
        ),
        1 => 
        array (
          0 => 'Security reports',
          1 => 'Do not publish exploitable vulnerabilities. Use the private security contact configured by the platform operator.',
        ),
        2 => 
        array (
          0 => 'Contribution process',
          1 => 'Changes should include migrations, policies, tests, documentation, and regression evidence, and should preserve existing route and data contracts.',
        ),
      ),
    ),
    'changelog' => 
    array (
      'title' => 'Changelog',
      'intro' => 'A high-level record of platform capability areas. Deployment-specific release notes belong in the source repository.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Centralized AI',
          1 => 'AcadSyntra now routes the main AI Assistant, Grounded Companion, Research, Assignment, SIWES, Project, Material and Discussion assistants, validators and moderation through one provider-routing architecture with explicit Provider AI, Hybrid, Rule-Based and Disabled modes.',
        ),
        1 => 
        array (
          0 => 'Feature management',
          1 => 'Platform modules can be enabled, placed in maintenance, or kept unreleased from one central Feature & Module Management area with dependency-aware access control.',
        ),
        2 => 
        array (
          0 => 'Academic ecosystem',
          1 => 'Research Studio, Knowledge Hub, courses, assignments/submissions, attendance, groups, communities, events, challenges, learning paths, commerce, notifications and reporting share tenant-aware identity, permissions and audit foundations.',
        ),
      ),
    ),
    'security' => 
    array (
      'title' => 'Security',
      'intro' => 'AcadSyntra protects academic records through layered authorization, tenant isolation, secure media handling, and auditable human decisions.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Access control',
          1 => 'Policies, role middleware, verified email, completed onboarding, session checks, rate limits, and tenant-aware queries protect restricted actions.',
        ),
        1 => 
        array (
          0 => 'Files and downloads',
          1 => 'Uploads are validated and scanned before storage. Restricted resources use authorization checks, expiring tokens, access logs, and safe content-disposition headers.',
        ),
        2 => 
        array (
          0 => 'Responsible disclosure',
          1 => 'Report vulnerabilities privately to the deployment owner. Never include live credentials, private research data, or personal information in a public report.',
        ),
      ),
    ),
    'terms' => 
    array (
      'title' => 'Terms of Use',
      'intro' => 'Use AcadSyntra lawfully, respectfully, and in accordance with applicable institutional policies.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Acceptable use',
          1 => 'Do not impersonate others, bypass access controls, distribute malware, harass users, manipulate reputation or voting, or misuse protected academic and assessment content.',
        ),
        1 => 
        array (
          0 => 'Academic integrity',
          1 => 'Similarity indicators are not automatic proof of misconduct. Users remain responsible for attribution, permissions, originality, and compliance with institutional rules.',
        ),
        2 => 
        array (
          0 => 'Account responsibility',
          1 => 'Keep credentials and recovery codes secure. Platform operators may suspend access where necessary to protect users, institutions, or data, subject to documented review.',
        ),
      ),
    ),
    'privacy' => 
    array (
      'title' => 'Privacy Policy',
      'intro' => 'AcadSyntra separates public profiles and publications from private academic, institutional, and commercial records.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Data controls',
          1 => 'Users choose profile visibility and notification preferences. Institutions control access to tenant-scoped academic records under their applicable policies.',
        ),
        1 => 
        array (
          0 => 'Processing',
          1 => 'The platform records activity needed for authentication, workflows, security, analytics, billing, AI usage, moderation, and audit. Access is limited by authorization.',
        ),
        2 => 
        array (
          0 => 'AI and documents',
          1 => 'AI requests must use authorized content and tenant-aware settings. Uploaded documents are treated as untrusted input and must not override system or institutional rules.',
        ),
      ),
    ),
    'cookies' => 
    array (
      'title' => 'Cookie Policy',
      'intro' => 'AcadSyntra uses cookies and comparable browser storage for secure sessions and essential application behavior.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Essential storage',
          1 => 'Session, CSRF, authentication, preference, and security state are required for signed-in features.',
        ),
        1 => 
        array (
          0 => 'Analytics',
          1 => 'Optional analytics should be enabled only in accordance with the deployment owner’s privacy settings and applicable law.',
        ),
        2 => 
        array (
          0 => 'Control',
          1 => 'Browser controls can remove stored data, but removing essential session data signs the user out and may reset local preferences.',
        ),
      ),
    ),
    'licenses' => 
    array (
      'title' => 'Licenses and Attributions',
      'intro' => 'This installation combines application source, framework packages, and optional provider integrations under their respective licenses.',
      'sections' => 
      array (
        0 => 
        array (
          0 => 'Application license',
          1 => 'The repository owner defines the license and distribution terms for this AcadSyntra installation.',
        ),
        1 => 
        array (
          0 => 'Dependencies',
          1 => 'Composer and npm lock files are the authoritative dependency inventories after installation. Production builds must retain required notices and comply with package licenses.',
        ),
        2 => 
        array (
          0 => 'User content',
          1 => 'Creators retain or grant rights according to platform and institutional terms. Publishing content does not remove third-party copyright or access restrictions.',
        ),
      ),
    ),
  ),
  'public_website' => 
  array (
    'pages' => 
    array (
      'features' => 
      array (
        'title' => 'Tools that keep academic work moving',
        'description' => 'Explore AcadSyntra courses, Research Studio, Knowledge Hub, collaboration and institution administration.',
        'intro' => 'From the first lecture note to the final research correction, keep the people, resources and next steps connected.',
      ),
      'how-it-works' => 
      array (
        'title' => 'From a starting point to your next milestone',
        'description' => 'See how to join AcadSyntra, connect your academic context and move through learning, research and publishing workflows.',
        'intro' => 'Begin with your role. Build around your work. Keep the context as you move forward.',
      ),
      'about' => 
      array (
        'title' => 'Built around the way academic work connects',
        'description' => 'Learn why AcadSyntra brings learning, teaching, research and academic publishing into one connected workspace.',
        'intro' => 'A course can spark a question. A question can become a research project. A project can become knowledge worth sharing. AcadSyntra gives that journey a place to happen.',
        'sections' => 
        array (
          0 => 
          array (
            0 => 'Our purpose',
            1 => 'Academic work too often lives across scattered files, message threads and disconnected tools. AcadSyntra brings learning, research, publishing and institution workflows into a shared environment, so the context stays with the work.',
          ),
          1 => 
          array (
            0 => 'Who we build for',
            1 => 'Students need a clearer view of course tasks and feedback. Lecturers need resources, submissions and discussions together. Researchers need room to write, organize references and respond to review. Institutions need a consistent way to coordinate people and academic structures. Independent members need a route to research and knowledge sharing without pretending to belong to an institution.',
          ),
          2 => 
          array (
            0 => 'Connected, without losing boundaries',
            1 => 'Research Studio supports formal research projects. Knowledge Hub supports discovery, publications and communities. Courses support teaching and learning. Shared identity and permissions connect these workflows while keeping restricted records within their authorized scope.',
          ),
          3 => 
          array (
            0 => 'People keep the final say',
            1 => 'Academic assistants can help explain, structure and explore. They do not replace a lecturer’s grading decision, a supervisor’s approval or a creator’s responsibility for attribution. Reviews and institutional rules remain part of the process.',
          ),
          4 => 
          array (
            0 => 'Our direction',
            1 => 'We want academic software to make the next useful action easier to find: a resource to study, a chapter to improve, a correction to resolve or an idea to share. We build on the workflows already in AcadSyntra, with configuration that lets institutions choose what fits their context.',
          ),
        ),
      ),
      'contact' => 
      array (
        'title' => 'Let’s find your next step',
        'description' => 'Contact the AcadSyntra platform team for account help, institution enquiries, product questions or privacy requests.',
        'intro' => 'A question about your account, your institution or the platform? Send the team a little context and a way to reach you.',
      ),
      'faq' => 
      array (
        'title' => 'Good questions. Clearer answers.',
        'description' => 'Answers to common questions about AcadSyntra accounts, courses, research, AI, feature access, privacy and support.',
        'intro' => 'A practical guide to getting started and getting more from your academic workspace.',
      ),
      'help' => 
      array (
        'title' => 'A little guidance goes a long way',
        'description' => 'Find help with AcadSyntra onboarding, account access, courses, Research Studio, publishing and notifications.',
        'intro' => 'Start with a topic below, search the common questions or contact the platform team for help with your account.',
      ),
      'privacy' => 
      array (
        'title' => 'Privacy Policy',
        'description' => 'How this AcadSyntra installation processes account, academic, publication, payment, support and technical information.',
        'intro' => 'This notice describes the data flows supported by AcadSyntra. The organization operating this installation is responsible for its processing decisions and deployment-specific privacy information.',
        'review' => 'Operator review required: confirm the legal operator and privacy contact, applicable legal bases, hosting and provider locations, international-transfer safeguards, retention schedules, and procedures for children’s and other data-subject rights before relying on this notice as a final legal policy.',
        'sections' => 
        array (
          0 => 
          array (
            0 => 'Information you provide',
            1 => 'Depending on the workflows you use, the platform stores account and profile details, institution membership, course enrollments, assignments and submission records, attendance, research drafts and references, publication content, discussions and preferences. The contact form stores your name, email, topic and message in a restricted support inbox. Do not submit unnecessary sensitive information.',
          ),
          1 => 
          array (
            0 => 'Technical and activity information',
            1 => 'Authentication sessions, IP addresses in relevant security and activity records, device/browser information, audit events, notification subscriptions, search and discovery signals, and AI usage records can be processed to operate and protect the service. The contact inbox does not separately store your IP address; infrastructure and abuse-prevention logs may still process it.',
          ),
          2 => 
          array (
            0 => 'Why information is used',
            1 => 'Information supports account access, academic workflows, collaboration, publishing, personalization where enabled, support, billing, notification delivery, moderation and security. Your institution and the platform operator determine the applicable purposes and lawful grounds in their context. Optional capabilities depend on configuration and permissions.',
          ),
          3 => 
          array (
            0 => 'Visibility and sharing',
            1 => 'Public profiles and publications can be visible to other visitors according to their visibility settings. Restricted academic and institutional records use role and institution access rules. Authorized administrators and service providers may process information needed for their responsibilities. Review publication audiences before sharing; public material can be copied by others.',
          ),
          4 => 
          array (
            0 => 'AI, payment and delivery providers',
            1 => 'When an AI tool is used, authorized prompts, document excerpts or related context may be sent to the configured provider. Payment initiation sends transaction details to the configured gateway; this application records references and outcomes rather than asking for card details in the contact form. Email and browser-push providers process the information needed for delivery. Provider identities, locations and terms depend on this installation and should be confirmed with the operator.',
          ),
          5 => 
          array (
            0 => 'Storage and retention',
            1 => 'Records and uploads are stored in the database and configured file storage. Retention varies by record type, institution requirements, security needs and the operator’s policies; the application does not apply a single automatic deletion period to all data. Contact messages remain in the support inbox until an authorized operator deletes them; marking a message closed does not delete it. Backups and infrastructure logs may have different retention periods.',
          ),
          6 => 
          array (
            0 => 'Your choices and requests',
            1 => 'Use available profile visibility, recommendation and notification controls to manage your experience. You can ask the operator about access, correction, deletion, restriction, objection or portability rights where applicable through the contact form. Identity may need to be checked, and academic, financial or other legal obligations can affect what can be removed. The form is a request channel, not an automatic account-deletion tool.',
          ),
          7 => 
          array (
            0 => 'Security and browser storage',
            1 => 'The application uses authenticated access, permissions, institution scoping and other security controls. Protection also depends on hosting, configuration and operational practices; no system can promise absolute security. See the Cookie Policy for sessions, local storage, offline caches and browser notifications.',
          ),
          8 => 
          array (
            0 => 'Questions and changes',
            1 => 'Use the contact page for privacy questions and identify the relevant institution when appropriate. Do not include confidential records in the initial message. The operator must publish any additional jurisdiction-specific rights, complaint routes and material policy changes relevant to its deployment.',
          ),
        ),
      ),
      'terms' => 
      array (
        'title' => 'Terms of Service',
        'description' => 'Responsibilities and boundaries for accounts, academic content, acceptable use and commercial features on AcadSyntra.',
        'intro' => 'These service terms explain expectations for using this installation alongside applicable institutional rules and the terms shown for specific purchases or services.',
        'review' => 'Operator review required: confirm the contracting organization, eligibility and minimum age, governing law, dispute process, enforceable liability terms, commercial/refund terms and effective date. This text does not invent those business decisions.',
        'sections' => 
        array (
          0 => 
          array (
            0 => 'Accounts and eligibility',
            1 => 'Provide accurate information, use only accounts and institutional roles you are entitled to hold, and keep passwords, verification links and recovery codes private. Tell the platform operator if you suspect unauthorized access. Institutional membership and elevated roles may require approval.',
          ),
          1 => 
          array (
            0 => 'Acceptable use',
            1 => 'Do not impersonate others, harass people, upload malware, bypass access controls, manipulate engagement or reputation, misuse assessment materials, or publish content unlawfully. Do not use the platform to expose confidential research or personal information without authorization.',
          ),
          2 => 
          array (
            0 => 'Academic responsibilities',
            1 => 'You remain responsible for originality, attribution, permissions, research ethics and compliance with institutional rules. AI outputs and similarity indicators can be incomplete or inaccurate. They do not establish academic misconduct or replace authorized human grading, review and approval.',
          ),
          3 => 
          array (
            0 => 'Content and intellectual property',
            1 => 'Only upload or publish material you have the right to use. Rights in your original work remain subject to ownership, institutional agreements and applicable law. You authorize the technical processing needed to store, display and deliver material to the audience you choose; broader commercial licensing must be agreed separately. Respect third-party rights and report suspected infringement through the contact page.',
          ),
          4 => 
          array (
            0 => 'Access, payments and optional services',
            1 => 'Features depend on your role, institution, permissions, module availability and any applicable commercial access. Review displayed prices, charges, product-specific terms and refund information before paying. Payment gateways and external providers may apply their own terms. These public pages do not guarantee a free plan, a particular provider or universal feature access.',
          ),
          5 => 
          array (
            0 => 'Service limitations',
            1 => 'Availability depends on infrastructure and configured integrations. Maintenance, outages and provider failures can affect access. Keep appropriate copies of important work according to your institution’s rules. Product previews use illustrative data and do not guarantee every account has the same screen or feature set.',
          ),
          6 => 
          array (
            0 => 'Moderation, suspension and leaving',
            1 => 'Authorized staff can review reports and apply access restrictions according to platform and institution policy. You may ask the operator about closing an account and handling associated data. Closing access does not necessarily remove records that must be retained for academic, financial, security or legal reasons.',
          ),
          7 => 
          array (
            0 => 'Questions and applicable terms',
            1 => 'Contact the platform team for the operator’s identity, commercial arrangements and terms that apply to your institution. Any additional enforceable conditions must be published by the operator and communicated as required by applicable law.',
          ),
        ),
      ),
      'cookies' => 
      array (
        'title' => 'Cookie Policy',
        'description' => 'Understand AcadSyntra session cookies, CSRF protection, local preferences, offline storage and push subscriptions.',
        'intro' => 'AcadSyntra uses cookies and related browser storage for authentication, security and features you choose to use. The public website does not load advertising pixels or third-party analytics scripts.',
        'review' => 'Operator review required: audit any hosting/CDN cookies and scripts added to this deployment. If optional tracking is introduced, implement any required consent before it loads and update this notice.',
        'sections' => 
        array (
          0 => 
          array (
            0 => 'Session cookie',
            1 => 'The configured session cookie keeps requests associated with your browser session, including form state and signed-in access. Its name and lifetime are deployment settings; the default lifetime is 120 minutes. Session storage and browser-close behavior depend on the operator’s configuration.',
          ),
          1 => 
          array (
            0 => 'CSRF and remembered sign-in',
            1 => 'Laravel may set XSRF-TOKEN for request-forgery protection. If you choose remembered sign-in, an authentication recaller cookie can preserve that choice. These serve authentication and security rather than advertising.',
          ),
          2 => 
          array (
            0 => 'Preferences and notifications',
            1 => 'The signed-in push feature uses the acadflow_push_account local-storage key to reconcile a device with the current account. A browser push subscription is created only after you enable it and grant permission; the server stores the endpoint and delivery keys. Disable it from Notification Preferences or your browser settings.',
          ),
          3 => 
          array (
            0 => 'Offline caches and local data',
            1 => 'When PWA/offline features are enabled, a service worker can cache application assets and supported responses, and IndexedDB can hold supported pending offline actions. This public marketing layout does not register a service worker. On shared devices, sign out and use browser site-data controls where appropriate.',
          ),
          4 => 
          array (
            0 => 'Managing storage',
            1 => 'Browser controls let you block or clear cookies, local storage, cached data and notification permissions. Clearing necessary storage may sign you out, reset preferences or remove unsynchronized offline actions. Keep any important unsent work before clearing site data.',
          ),
          5 => 
          array (
            0 => 'Analytics and consent',
            1 => 'This public website adds no optional tracking tools and does not display a misleading “accept all” banner for essential storage alone. The wider application can record server-side usage and discovery events as described in the Privacy Policy. Any future optional tracking needs its own assessment and appropriate controls.',
          ),
        ),
      ),
      'disclaimer' => 
      array (
        'title' => 'Academic & product disclaimer',
        'description' => 'Understand the limits of academic assistance, product previews, institutional decisions and external resources on AcadSyntra.',
        'intro' => 'AcadSyntra helps organize work and support decisions. The people responsible for that work keep their academic and professional responsibilities.',
        'sections' => 
        array (
          0 => 
          array (
            0 => 'Academic and AI assistance',
            1 => 'Explanations, generated drafts, recommendations and similarity signals are aids to review. Verify sources, attribution and accuracy. The software does not award institutional approval merely because a workflow or assistant has been used.',
          ),
          1 => 
          array (
            0 => 'Product demonstrations',
            1 => 'Public previews are interface illustrations using fictional example work. They are not customer records, endorsements or a promise of results. Screens and available actions can differ with role, institution, configuration and commercial access.',
          ),
          2 => 
          array (
            0 => 'External resources',
            1 => 'Linked publications, third-party providers and community contributions are not automatically endorsed or independently verified by the platform operator. Assess their reliability and terms before relying on them.',
          ),
        ),
      ),
      'security' => 
      array (
        'title' => 'Security & responsible use',
        'description' => 'How AcadSyntra approaches authorized access, academic records, uploads and private security reports.',
        'intro' => 'Access to academic work should follow the people, roles and institution boundaries attached to it.',
        'sections' => 
        array (
          0 => 
          array (
            0 => 'Access controls',
            1 => 'The application uses authentication, role and policy checks, institution scoping, rate limits and configurable account-security controls. Email verification and two-factor requirements depend on operator settings.',
          ),
          1 => 
          array (
            0 => 'Documents and downloads',
            1 => 'Uploads have validation and access rules. Malware scanning depends on the scanner configured by the deployment operator; it must not be assumed to be active everywhere. Restricted media uses authorized delivery paths and expiring access where implemented.',
          ),
          2 => 
          array (
            0 => 'Report a concern privately',
            1 => 'Use the contact page with the security topic. Describe the affected area without sending passwords, live secrets or other people’s private records. The public website does not claim an independent security certification or promise a response deadline.',
          ),
        ),
      ),
    ),
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => '',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 360,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 360,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => '',
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 360,
        'block_for' => 2,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
    ),
    'batching' => 
    array (
      'database' => 'sqlite',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'sqlite',
      'table' => 'failed_jobs',
    ),
  ),
  'reputation' => 
  array (
    'weights' => 
    array (
      'knowledge' => 0.3,
      'quality' => 0.3,
      'research_impact' => 0.3,
      'community' => 0.1,
    ),
    'points' => 
    array (
      'publication' => 12.0,
      'bookmark' => 0.7,
      'view' => 0.02,
      'citation' => 5.0,
      'featured_publication' => 8.0,
      'approved_research_quality' => 10.0,
      'citation_research_impact' => 8.0,
      'approved_research_impact' => 15.0,
      'research_output' => 6.0,
      'follower' => 2.0,
      'comment' => 0.8,
      'reaction' => 0.4,
    ),
    'levels' => 
    array (
      0 => 
      array (
        'key' => 'hall_of_knowledge',
        'name' => 'Hall of Knowledge',
        'minimum' => 2500.0,
      ),
      1 => 
      array (
        'key' => 'professor_circle',
        'name' => 'Professor Circle',
        'minimum' => 1500.0,
      ),
      2 => 
      array (
        'key' => 'distinguished_researcher',
        'name' => 'Distinguished Researcher',
        'minimum' => 1000.0,
      ),
      3 => 
      array (
        'key' => 'research_fellow',
        'name' => 'Research Fellow',
        'minimum' => 700.0,
      ),
      4 => 
      array (
        'key' => 'national_contributor',
        'name' => 'National Contributor',
        'minimum' => 450.0,
      ),
      5 => 
      array (
        'key' => 'university_scholar',
        'name' => 'University Scholar',
        'minimum' => 250.0,
      ),
      6 => 
      array (
        'key' => 'faculty_expert',
        'name' => 'Faculty Expert',
        'minimum' => 120.0,
      ),
      7 => 
      array (
        'key' => 'department_expert',
        'name' => 'Department Expert',
        'minimum' => 60.0,
      ),
      8 => 
      array (
        'key' => 'campus_contributor',
        'name' => 'Campus Contributor',
        'minimum' => 20.0,
      ),
      9 => 
      array (
        'key' => 'new_contributor',
        'name' => 'New Contributor',
        'minimum' => 0.0,
      ),
    ),
  ),
  'research_methodologies' => 
  array (
    'version' => 1,
    'levels' => 
    array (
      'nd' => 'National Diploma (ND)',
      'hnd' => 'Higher National Diploma (HND)',
      'undergraduate' => 'Undergraduate',
      'postgraduate' => 'Postgraduate diploma',
      'masters' => 'Master’s',
      'doctoral' => 'Doctoral',
      'professional' => 'Independent / professional',
    ),
    'styles' => 
    array (
      'apa' => 'APA',
      'ieee' => 'IEEE',
      'mla' => 'MLA',
      'chicago' => 'Chicago',
      'harvard' => 'Harvard',
      'vancouver' => 'Vancouver',
    ),
    'frameworks' => 
    array (
      'prisma' => 
      array (
        'label' => 'PRISMA 2020',
        'url' => 'https://www.prisma-statement.org/prisma-2020',
        'scope' => 'Systematic reviews; choose relevant extensions.',
        'checks' => 
        array (
          0 => 'Protocol, registration status and changes disclosed',
          1 => 'Search strategies, databases and dates documented',
          2 => 'Eligibility and screening decisions traceable',
          3 => 'Selection flow and exclusion reasons accounted for',
          4 => 'Appraisal, synthesis and uncertainty explained',
          5 => 'Availability of extraction data and limitations stated',
        ),
      ),
      'prisma_scr' => 
      array (
        'label' => 'PRISMA-ScR',
        'url' => 'https://www.prisma-statement.org/scoping',
        'scope' => 'Scoping reviews.',
        'checks' => 
        array (
          0 => 'Review purpose and scope justified',
          1 => 'Population, concept and context criteria explicit',
          2 => 'Search and selection process reproducible',
          3 => 'Charting fields and evidence map described',
          4 => 'Limitations and implications supported',
        ),
      ),
      'consort' => 
      array (
        'label' => 'CONSORT 2025',
        'url' => 'https://www.consort-spirit.org/',
        'scope' => 'Randomised trial results only; not every clinical study.',
        'checks' => 
        array (
          0 => 'Trial registration and protocol availability documented',
          1 => 'Allocation and concealment procedures described',
          2 => 'Outcomes and analysis population prespecified',
          3 => 'Participant flow and missing outcomes explained',
          4 => 'Harms and uncertainty reported alongside effects',
          5 => 'Funding, conflicts and data-sharing arrangements disclosed',
        ),
      ),
      'spirit' => 
      array (
        'label' => 'SPIRIT 2025',
        'url' => 'https://www.consort-spirit.org/',
        'scope' => 'Randomised trial protocols.',
        'checks' => 
        array (
          0 => 'Trial rationale, objectives and design specified',
          1 => 'Interventions, outcomes and visit schedule defined',
          2 => 'Allocation and sample-size rationale documented',
          3 => 'Analysis, missingness and monitoring plans explicit',
          4 => 'Ethics, consent, registration and dissemination arrangements documented',
        ),
      ),
      'strobe' => 
      array (
        'label' => 'STROBE',
        'url' => 'https://www.strobe-statement.org/',
        'scope' => 'Epidemiological cohort, case-control and cross-sectional studies.',
        'checks' => 
        array (
          0 => 'Design, setting and observation dates explicit',
          1 => 'Recruitment and selection criteria documented',
          2 => 'Variables, measurements and confounding addressed',
          3 => 'Missingness, bias and analysis decisions explained',
          4 => 'Estimates, uncertainty and limitations reported',
        ),
      ),
      'coreq' => 
      array (
        'label' => 'COREQ',
        'url' => 'https://www.equator-network.org/reporting-guidelines/coreq/',
        'scope' => 'Qualitative interviews and focus groups.',
        'checks' => 
        array (
          0 => 'Interviewer background and participant relationships disclosed',
          1 => 'Recruitment, setting and guide development explained',
          2 => 'Recording, transcription and analytic process described',
          3 => 'Quotes linked to anonymised source identifiers',
          4 => 'Themes and differing views supported by evidence',
        ),
      ),
      'srqr' => 
      array (
        'label' => 'SRQR',
        'url' => 'https://www.equator-network.org/reporting-guidelines/srqr/',
        'scope' => 'Qualitative research reporting.',
        'checks' => 
        array (
          0 => 'Research orientation and context explicit',
          1 => 'Researcher position and reflexivity documented',
          2 => 'Sampling and evidence-generation rationale explained',
          3 => 'Analysis and trustworthiness procedures transparent',
          4 => 'Interpretations linked to actual evidence and limitations',
        ),
      ),
    ),
    'methodologies' => 
    array (
      'quantitative' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Specify constructs, variables and a testable analysis plan.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Quantitative research',
        'family' => 'quantitative',
        'decision' => 'Specify constructs, variables and a testable analysis plan.',
      ),
      'qualitative' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Justify the interpretive approach, reflexivity and evidence trail.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Qualitative research',
        'family' => 'qualitative',
        'decision' => 'Justify the interpretive approach, reflexivity and evidence trail.',
      ),
      'mixed_methods' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Choose convergent, explanatory sequential or exploratory sequential integration.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Mixed-methods research',
        'family' => 'mixed',
        'decision' => 'Choose convergent, explanatory sequential or exploratory sequential integration.',
      ),
      'experimental' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Allocation and intervention protocol',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Between-group effects and uncertainty',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Document allocation, controls, blinding, intervention fidelity and causal assumptions.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Experimental research',
        'family' => 'quantitative',
        'decision' => 'Document allocation, controls, blinding, intervention fidelity and causal assumptions.',
      ),
      'quasi_experimental' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Group comparability and confounder register',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Adjusted comparisons or difference-in-differences where justified',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Address non-random assignment, baseline imbalance and confounding.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Quasi-experimental research',
        'family' => 'quantitative',
        'decision' => 'Address non-random assignment, baseline imbalance and confounding.',
      ),
      'descriptive' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Descriptive variable codebook',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Frequency distributions and precision',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Define the population and estimation targets without causal claims.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Descriptive research',
        'family' => 'quantitative',
        'decision' => 'Define the population and estimation targets without causal claims.',
      ),
      'exploratory' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Exploratory decision journal',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Pattern exploration and triangulation',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Document evolving questions and clearly label exploratory analyses.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Exploratory research',
        'family' => 'mixed',
        'decision' => 'Document evolving questions and clearly label exploratory analyses.',
      ),
      'explanatory' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Mechanism and rival explanation map',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Explanatory integration',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Specify mechanisms and evaluate alternative explanations.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Explanatory research',
        'family' => 'mixed',
        'decision' => 'Specify mechanisms and evaluate alternative explanations.',
      ),
      'correlational' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Variable-pair and confounder plan',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Pearson or rank correlation as justified',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Assess measurement scale, association, outliers and non-causal interpretation.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Correlational research',
        'family' => 'quantitative',
        'decision' => 'Assess measurement scale, association, outliers and non-causal interpretation.',
      ),
      'survey' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Questionnaire and pilot feedback',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Survey estimates and non-response assessment',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Document recruitment, non-response, questionnaire pilot and sampling weights.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Survey research',
        'family' => 'quantitative',
        'decision' => 'Document recruitment, non-response, questionnaire pilot and sampling weights.',
      ),
      'interviews' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Semi-structured interview guide',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Case comparison and thematic interpretation',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Record interviewer relationships, guide development and transcription decisions.',
        ),
        'frameworks' => 
        array (
          0 => 'coreq',
          1 => 'srqr',
        ),
        'label' => 'Interview study',
        'family' => 'qualitative',
        'decision' => 'Record interviewer relationships, guide development and transcription decisions.',
      ),
      'focus_groups' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Moderator guide and group context log',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Interaction-sensitive thematic analysis',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Address group dynamics, moderation, confidentiality and dissenting views.',
        ),
        'frameworks' => 
        array (
          0 => 'coreq',
          1 => 'srqr',
        ),
        'label' => 'Focus groups',
        'family' => 'qualitative',
        'decision' => 'Address group dynamics, moderation, confidentiality and dissenting views.',
      ),
      'observation' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Observation schedule and field diary',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Specify observer role, field access, context and recording procedures.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Observational study',
        'family' => 'qualitative',
        'decision' => 'Specify observer role, field access, context and recording procedures.',
      ),
      'case_study' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Case boundary and evidence matrix',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Within-case and cross-case analysis',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Define case boundaries, units of analysis and multiple evidence sources.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Case study',
        'family' => 'qualitative',
        'decision' => 'Define case boundaries, units of analysis and multiple evidence sources.',
      ),
      'ethnography' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Ethnographic field diary',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Cultural interpretation and thick description',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Explain immersion, field relationships, positionality and representation.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Ethnography',
        'family' => 'qualitative',
        'decision' => 'Explain immersion, field relationships, positionality and representation.',
      ),
      'phenomenology' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Lived-experience interview and reflexive memo',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Meaning units and experiential interpretation',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Choose a phenomenological tradition and document lived-experience interpretation.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Phenomenology',
        'family' => 'qualitative',
        'decision' => 'Choose a phenomenological tradition and document lived-experience interpretation.',
      ),
      'grounded_theory' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Theoretical sampling and category memo',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Constant comparison and category development',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Document theoretical sampling, constant comparison and theory development.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Grounded theory',
        'family' => 'qualitative',
        'decision' => 'Document theoretical sampling, constant comparison and theory development.',
      ),
      'narrative' => 
      array (
        'sections' => 
        array (
          0 => 'Research context and questions',
          1 => 'Literature and conceptual position',
          2 => 'Methodology and reflexivity',
          3 => 'Recruitment and data generation',
          4 => 'Findings and themes',
          5 => 'Interpretation and trustworthiness',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the inquiry',
          1 => 'Choose epistemology and sampling',
          2 => 'Review ethics and develop guides',
          3 => 'Generate and document evidence',
          4 => 'Code, compare and develop interpretations',
          5 => 'Review reflexivity and report',
        ),
        'instruments' => 
        array (
          0 => 'Interview guide',
          1 => 'Observation field notes',
          2 => 'Reflexive journal',
          3 => 'Coding and theme record',
          4 => 'Narrative chronology',
        ),
        'analysis' => 
        array (
          0 => 'Qualitative coding',
          1 => 'Thematic interpretation',
          2 => 'Negative cases and reflexive memos',
          3 => 'Narrative structure and positioning',
        ),
        'requirements' => 
        array (
          0 => 'Researcher positionality and relationships',
          1 => 'Recruitment rationale and information sufficiency',
          2 => 'Evidence trail linking excerpts to interpretations',
          3 => 'Preserve sequence, voice, context and co-construction of accounts.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Narrative inquiry',
        'family' => 'qualitative',
        'decision' => 'Preserve sequence, voice, context and co-construction of accounts.',
      ),
      'action_research' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Action cycle and reflection log',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Cycle evaluation and practitioner reflection',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Document iterative plan, act, observe and reflect cycles with participants.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Action research',
        'family' => 'mixed',
        'decision' => 'Document iterative plan, act, observe and reflect cycles with participants.',
      ),
      'participatory' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Participation and shared decision agreement',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Co-analysis and community validation',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Record shared governance, power relationships and community ownership.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Participatory research',
        'family' => 'mixed',
        'decision' => 'Record shared governance, power relationships and community ownership.',
      ),
      'longitudinal' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Wave schedule and retention plan',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Repeated-measure or longitudinal modelling',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Specify waves, retention, attrition and within-person dependence.',
        ),
        'frameworks' => 
        array (
          0 => 'strobe',
        ),
        'label' => 'Longitudinal study',
        'family' => 'quantitative',
        'decision' => 'Specify waves, retention, attrition and within-person dependence.',
      ),
      'cross_sectional' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Observation-window and recruitment log',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Prevalence and association estimates',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Define the observation window, selection bias and temporal limitations.',
        ),
        'frameworks' => 
        array (
          0 => 'strobe',
        ),
        'label' => 'Cross-sectional study',
        'family' => 'quantitative',
        'decision' => 'Define the observation window, selection bias and temporal limitations.',
      ),
      'systematic_review' => 
      array (
        'sections' => 
        array (
          0 => 'Review question and rationale',
          1 => 'Protocol and eligibility criteria',
          2 => 'Search strategy',
          3 => 'Screening and selection',
          4 => 'Extraction and quality appraisal',
          5 => 'Synthesis of evidence',
          6 => 'Discussion and limitations',
        ),
        'stages' => 
        array (
          0 => 'Specify review question and protocol',
          1 => 'Document reproducible search',
          2 => 'Screen and record exclusion reasons',
          3 => 'Extract and appraise evidence',
          4 => 'Synthesize included studies',
          5 => 'Report search dates and selection flow',
        ),
        'instruments' => 
        array (
          0 => 'Search strategy log',
          1 => 'Screening and exclusion log',
          2 => 'Evidence extraction matrix',
        ),
        'analysis' => 
        array (
          0 => 'Evidence synthesis',
          1 => 'Risk of bias assessment',
          2 => 'Heterogeneity and certainty assessment',
        ),
        'requirements' => 
        array (
          0 => 'Eligibility criteria and dated database searches',
          1 => 'Traceable screening decisions',
          2 => 'Source provenance and duplicate handling',
          3 => 'Pre-specify the protocol, screening process and risk-of-bias approach.',
        ),
        'frameworks' => 
        array (
          0 => 'prisma',
        ),
        'label' => 'Systematic review',
        'family' => 'review',
        'decision' => 'Pre-specify the protocol, screening process and risk-of-bias approach.',
      ),
      'scoping_review' => 
      array (
        'sections' => 
        array (
          0 => 'Review question and rationale',
          1 => 'Protocol and eligibility criteria',
          2 => 'Search strategy',
          3 => 'Screening and selection',
          4 => 'Extraction and quality appraisal',
          5 => 'Synthesis of evidence',
          6 => 'Discussion and limitations',
        ),
        'stages' => 
        array (
          0 => 'Specify review question and protocol',
          1 => 'Document reproducible search',
          2 => 'Screen and record exclusion reasons',
          3 => 'Extract and appraise evidence',
          4 => 'Synthesize included studies',
          5 => 'Report search dates and selection flow',
        ),
        'instruments' => 
        array (
          0 => 'Search strategy log',
          1 => 'Screening and exclusion log',
          2 => 'Evidence extraction matrix',
          3 => 'PCC eligibility and charting form',
        ),
        'analysis' => 
        array (
          0 => 'Evidence synthesis',
          1 => 'Risk of bias assessment',
          2 => 'Heterogeneity and certainty assessment',
          3 => 'Evidence mapping and descriptive charting',
        ),
        'requirements' => 
        array (
          0 => 'Eligibility criteria and dated database searches',
          1 => 'Traceable screening decisions',
          2 => 'Source provenance and duplicate handling',
          3 => 'Map concepts and evidence types using a PCC question and charting process.',
        ),
        'frameworks' => 
        array (
          0 => 'prisma_scr',
        ),
        'label' => 'Scoping review',
        'family' => 'review',
        'decision' => 'Map concepts and evidence types using a PCC question and charting process.',
      ),
      'narrative_review' => 
      array (
        'sections' => 
        array (
          0 => 'Review question and rationale',
          1 => 'Protocol and eligibility criteria',
          2 => 'Search strategy',
          3 => 'Screening and selection',
          4 => 'Extraction and quality appraisal',
          5 => 'Synthesis of evidence',
          6 => 'Discussion and limitations',
        ),
        'stages' => 
        array (
          0 => 'Specify review question and protocol',
          1 => 'Document reproducible search',
          2 => 'Screen and record exclusion reasons',
          3 => 'Extract and appraise evidence',
          4 => 'Synthesize included studies',
          5 => 'Report search dates and selection flow',
        ),
        'instruments' => 
        array (
          0 => 'Search strategy log',
          1 => 'Screening and exclusion log',
          2 => 'Evidence extraction matrix',
          3 => 'Narrative review selection rationale',
        ),
        'analysis' => 
        array (
          0 => 'Evidence synthesis',
          1 => 'Risk of bias assessment',
          2 => 'Heterogeneity and certainty assessment',
          3 => 'Critical thematic synthesis',
        ),
        'requirements' => 
        array (
          0 => 'Eligibility criteria and dated database searches',
          1 => 'Traceable screening decisions',
          2 => 'Source provenance and duplicate handling',
          3 => 'Explain scope and source selection without claiming systematic completeness.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Narrative literature review',
        'family' => 'review',
        'decision' => 'Explain scope and source selection without claiming systematic completeness.',
      ),
      'meta_analysis' => 
      array (
        'sections' => 
        array (
          0 => 'Review question and rationale',
          1 => 'Protocol and eligibility criteria',
          2 => 'Search strategy',
          3 => 'Screening and selection',
          4 => 'Extraction and quality appraisal',
          5 => 'Synthesis of evidence',
          6 => 'Discussion and limitations',
        ),
        'stages' => 
        array (
          0 => 'Specify review question and protocol',
          1 => 'Document reproducible search',
          2 => 'Screen and record exclusion reasons',
          3 => 'Extract and appraise evidence',
          4 => 'Synthesize included studies',
          5 => 'Report search dates and selection flow',
        ),
        'instruments' => 
        array (
          0 => 'Search strategy log',
          1 => 'Screening and exclusion log',
          2 => 'Evidence extraction matrix',
          3 => 'Effect size and variance extraction form',
        ),
        'analysis' => 
        array (
          0 => 'Evidence synthesis',
          1 => 'Risk of bias assessment',
          2 => 'Heterogeneity and certainty assessment',
          3 => 'Effect-size pooling, heterogeneity and sensitivity analysis',
        ),
        'requirements' => 
        array (
          0 => 'Eligibility criteria and dated database searches',
          1 => 'Traceable screening decisions',
          2 => 'Source provenance and duplicate handling',
          3 => 'Pre-specify effect metrics, pooling model, dependence and sensitivity analyses.',
        ),
        'frameworks' => 
        array (
          0 => 'prisma',
        ),
        'label' => 'Meta-analysis',
        'family' => 'review',
        'decision' => 'Pre-specify effect metrics, pooling model, dependence and sensitivity analyses.',
      ),
      'bibliometric' => 
      array (
        'sections' => 
        array (
          0 => 'Review question and rationale',
          1 => 'Protocol and eligibility criteria',
          2 => 'Search strategy',
          3 => 'Screening and selection',
          4 => 'Extraction and quality appraisal',
          5 => 'Synthesis of evidence',
          6 => 'Discussion and limitations',
        ),
        'stages' => 
        array (
          0 => 'Specify review question and protocol',
          1 => 'Document reproducible search',
          2 => 'Screen and record exclusion reasons',
          3 => 'Extract and appraise evidence',
          4 => 'Synthesize included studies',
          5 => 'Report search dates and selection flow',
        ),
        'instruments' => 
        array (
          0 => 'Search strategy log',
          1 => 'Screening and exclusion log',
          2 => 'Evidence extraction matrix',
          3 => 'Bibliographic cleaning and disambiguation log',
        ),
        'analysis' => 
        array (
          0 => 'Evidence synthesis',
          1 => 'Risk of bias assessment',
          2 => 'Heterogeneity and certainty assessment',
          3 => 'Co-citation, co-authorship and keyword networks',
        ),
        'requirements' => 
        array (
          0 => 'Eligibility criteria and dated database searches',
          1 => 'Traceable screening decisions',
          2 => 'Source provenance and duplicate handling',
          3 => 'Record database coverage, query, deduplication and author disambiguation.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Bibliometric research',
        'family' => 'review',
        'decision' => 'Record database coverage, query, deduplication and author disambiguation.',
      ),
      'historical' => 
      array (
        'sections' => 
        array (
          0 => 'Research question and context',
          1 => 'Scholarship and conceptual framework',
          2 => 'Corpus and interpretive method',
          3 => 'Analysis and argument',
          4 => 'Counterarguments and interpretation',
          5 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define question and corpus',
          1 => 'Establish provenance and context',
          2 => 'Select interpretive approach',
          3 => 'Analyse primary sources',
          4 => 'Evaluate competing interpretations',
          5 => 'Develop and defend argument',
        ),
        'instruments' => 
        array (
          0 => 'Source and provenance register',
          1 => 'Analytical memo',
          2 => 'Argument and counterargument map',
          3 => 'Archival source criticism register',
        ),
        'analysis' => 
        array (
          0 => 'Source criticism',
          1 => 'Interpretive analysis',
          2 => 'Comparative argumentation',
          3 => 'Chronological and contextual interpretation',
        ),
        'requirements' => 
        array (
          0 => 'Primary versus secondary sources distinguished',
          1 => 'Transparent selection and interpretive assumptions',
          2 => 'Counter-evidence and rival explanations',
          3 => 'Assess archival provenance, chronology and source reliability.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Historical research',
        'family' => 'humanities',
        'decision' => 'Assess archival provenance, chronology and source reliability.',
      ),
      'philosophical' => 
      array (
        'sections' => 
        array (
          0 => 'Research question and context',
          1 => 'Scholarship and conceptual framework',
          2 => 'Corpus and interpretive method',
          3 => 'Analysis and argument',
          4 => 'Counterarguments and interpretation',
          5 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define question and corpus',
          1 => 'Establish provenance and context',
          2 => 'Select interpretive approach',
          3 => 'Analyse primary sources',
          4 => 'Evaluate competing interpretations',
          5 => 'Develop and defend argument',
        ),
        'instruments' => 
        array (
          0 => 'Source and provenance register',
          1 => 'Analytical memo',
          2 => 'Argument and counterargument map',
          3 => 'Argument reconstruction worksheet',
        ),
        'analysis' => 
        array (
          0 => 'Source criticism',
          1 => 'Interpretive analysis',
          2 => 'Comparative argumentation',
          3 => 'Conceptual analysis and logical critique',
        ),
        'requirements' => 
        array (
          0 => 'Primary versus secondary sources distinguished',
          1 => 'Transparent selection and interpretive assumptions',
          2 => 'Counter-evidence and rival explanations',
          3 => 'Define concepts, reconstruct arguments and address counterexamples.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Philosophical research',
        'family' => 'humanities',
        'decision' => 'Define concepts, reconstruct arguments and address counterexamples.',
      ),
      'doctrinal_legal' => 
      array (
        'sections' => 
        array (
          0 => 'Research question and context',
          1 => 'Scholarship and conceptual framework',
          2 => 'Corpus and interpretive method',
          3 => 'Analysis and argument',
          4 => 'Counterarguments and interpretation',
          5 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define question and corpus',
          1 => 'Establish provenance and context',
          2 => 'Select interpretive approach',
          3 => 'Analyse primary sources',
          4 => 'Evaluate competing interpretations',
          5 => 'Develop and defend argument',
        ),
        'instruments' => 
        array (
          0 => 'Source and provenance register',
          1 => 'Analytical memo',
          2 => 'Argument and counterargument map',
          3 => 'Legislation, cases and authority table',
        ),
        'analysis' => 
        array (
          0 => 'Source criticism',
          1 => 'Interpretive analysis',
          2 => 'Comparative argumentation',
          3 => 'Doctrinal synthesis and comparative legal reasoning',
        ),
        'requirements' => 
        array (
          0 => 'Primary versus secondary sources distinguished',
          1 => 'Transparent selection and interpretive assumptions',
          2 => 'Counter-evidence and rival explanations',
          3 => 'Specify jurisdiction, authority hierarchy and currency of law.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Doctrinal legal research',
        'family' => 'humanities',
        'decision' => 'Specify jurisdiction, authority hierarchy and currency of law.',
      ),
      'computational' => 
      array (
        'sections' => 
        array (
          0 => 'Problem and requirements',
          1 => 'Related work and design constraints',
          2 => 'Design methodology',
          3 => 'Architecture and implementation',
          4 => 'Testing and evaluation',
          5 => 'Discussion and limitations',
          6 => 'Conclusions and future work',
        ),
        'stages' => 
        array (
          0 => 'Identify problem and stakeholders',
          1 => 'Specify acceptance criteria',
          2 => 'Design and prototype',
          3 => 'Implement with versioned artefacts',
          4 => 'Evaluate against requirements',
          5 => 'Document reproducibility and handover',
        ),
        'instruments' => 
        array (
          0 => 'Requirements matrix',
          1 => 'Design decision log',
          2 => 'Test and evaluation protocol',
          3 => 'Algorithm and environment specification',
        ),
        'analysis' => 
        array (
          0 => 'Requirements traceability',
          1 => 'Benchmark comparison',
          2 => 'Usability and performance evaluation',
          3 => 'Complexity and reproducibility evaluation',
        ),
        'requirements' => 
        array (
          0 => 'Explicit design constraints and alternatives',
          1 => 'Reproducible build and evaluation procedure',
          2 => 'Evidence of testing, failures and limitations',
          3 => 'Version algorithms, environments, dependencies and reproducibility seeds.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Computational research',
        'family' => 'design',
        'decision' => 'Version algorithms, environments, dependencies and reproducibility seeds.',
      ),
      'simulation' => 
      array (
        'sections' => 
        array (
          0 => 'Problem and requirements',
          1 => 'Related work and design constraints',
          2 => 'Design methodology',
          3 => 'Architecture and implementation',
          4 => 'Testing and evaluation',
          5 => 'Discussion and limitations',
          6 => 'Conclusions and future work',
        ),
        'stages' => 
        array (
          0 => 'Identify problem and stakeholders',
          1 => 'Specify acceptance criteria',
          2 => 'Design and prototype',
          3 => 'Implement with versioned artefacts',
          4 => 'Evaluate against requirements',
          5 => 'Document reproducibility and handover',
        ),
        'instruments' => 
        array (
          0 => 'Requirements matrix',
          1 => 'Design decision log',
          2 => 'Test and evaluation protocol',
          3 => 'Simulation scenario and parameter register',
        ),
        'analysis' => 
        array (
          0 => 'Requirements traceability',
          1 => 'Benchmark comparison',
          2 => 'Usability and performance evaluation',
          3 => 'Sensitivity and model validation',
        ),
        'requirements' => 
        array (
          0 => 'Explicit design constraints and alternatives',
          1 => 'Reproducible build and evaluation procedure',
          2 => 'Evidence of testing, failures and limitations',
          3 => 'Specify model assumptions, calibration, verification and sensitivity.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Simulation study',
        'family' => 'design',
        'decision' => 'Specify model assumptions, calibration, verification and sensitivity.',
      ),
      'data_science_ai' => 
      array (
        'sections' => 
        array (
          0 => 'Problem and requirements',
          1 => 'Related work and design constraints',
          2 => 'Design methodology',
          3 => 'Architecture and implementation',
          4 => 'Testing and evaluation',
          5 => 'Discussion and limitations',
          6 => 'Conclusions and future work',
        ),
        'stages' => 
        array (
          0 => 'Identify problem and stakeholders',
          1 => 'Specify acceptance criteria',
          2 => 'Design and prototype',
          3 => 'Implement with versioned artefacts',
          4 => 'Evaluate against requirements',
          5 => 'Document reproducibility and handover',
        ),
        'instruments' => 
        array (
          0 => 'Requirements matrix',
          1 => 'Design decision log',
          2 => 'Test and evaluation protocol',
          3 => 'Dataset/model card and evaluation protocol',
        ),
        'analysis' => 
        array (
          0 => 'Requirements traceability',
          1 => 'Benchmark comparison',
          2 => 'Usability and performance evaluation',
          3 => 'Held-out evaluation, calibration and error analysis',
        ),
        'requirements' => 
        array (
          0 => 'Explicit design constraints and alternatives',
          1 => 'Reproducible build and evaluation procedure',
          2 => 'Evidence of testing, failures and limitations',
          3 => 'Track data provenance, train/test separation, leakage, fairness and model limitations.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Data science and AI research',
        'family' => 'design',
        'decision' => 'Track data provenance, train/test separation, leakage, fairness and model limitations.',
      ),
      'engineering' => 
      array (
        'sections' => 
        array (
          0 => 'Problem and requirements',
          1 => 'Related work and design constraints',
          2 => 'Design methodology',
          3 => 'Architecture and implementation',
          4 => 'Testing and evaluation',
          5 => 'Discussion and limitations',
          6 => 'Conclusions and future work',
        ),
        'stages' => 
        array (
          0 => 'Identify problem and stakeholders',
          1 => 'Specify acceptance criteria',
          2 => 'Design and prototype',
          3 => 'Implement with versioned artefacts',
          4 => 'Evaluate against requirements',
          5 => 'Document reproducibility and handover',
        ),
        'instruments' => 
        array (
          0 => 'Requirements matrix',
          1 => 'Design decision log',
          2 => 'Test and evaluation protocol',
          3 => 'Design specification and safety review',
        ),
        'analysis' => 
        array (
          0 => 'Requirements traceability',
          1 => 'Benchmark comparison',
          2 => 'Usability and performance evaluation',
          3 => 'Verification against engineering requirements',
        ),
        'requirements' => 
        array (
          0 => 'Explicit design constraints and alternatives',
          1 => 'Reproducible build and evaluation procedure',
          2 => 'Evidence of testing, failures and limitations',
          3 => 'Specify safety constraints, design calculations and physical validation.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Engineering design',
        'family' => 'design',
        'decision' => 'Specify safety constraints, design calculations and physical validation.',
      ),
      'prototyping' => 
      array (
        'sections' => 
        array (
          0 => 'Problem and requirements',
          1 => 'Related work and design constraints',
          2 => 'Design methodology',
          3 => 'Architecture and implementation',
          4 => 'Testing and evaluation',
          5 => 'Discussion and limitations',
          6 => 'Conclusions and future work',
        ),
        'stages' => 
        array (
          0 => 'Identify problem and stakeholders',
          1 => 'Specify acceptance criteria',
          2 => 'Design and prototype',
          3 => 'Implement with versioned artefacts',
          4 => 'Evaluate against requirements',
          5 => 'Document reproducibility and handover',
        ),
        'instruments' => 
        array (
          0 => 'Requirements matrix',
          1 => 'Design decision log',
          2 => 'Test and evaluation protocol',
          3 => 'Prototype iteration and test log',
        ),
        'analysis' => 
        array (
          0 => 'Requirements traceability',
          1 => 'Benchmark comparison',
          2 => 'Usability and performance evaluation',
          3 => 'Prototype comparison and feasibility evaluation',
        ),
        'requirements' => 
        array (
          0 => 'Explicit design constraints and alternatives',
          1 => 'Reproducible build and evaluation procedure',
          2 => 'Evidence of testing, failures and limitations',
          3 => 'Record prototypes, acceptance criteria and feedback-driven iterations.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Prototyping project',
        'family' => 'design',
        'decision' => 'Record prototypes, acceptance criteria and feedback-driven iterations.',
      ),
      'software' => 
      array (
        'sections' => 
        array (
          0 => 'Problem and requirements',
          1 => 'Related work and design constraints',
          2 => 'Design methodology',
          3 => 'Architecture and implementation',
          4 => 'Testing and evaluation',
          5 => 'Discussion and limitations',
          6 => 'Conclusions and future work',
        ),
        'stages' => 
        array (
          0 => 'Identify problem and stakeholders',
          1 => 'Specify acceptance criteria',
          2 => 'Design and prototype',
          3 => 'Implement with versioned artefacts',
          4 => 'Evaluate against requirements',
          5 => 'Document reproducibility and handover',
        ),
        'instruments' => 
        array (
          0 => 'Requirements matrix',
          1 => 'Design decision log',
          2 => 'Test and evaluation protocol',
          3 => 'Software requirements and test traceability',
        ),
        'analysis' => 
        array (
          0 => 'Requirements traceability',
          1 => 'Benchmark comparison',
          2 => 'Usability and performance evaluation',
          3 => 'Functional, performance and usability evaluation',
        ),
        'requirements' => 
        array (
          0 => 'Explicit design constraints and alternatives',
          1 => 'Reproducible build and evaluation procedure',
          2 => 'Evidence of testing, failures and limitations',
          3 => 'Connect requirements to architecture, implementation and test evidence.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Software development research',
        'family' => 'design',
        'decision' => 'Connect requirements to architecture, implementation and test evidence.',
      ),
      'clinical' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Clinical protocol and safety reporting plan',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Prespecified clinical outcome analysis',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Select a specific study design. Ethics committee approval and any trial registration must be obtained independently.',
        ),
        'frameworks' => 
        array (
          0 => 'consort',
          1 => 'spirit',
        ),
        'label' => 'Clinical research',
        'family' => 'quantitative',
        'decision' => 'Select a specific study design. Ethics committee approval and any trial registration must be obtained independently.',
      ),
      'laboratory' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction',
          1 => 'Literature review',
          2 => 'Research design and measures',
          3 => 'Sampling and analysis plan',
          4 => 'Results',
          5 => 'Discussion',
          6 => 'Conclusions',
        ),
        'stages' => 
        array (
          0 => 'Define questions and variables',
          1 => 'Specify sampling and analysis',
          2 => 'Review ethics and pilot instruments',
          3 => 'Collect and validate data',
          4 => 'Analyse and report uncertainty',
          5 => 'Interpret and prepare submission',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Measurement codebook',
          2 => 'Data collection log',
          3 => 'Laboratory protocol and calibration record',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Effect sizes and confidence intervals',
          2 => 'Assumption checks and justified inferential tests',
          3 => 'Replicate variability and quality control',
        ),
        'requirements' => 
        array (
          0 => 'Operational definitions and measurement quality',
          1 => 'Sampling frame, power or precision justification',
          2 => 'Missing-data and bias assessment',
          3 => 'Record SOPs, calibration, replicates, controls and batch effects.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Laboratory research',
        'family' => 'quantitative',
        'decision' => 'Record SOPs, calibration, replicates, controls and batch effects.',
      ),
      'environmental' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Site sampling and quality-assurance plan',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Spatial/temporal patterns and uncertainty',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Document spatial and temporal sampling, permits and measurement calibration.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Environmental research',
        'family' => 'mixed',
        'decision' => 'Document spatial and temporal sampling, permits and measurement calibration.',
      ),
      'field' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Field protocol and site log',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Site comparison and triangulation',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Document site access, field safety, permits and context-dependent bias.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Field research',
        'family' => 'mixed',
        'decision' => 'Document site access, field safety, permits and context-dependent bias.',
      ),
      'creative_practice' => 
      array (
        'sections' => 
        array (
          0 => 'Creative inquiry and context',
          1 => 'Critical and theoretical influences',
          2 => 'Practice methodology',
          3 => 'Process and artefact portfolio',
          4 => 'Critical reflection and evaluation',
          5 => 'Contribution and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Frame the creative question',
          1 => 'Situate practice and influences',
          2 => 'Plan ethical documentation',
          3 => 'Develop and document iterations',
          4 => 'Reflect and evaluate artefacts',
          5 => 'Present portfolio and contribution',
        ),
        'instruments' => 
        array (
          0 => 'Practice journal',
          1 => 'Portfolio register',
          2 => 'Critical reflection memo',
        ),
        'analysis' => 
        array (
          0 => 'Reflective analysis',
          1 => 'Artefact critique',
          2 => 'Audience or peer evaluation',
        ),
        'requirements' => 
        array (
          0 => 'Document iterations and creative decisions',
          1 => 'Copyright, consent and attribution',
          2 => 'Explain how practice contributes knowledge',
          3 => 'Link documented creative practice to an explicit research contribution.',
        ),
        'frameworks' => 
        array (
        ),
        'label' => 'Creative practice-based research',
        'family' => 'practice',
        'decision' => 'Link documented creative practice to an explicit research contribution.',
      ),
      'interdisciplinary' => 
      array (
        'sections' => 
        array (
          0 => 'Introduction and integrated questions',
          1 => 'Literature and mixed-methods rationale',
          2 => 'Design and integration plan',
          3 => 'Quantitative strand',
          4 => 'Qualitative strand',
          5 => 'Joint display and integrated findings',
          6 => 'Discussion and conclusions',
        ),
        'stages' => 
        array (
          0 => 'Justify mixing and sequence',
          1 => 'Plan both strands and integration',
          2 => 'Review ethics and instruments',
          3 => 'Collect both evidence streams',
          4 => 'Analyse strands and construct joint displays',
          5 => 'Interpret convergence and divergence',
        ),
        'instruments' => 
        array (
          0 => 'Questionnaire',
          1 => 'Interview guide',
          2 => 'Integration matrix',
          3 => 'Disciplinary integration agreement',
        ),
        'analysis' => 
        array (
          0 => 'Descriptive statistics',
          1 => 'Qualitative coding',
          2 => 'Joint displays and meta-inferences',
          3 => 'Integrated interpretation across disciplines',
        ),
        'requirements' => 
        array (
          0 => 'Timing, priority and sampling relationship of strands',
          1 => 'Explicit integration points',
          2 => 'Explain contradictory findings',
          3 => 'Make disciplinary assumptions, integration and shared quality criteria explicit.',
        ),
        'frameworks' => 
        array (
          0 => 'srqr',
        ),
        'label' => 'Interdisciplinary research',
        'family' => 'mixed',
        'decision' => 'Make disciplinary assumptions, integration and shared quality criteria explicit.',
      ),
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => '127.0.0.1',
      3 => '127.0.0.1:8000',
      4 => '127.0.0.1:8001',
      5 => '::1',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'authenticate_session' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
      'encrypt_cookies' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
      'validate_csrf_token' => 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
    ),
  ),
  'scholarly' => 
  array (
    'search' => 
    array (
      'timeout' => 5,
      'connect_timeout' => 2,
      'cache_seconds' => 300,
    ),
    'orcid' => 
    array (
      'base_url' => 'https://pub.orcid.org/v3.0',
    ),
    'providers' => 
    array (
      'openalex' => 
      array (
        'base_url' => 'https://api.openalex.org',
        'mailto' => '',
      ),
      'crossref' => 
      array (
        'base_url' => 'https://api.crossref.org',
        'mailto' => '',
      ),
      'semantic_scholar' => 
      array (
        'base_url' => 'https://api.semanticscholar.org/graph/v1',
        'api_key' => '',
      ),
      'core' => 
      array (
        'base_url' => 'https://api.core.ac.uk/v3',
        'api_key' => '',
      ),
      'doaj' => 
      array (
        'base_url' => 'https://doaj.org/api',
      ),
      'pubmed' => 
      array (
        'base_url' => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils',
      ),
      'arxiv' => 
      array (
        'base_url' => 'https://export.arxiv.org',
      ),
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'token' => '',
    ),
    'resend' => 
    array (
      'key' => '',
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => '',
        'channel' => '',
      ),
    ),
    'webpush' => 
    array (
      'vapid_public_key' => '',
      'vapid_private_key' => '',
      'vapid_subject' => '',
      'timeout' => 8,
      'connect_timeout' => 3,
      'allowed_hosts' => 
      array (
        0 => 'fcm.googleapis.com',
        1 => 'android.googleapis.com',
        2 => '*.push.services.mozilla.com',
        3 => '*.push.apple.com',
        4 => '*.notify.windows.com',
      ),
    ),
    'sms' => 
    array (
      'base_url' => 'https://api.sms-provider.com/v1',
      'sender_id' => 'AcadSyntra',
      'api_key' => '',
    ),
  ),
  'session' => 
  array (
    'driver' => 'database',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => '/Users/user/Desktop/acadflow/acadflow-web/storage/framework/sessions',
    'connection' => '',
    'table' => 'sessions',
    'store' => '',
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'acadflow_session',
    'path' => '/',
    'domain' => NULL,
    'secure' => false,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'submissions' => 
  array (
    'billing' => 
    array (
      'grace_period_days' => '7',
      'require_payment_before_submission' => true,
    ),
    'uploads' => 
    array (
      'default_max_file_size_mb' => 50,
      'default_max_file_count' => 10,
      'default_max_total_size_mb' => 500,
      'allowed_mime_types' => 
      array (
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'application/zip' => 'zip',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
      ),
    ),
    'late_submissions' => 
    array (
      'default_allow_late' => true,
      'default_penalty_percent' => 10.0,
      'max_penalty_percent' => 50.0,
    ),
    'tasks' => 
    array (
      'default_submission_format' => 'file',
      'default_max_resubmissions' => '',
      'default_group_size' => 
      array (
        'min' => 1,
        'max' => 6,
      ),
    ),
    'deadlines' => 
    array (
      'show_deadline_warning_days' => 3,
      'show_deadline_urgent_hours' => 24,
    ),
    'notifications' => 
    array (
      'notify_deadline_approaching' => true,
      'notify_submission_received' => true,
      'notify_grade_posted' => true,
      'notify_correction_requested' => true,
    ),
    'storage' => 
    array (
      'disk' => 'local',
      'path' => 'submissions',
    ),
  ),
  'dompdf' => 
  array (
    'show_warnings' => false,
    'public_path' => NULL,
    'convert_entities' => true,
    'options' => 
    array (
      'font_dir' => '/Users/user/Desktop/acadflow/acadflow-web/storage/fonts',
      'font_cache' => '/Users/user/Desktop/acadflow/acadflow-web/storage/fonts',
      'temp_dir' => '/var/folders/sg/xsnpnpcs45zcs1wrymkz20nc0000gn/T',
      'chroot' => '/Users/user/Desktop/acadflow/acadflow-web',
      'allowed_protocols' => 
      array (
        'data://' => 
        array (
          'rules' => 
          array (
          ),
        ),
        'file://' => 
        array (
          'rules' => 
          array (
          ),
        ),
        'http://' => 
        array (
          'rules' => 
          array (
          ),
        ),
        'https://' => 
        array (
          'rules' => 
          array (
          ),
        ),
      ),
      'artifactPathValidation' => NULL,
      'log_output_file' => NULL,
      'enable_font_subsetting' => false,
      'pdf_backend' => 'CPDF',
      'default_media_type' => 'screen',
      'default_paper_size' => 'a4',
      'default_paper_orientation' => 'portrait',
      'default_font' => 'serif',
      'dpi' => 96,
      'enable_php' => false,
      'enable_javascript' => true,
      'enable_remote' => false,
      'allowed_remote_hosts' => NULL,
      'font_height_ratio' => 1.1,
      'enable_html5_parser' => true,
    ),
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
    'trust_project' => 'always',
  ),
);
