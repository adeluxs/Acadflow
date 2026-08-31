<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\AiAnalytics;
use App\Ai\AiManager;
use App\Ai\AiProviderRegistry;
use App\Ai\AiRouter;
use App\Ai\Providers\OpenRouterProvider;
use App\Ai\Features\CitationAssistantModule;
use App\Ai\Features\PlagiarismModule;
use App\Ai\Features\SubmissionValidatorModule;
use App\Ai\Features\WritingAssistantModule;
use App\Enums\AiMode;
use App\Enums\AiProviderName;
use App\Enums\Permission;
use App\Events\SubmissionAiAnalysisRequested;
use App\Models\AiAnalysis;
use App\Models\AiPromptVersion;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Submission;
use App\Services\Ai\AcademicAssistantService;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\SettingService;
use App\Support\Errors\UserFacingError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central AcadFlow AI controller.
 *
 * Configuration and provider routing are intentionally managed in this single
 * AI Settings area; normal System Settings no longer exposes AI runtime knobs.
 */
class AiController extends Controller
{
    public function __construct(
        protected AiManager $manager,
        protected AiRouter $router,
        protected AiAnalytics $analytics,
        protected SubmissionValidatorModule $validator,
        protected PlagiarismModule $plagiarism,
        protected WritingAssistantModule $writing,
        protected CitationAssistantModule $citation,
        protected AcademicAssistantService $assistant,
        protected AiRuntimeConfigService $runtime,
        protected AiProviderRegistry $providerRegistry,
    ) {}

    protected function authorizeAi(Permission $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) abort(403);
    }

    public function assistant(Request $request)
    {
        $user = $request->user();
        $courses = collect();

        if ($user->isStudent()) {
            $courses = Course::query()
                ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'enrolled'))
                ->orderBy('code')->get(['id', 'uuid', 'code', 'name']);
        } elseif ($user->isLecturer()) {
            $courses = Course::query()
                ->whereHas('lecturerAssignments', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('code')->get(['id', 'uuid', 'code', 'name']);
        } elseif ($user->isDepartmentAdmin()) {
            $courses = Course::query()->where('department_id', $user->department_id)
                ->orderBy('code')->get(['id', 'uuid', 'code', 'name']);
        } elseif ($user->isUniversityAdmin()) {
            $courses = Course::query()
                ->whereHas('department.faculty', fn ($query) => $query->where('university_id', $user->university_id))
                ->orderBy('code')->get(['id', 'uuid', 'code', 'name']);
        }

        $selectedTool = in_array($request->query('tool'), ['ask', 'writing', 'citation'], true)
            ? (string) $request->query('tool')
            : 'ask';

        // Resolve the same canonical feature keys used by the eventual runtime
        // requests. A lecturer therefore sees lecturer_assistant routing for
        // Ask/Explain, while Writing and Citation expose their own configured
        // routes. The UI receives all three snapshots so its provider/model
        // badges stay consistent when the user changes tools before submitting.
        $toolRoutes = [];
        foreach (['ask', 'writing', 'citation'] as $tool) {
            $feature = $this->assistant->featureFor($user, $tool);
            $resolved = $this->router->route($feature, $user->university_id);
            $externalAiEnabled = in_array($resolved['mode'], [AiMode::PROVIDER->value, AiMode::HYBRID->value], true);

            $toolRoutes[$tool] = [
                'feature' => $feature,
                'mode' => $resolved['mode'],
                'provider' => $externalAiEnabled ? $resolved['resolved_provider'] : 'Rule engine',
                'model' => $externalAiEnabled ? ($resolved['resolved_model'] ?: 'Provider default') : 'rule-engine',
                'feature_enabled' => (bool) $resolved['feature_enabled'],
            ];
        }

        $route = $toolRoutes[$selectedTool];

        return view('ai.assistant', [
            'courses' => $courses,
            'assistantFeature' => $route['feature'],
            'mode' => $route['mode'],
            'provider' => $route['provider'],
            'model' => $route['model'],
            'externalAiEnabled' => in_array($route['mode'], [AiMode::PROVIDER->value, AiMode::HYBRID->value], true),
            'selectedTool' => $selectedTool,
            'toolRoutes' => $toolRoutes,
        ]);
    }

    public function askAssistant(Request $request)
    {
        $data = $request->validate([
            'tool' => ['required', 'in:ask,writing,citation'],
            'message' => ['required', 'string', 'max:50000'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'style' => ['nullable', 'in:apa,mla,chicago,harvard,ieee,vancouver'],
        ]);

        $user = $request->user();
        $courseId = isset($data['course_id']) ? (int) $data['course_id'] : null;
        if ($courseId && ! $user->canAccessCourse(Course::with('department.faculty')->findOrFail($courseId))) {
            abort(403, 'You do not have access to the selected course.');
        }

        if ($data['tool'] === 'writing') {
            if (! $this->writing->isEnabled($user->university_id)) {
                return response()->json(['success' => false, 'answer' => 'The writing assistant is disabled for your institution.']);
            }
            return response()->json($this->assistantModulePayload(
                $this->writing->analyze($data['message'], 'general', $user)->toArray(), 'Writing review'
            ));
        }

        if ($data['tool'] === 'citation') {
            if (! $this->citation->isEnabled($user->university_id)) {
                return response()->json(['success' => false, 'answer' => 'The citation assistant is disabled for your institution.']);
            }
            return response()->json($this->assistantModulePayload(
                $this->citation->analyze($data['message'], $data['style'] ?? 'apa', $user)->toArray(), 'Citation review'
            ));
        }

        return response()->json($this->assistant->ask($user, $data['message'], $courseId));
    }

    private function assistantModulePayload(array $payload, string $fallbackTitle): array
    {
        $answer = $payload['data']['answer'] ?? null;
        $findings = $payload['findings'] ?? $payload['issues'] ?? [];
        if ((! is_string($answer) || trim($answer) === '') && is_array($findings) && $findings !== []) {
            $answer = collect($findings)->map(function ($finding): string {
                if (! is_array($finding)) return (string) $finding;
                $message = $finding['message'] ?? $finding['issue'] ?? $finding['title'] ?? 'Suggestion';
                return trim((string) $message).(! empty($finding['suggestion']) ? ' — '.trim((string) $finding['suggestion']) : '');
            })->implode("\n");
        }
        if (! is_string($answer) || trim($answer) === '') $answer = $payload['summary'] ?? null;
        $success = (bool) ($payload['success'] ?? true);
        $failure = $success ? null : UserFacingError::fromAiCode($payload['error_code'] ?? null, is_string($answer) ? $answer : null);
        if ($failure) $answer = $failure['message'];

        return [
            'success' => $success,
            'answer' => (is_string($answer) && trim($answer) !== '') ? $answer : $fallbackTitle.' completed. No additional suggestions were returned.',
            'provider' => $payload['provider'] ?? null,
            'model' => $payload['model'] ?? null,
            'source' => $payload['source'] ?? null,
            'fallback_used' => $payload['fallback_used'] ?? false,
            'confidence' => $payload['confidence'] ?? null,
            'sources' => [],
            'request_id' => $payload['request_id'] ?? null,
            'error_code' => $payload['error_code'] ?? null,
            'retryable' => (bool) ($failure['retryable'] ?? false),
        ];
    }

    public function settings()
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);
        $user = auth()->user();
        $scope = $user->isSuperAdmin() ? null : $user->university_id;
        $providerDefinitions = $this->providerRegistry->definitions($scope);

        foreach ($providerDefinitions as $name => &$definition) {
            $definition['health'] = Cache::get('ai:provider-health:global:'.$name, [
                'status' => $definition['configured'] ? 'not_checked' : ($definition['enabled'] ? 'configuration_incomplete' : 'disabled'),
                'message' => $definition['configured'] ? 'Use Test Connection to verify this provider.' : 'Provider is not ready for requests.',
            ]);
            $cfg = $name === AiProviderName::RULE_BASED->value ? [] : $this->runtime->providerConfig($name, $scope);
            unset($cfg['api_key']);
            $definition['base_url'] = $cfg['base_url'] ?? $cfg['endpoint'] ?? '';
            $definition['temperature'] = $cfg['temperature'] ?? $this->runtime->globalTemperature($scope);
            $definition['credential_configured'] = $definition['configured'] || $name === AiProviderName::OLLAMA->value;
        }
        unset($definition);

        $featureRouting = [];
        foreach ((array) config('ai.features', []) as $feature) {
            $featureRouting[$feature] = [
                'enabled' => $this->runtime->featureEnabled($feature, $scope),
                'provider' => $this->runtime->featureProvider($feature, $scope),
                'model' => $this->runtime->featureModel($feature, $scope),
                'rule_fallback' => $this->runtime->featureRuleFallbackEnabled($feature, $scope),
                'resolved' => $this->router->route($feature, $scope),
            ];
        }

        return view('ai.settings', [
            'modes' => AiMode::cases(),
            'providers' => AiProviderName::cases(),
            'providerDefinitions' => $providerDefinitions,
            'mode' => $this->router->mode($scope)->value,
            'defaultProvider' => $this->runtime->defaultProvider($scope),
            'defaultModel' => $this->runtime->defaultModel($scope),
            'fallbackProvider' => $this->runtime->fallbackProvider($scope) ?? 'none',
            'fallbackModel' => $this->runtime->fallbackModel($scope) ?? '',
            'secondaryFallbackProvider' => $this->runtime->secondaryFallbackProvider($scope) ?? 'none',
            'secondaryFallbackModel' => $this->runtime->secondaryFallbackModel($scope) ?? '',
            'settings' => $this->aiSettings($scope),
            'rulePacks' => $this->rulePackSettings($scope),
            'features' => config('ai.features', []),
            'featureRouting' => $featureRouting,
            'isPlatformAdmin' => $user->isSuperAdmin(),
            'promptVersions' => AiPromptVersion::query()->where(function ($query) use ($user) {
                if ($user->isSuperAdmin()) $query->whereNotNull('id');
                else $query->whereNull('university_id')->orWhere('university_id', $user->university_id);
            })->orderBy('feature')->orderByDesc('version')->get(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);
        $user = auth()->user();
        $scope = $user->isSuperAdmin() ? null : $user->university_id;
        $actorId = $user->id;
        $externalProviders = array_values(array_filter(AiProviderName::values(), fn ($p) => $p !== AiProviderName::RULE_BASED->value));
        $providerChoices = implode(',', $externalProviders);

        $data = $request->validate([
            'ai_mode' => ['required', 'in:'.implode(',', AiMode::values())],
            'ai_default_provider' => ['required', 'in:'.implode(',', AiProviderName::values())],
            'ai_default_model' => ['nullable', 'string', 'max:150'],
            'ai_fallback_provider' => ['required', 'in:none,'.$providerChoices],
            'ai_fallback_model' => ['nullable', 'string', 'max:150'],
            'ai_secondary_fallback_provider' => ['required', 'in:none,'.$providerChoices],
            'ai_secondary_fallback_model' => ['nullable', 'string', 'max:150'],
            'ai_similarity_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'ai_request_timeout' => ['required', 'integer', 'min:1', 'max:300'],
            'ai_retry_count' => ['required', 'integer', 'min:0', 'max:5'],
            'ai_retry_delay_ms' => ['required', 'integer', 'min:0', 'max:10000'],
            'ai_temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'ai_max_tokens' => ['required', 'integer', 'min:1', 'max:128000'],
            'ai_context_limit' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'ai_daily_request_limit' => ['required', 'integer', 'min:0'],
            'ai_monthly_request_limit' => ['required', 'integer', 'min:0'],
            'ai_max_cost' => ['required', 'numeric', 'min:0'],
            'ai_rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:1000'],
            'ai_cache_ttl' => ['required', 'integer', 'min:60', 'max:2592000'],
            'ai_editor_suggestion_min_chars' => ['required', 'integer', 'min:10', 'max:5000'],
            'ai_editor_suggestion_delay_ms' => ['required', 'integer', 'min:200', 'max:10000'],
            'ai_min_word_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'ai_max_word_count' => ['required', 'integer', 'min:1', 'max:2000000'],
            'ai_institution_required_sections' => ['nullable', 'string', 'max:2000'],
            'ai_global_system_prompt' => ['required', 'string', 'max:10000'],
            'ai_grounded_min_question_chars' => ['required', 'integer', 'min:2', 'max:50'],
            'ai_grounded_gibberish_threshold' => ['required', 'numeric', 'min:0.2', 'max:1'],
            'ai_grounded_relevance_threshold' => ['required', 'numeric', 'min:0.01', 'max:0.95'],
            'ai_grounded_lexical_floor' => ['required', 'numeric', 'min:0', 'max:0.95'],
            'ai_grounded_citation_coverage_min' => ['required', 'numeric', 'min:0.2', 'max:1'],
            'ai_grounded_support_threshold' => ['required', 'numeric', 'min:0.01', 'max:0.95'],
            'ai_grounded_support_coverage_min' => ['required', 'numeric', 'min:0.2', 'max:1'],
        ]);

        if (in_array($data['ai_mode'], [AiMode::PROVIDER->value, AiMode::HYBRID->value], true)
            && $data['ai_default_provider'] === AiProviderName::RULE_BASED->value) {
            throw ValidationException::withMessages(['ai_default_provider' => 'Provider AI and Hybrid modes require an external Default Provider. Rule-Based Only has its own AI Mode.']);
        }
        if ($data['ai_fallback_provider'] !== 'none' && $data['ai_fallback_provider'] === $data['ai_default_provider']) {
            throw ValidationException::withMessages(['ai_fallback_provider' => 'Fallback Provider must be different from the Default Provider.']);
        }
        if ($data['ai_secondary_fallback_provider'] !== 'none'
            && in_array($data['ai_secondary_fallback_provider'], [$data['ai_default_provider'], $data['ai_fallback_provider']], true)) {
            throw ValidationException::withMessages(['ai_secondary_fallback_provider' => 'Secondary Fallback must be different from both the Default and first Fallback providers.']);
        }

        if ((int) $data['ai_max_word_count'] < (int) $data['ai_min_word_count']) {
            throw ValidationException::withMessages(['ai_max_word_count' => 'Maximum word count must be greater than or equal to the minimum word count.']);
        }

        // Validate the configuration that is about to be written before the
        // transaction begins. This prevents an invalid provider/model selection
        // from being persisted and only then rejected after commit.
        $this->validateRequestedConfiguration($request, $data, $scope, $user, $externalProviders);

        $before = [
            'mode' => $this->runtime->mode($scope)->value,
            'default_provider' => $this->runtime->defaultProvider($scope),
            'default_model' => $this->runtime->defaultModel($scope),
            'fallback_provider' => $this->runtime->fallbackProvider($scope),
            'fallback_model' => $this->runtime->fallbackModel($scope),
        ];

        DB::transaction(function () use ($request, $data, $scope, $actorId, $user, $externalProviders): void {
            // Provider credentials/configuration are platform-owned. Institution
            // admins can inherit them and choose routing, but cannot read/replace secrets.
            if ($user->isSuperAdmin()) {
                foreach ($externalProviders as $provider) {
                    $model = trim((string) $request->input("provider_{$provider}_model", ''));
                    $models = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $request->input("provider_{$provider}_models", ''))))));
                    if ($model !== '' && ! in_array($model, $models, true)) $models[] = $model;
                    $baseUrl = trim((string) $request->input("provider_{$provider}_base_url", ''));
                    if (strlen($baseUrl) > 1000) throw ValidationException::withMessages(["provider_{$provider}_base_url" => 'Provider URL is too long.']);

                    SettingService::set("ai_provider_{$provider}_enabled", $request->boolean("provider_{$provider}_enabled"), 'boolean', null, $actorId);
                    SettingService::set("ai_provider_{$provider}_model", $model, 'string', null, $actorId);
                    SettingService::set("ai_provider_{$provider}_models", $models, 'json', null, $actorId);
                    SettingService::set("ai_provider_{$provider}_temperature", (float) $request->input("provider_{$provider}_temperature", $data['ai_temperature']), 'decimal', null, $actorId);
                    SettingService::set("ai_provider_{$provider}_base_url", $baseUrl, 'string', null, $actorId);

                    $newSecret = trim((string) $request->input("provider_{$provider}_api_key", ''));
                    if ($newSecret !== '') {
                        SettingService::set("ai_provider_{$provider}_api_key", $this->runtime->encryptProviderSecret($newSecret), 'string', null, $actorId);
                    }
                }
            }

            $types = [
                'ai_mode' => 'string', 'ai_default_provider' => 'string', 'ai_default_model' => 'string',
                'ai_fallback_provider' => 'string', 'ai_fallback_model' => 'string',
                'ai_secondary_fallback_provider' => 'string', 'ai_secondary_fallback_model' => 'string',
                'ai_similarity_threshold' => 'integer', 'ai_request_timeout' => 'integer', 'ai_retry_count' => 'integer',
                'ai_retry_delay_ms' => 'integer', 'ai_temperature' => 'decimal', 'ai_max_tokens' => 'integer',
                'ai_context_limit' => 'integer', 'ai_daily_request_limit' => 'integer', 'ai_monthly_request_limit' => 'integer',
                'ai_max_cost' => 'decimal', 'ai_rate_limit_per_minute' => 'integer', 'ai_cache_ttl' => 'integer',
                'ai_editor_suggestion_min_chars' => 'integer',
                'ai_editor_suggestion_delay_ms' => 'integer', 'ai_min_word_count' => 'integer', 'ai_max_word_count' => 'integer',
                'ai_institution_required_sections' => 'string', 'ai_global_system_prompt' => 'string',
                'ai_grounded_min_question_chars' => 'integer', 'ai_grounded_gibberish_threshold' => 'decimal',
                'ai_grounded_relevance_threshold' => 'decimal', 'ai_grounded_lexical_floor' => 'decimal',
                'ai_grounded_citation_coverage_min' => 'decimal', 'ai_grounded_support_threshold' => 'decimal',
                'ai_grounded_support_coverage_min' => 'decimal',
            ];
            foreach ($types as $key => $type) SettingService::set($key, $data[$key] ?? '', $type, $scope, $actorId);


            foreach (['ai_automatic_failover','ai_fast_failover','ai_provider_health_enabled','ai_enable_cache','ai_enable_logging','ai_grounding_enabled','ai_hybrid_escalate_when_clean','ai_grounded_pattern_learning_enabled','ai_editor_suggestions_enabled'] as $toggle) {
                SettingService::set($toggle, $request->boolean($toggle), 'boolean', $scope, $actorId);
            }
            // No live web-search adapter exists in this source. Keep the runtime
            // capability explicitly disabled instead of presenting fake web AI.
            SettingService::set('ai_web_research_enabled', false, 'boolean', $scope, $actorId);

            foreach ((array) config('ai.features', []) as $feature) {
                $provider = (string) $request->input("feature_provider.{$feature}", 'global');
                $model = trim((string) $request->input("feature_model.{$feature}", 'global')) ?: 'global';
                if ($provider !== 'global' && ! in_array($provider, AiProviderName::values(), true)) {
                    throw ValidationException::withMessages(["feature_provider.{$feature}" => 'Unsupported feature provider.']);
                }
                if ($provider === AiProviderName::RULE_BASED->value && in_array($data['ai_mode'], [AiMode::PROVIDER->value, AiMode::HYBRID->value], true)) {
                    throw ValidationException::withMessages(["feature_provider.{$feature}" => 'Choose Use Global Default or an external provider. Rule-Based behavior is selected through AI Mode / Hybrid fallback.']);
                }
                if ($provider !== 'global' && $model !== 'global' && ! in_array($model, $this->runtime->providerModels($provider, $scope), true)) {
                    throw ValidationException::withMessages(["feature_model.{$feature}" => 'The selected model is not configured for the selected provider.']);
                }
                SettingService::set('ai_feature_'.$feature, $request->boolean("feature_enabled.{$feature}"), 'boolean', $scope, $actorId);
                SettingService::set('ai_feature_'.$feature.'_provider', $provider, 'string', $scope, $actorId);
                SettingService::set('ai_feature_'.$feature.'_model', $model, 'string', $scope, $actorId);
                SettingService::set('ai_feature_'.$feature.'_rule_fallback', $request->boolean("feature_rule_fallback.{$feature}"), 'boolean', $scope, $actorId);
            }

            foreach ($this->rulePackKeys() as $pack) {
                SettingService::set('ai_rulepack_'.$pack, $request->boolean('ai_rulepack_'.$pack), 'boolean', $scope, $actorId);
            }

            $layoutFonts = $request->input('ai_layout_required_fonts', []);
            if (! is_array($layoutFonts)) $layoutFonts = array_filter(array_map('trim', explode(',', (string) $layoutFonts)));
            SettingService::set('ai_layout_required_fonts', array_values($layoutFonts), 'json', $scope, $actorId);
            SettingService::set('ai_layout_page_size', $request->input('ai_layout_page_size', 'A4'), 'string', $scope, $actorId);
            SettingService::set('ai_layout_min_margin_inches', $request->input('ai_layout_min_margin_inches', 1.0), 'decimal', $scope, $actorId);
            SettingService::set('ai_layout_line_spacing', $request->input('ai_layout_line_spacing', '1.5'), 'string', $scope, $actorId);
            SettingService::set('ai_layout_min_font_size', $request->input('ai_layout_min_font_size', 10), 'integer', $scope, $actorId);
            SettingService::set('ai_layout_require_page_numbering', $request->boolean('ai_layout_require_page_numbering'), 'boolean', $scope, $actorId);
            SettingService::set('ai_layout_require_branding', $request->boolean('ai_layout_require_branding'), 'boolean', $scope, $actorId);
        });

        $this->runtime->invalidate();
        $this->manager->invalidateAll();


        $after = [
            'mode' => $this->runtime->mode($scope)->value,
            'default_provider' => $this->runtime->defaultProvider($scope),
            'default_model' => $this->runtime->defaultModel($scope),
            'fallback_provider' => $this->runtime->fallbackProvider($scope),
            'fallback_model' => $this->runtime->fallbackModel($scope),
        ];
        AuditLog::log('ai_settings_updated', $actorId, 'ai_settings', $scope, $before, $after, $request->ip(), $request->userAgent(), 'runtime_routing');

        return redirect()->route('ai.settings')->with('success', 'AI settings updated. New provider/model routing is effective immediately.');
    }

    public function testProvider(Request $request, string $provider)
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);
        abort_unless(in_array($provider, AiProviderName::values(), true), 404);
        abort_if($provider === AiProviderName::RULE_BASED->value, 422, 'The Rule Engine does not require an external connection test.');

        $user = $request->user();
        $scope = $user->isSuperAdmin() ? null : $user->university_id;

        if (! $user->isSuperAdmin()) {
            $result = $this->providerRegistry->health($provider, $scope, true);
            return back()->with('provider_test', $result);
        }

        // Test the values currently entered in the form without saving them. A
        // blank secret means "keep/test the existing encrypted secret".
        $config = $this->runtime->providerConfig($provider, null);
        $model = trim((string) $request->input("provider_{$provider}_model", $config['model'] ?? ''));
        $baseUrl = trim((string) $request->input("provider_{$provider}_base_url", ''));
        $secret = trim((string) $request->input("provider_{$provider}_api_key", ''));
        $config['model'] = $model;
        $config['enabled'] = true;
        if ($secret !== '') $config['api_key'] = $secret;
        if ($baseUrl !== '') {
            if (in_array($provider, [AiProviderName::AZURE_OPENAI->value, AiProviderName::OLLAMA->value], true)) $config['endpoint'] = $baseUrl;
            else $config['base_url'] = $baseUrl;
        }
        $config['temperature'] = (float) $request->input("provider_{$provider}_temperature", $config['temperature'] ?? 0.2);

        $result = $this->providerRegistry->healthWithConfig($provider, $config);
        return back()->withInput()->with('provider_test', $result);
    }

    public function discoverProviderModels(Request $request, string $provider)
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($provider === AiProviderName::OPENROUTER->value, 404);

        $config = $this->runtime->providerConfig($provider, null);
        $baseUrl = trim((string) $request->input("provider_{$provider}_base_url", ''));
        $secret = trim((string) $request->input("provider_{$provider}_api_key", ''));
        if ($baseUrl !== '') $config['base_url'] = $baseUrl;
        if ($secret !== '') $config['api_key'] = $secret;
        $config['enabled'] = true;

        $adapter = $this->providerRegistry->makeWithConfig($provider, $config);
        abort_unless($adapter instanceof OpenRouterProvider, 500, 'OpenRouter adapter is unavailable.');

        try {
            $catalog = $adapter->discoverModels();
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->withErrors(['provider_openrouter_models' => 'OpenRouter model discovery failed. Verify the API key, endpoint, and network connection.']);
        }

        if ($catalog === []) {
            return back()->withInput()->withErrors(['provider_openrouter_models' => 'OpenRouter returned no models. Verify the API key and endpoint.']);
        }

        $models = array_values(array_unique(array_map(fn (array $model): string => $model['id'], $catalog)));
        SettingService::set('ai_provider_openrouter_models', $models, 'json', null, $request->user()->id);
        SettingService::set('ai_provider_openrouter_model_catalog', $catalog, 'json', null, $request->user()->id);
        $this->runtime->invalidate();

        return redirect()->route('ai.settings')->with('success', 'OpenRouter model catalog refreshed: '.count($models).' models discovered.');
    }

    public function diagnostics()
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);
        $user = auth()->user();
        $scope = $user->isSuperAdmin() ? null : $user->university_id;
        $providers = $this->providerRegistry->definitions($scope);
        foreach ($providers as $name => &$definition) {
            $definition['health'] = Cache::get('ai:provider-health:global:'.$name, ['status' => 'not_checked', 'message' => 'Not checked recently.']);
        }
        unset($definition);

        $routes = [];
        foreach ((array) config('ai.features', []) as $feature) $routes[$feature] = $this->router->route($feature, $scope);

        $recent = AiUsageLog::query()
            ->when($scope, fn ($q) => $q->where('university_id', $scope))
            ->latest()->limit(25)->get();

        return view('ai.diagnostics', [
            'mode' => $this->runtime->mode($scope)->value,
            'defaultProvider' => $this->runtime->defaultProvider($scope),
            'defaultModel' => $this->runtime->defaultModel($scope),
            'fallbackProvider' => $this->runtime->fallbackProvider($scope),
            'fallbackModel' => $this->runtime->fallbackModel($scope),
            'secondaryFallbackProvider' => $this->runtime->secondaryFallbackProvider($scope),
            'providers' => $providers,
            'routes' => $routes,
            'recent' => $recent,
            'configGeneration' => (int) Cache::get('ai:runtime-config-generation', 1),
            'queueConnection' => config('ai.queue_connection') ?: config('queue.default'),
        ]);
    }

    public function submissionAnalysis(Submission $submission)
    {
        $this->authorize('view', $submission);
        $analyses = AiAnalysis::where('submission_id', $submission->id)->orderByDesc('created_at')->get();
        return view('ai.submission-analysis', compact('submission', 'analyses'));
    }

    public function reanalyze(Request $request, Submission $submission)
    {
        $this->authorize('view', $submission);
        event(new SubmissionAiAnalysisRequested($submission, $request->user(), (array) $request->input('features', ['submission_validator', 'plagiarism'])));
        return back()->with('success', 'AI analysis queued. Refresh shortly to see results.');
    }

    public function lecturerLayoutPreferences()
    {
        $user = auth()->user();
        if (! $user || ! $user->isLecturer()) abort(403);
        $prefs = \App\Models\LecturerLayoutPreference::firstOrCreate(['user_id' => $user->id], []);
        return view('ai.lecturer-layout-preferences', [
            'prefs' => $prefs,
            'institutionDefaults' => config('ai.layout_requirements', []),
            'pageSizes' => ['A4', 'Letter', 'Legal', 'A3', 'A5'],
            'lineSpacings' => ['1.0', '1.15', '1.5', '2.0'],
        ]);
    }

    public function saveLecturerLayoutPreferences(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->isLecturer()) abort(403);
        $validated = $request->validate([
            'required_fonts' => ['nullable', 'array'], 'required_fonts.*' => ['nullable', 'string', 'max:100'],
            'page_size' => ['nullable', 'string', 'max:20'], 'min_margin_inches' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'line_spacing' => ['nullable', 'string', 'max:10'], 'min_font_size_pt' => ['nullable', 'integer', 'min:6', 'max:72'],
            'require_page_numbering' => ['boolean'], 'require_institution_branding' => ['boolean'],
        ]);
        $prefs = \App\Models\LecturerLayoutPreference::firstOrCreate(['user_id' => $user->id]);
        $prefs->fill($validated)->save();
        return redirect()->route('ai.lecturer.layout.preferences')->with('success', 'Layout preferences saved.');
    }

    public function analytics(Request $request)
    {
        $this->authorizeAi(Permission::VIEW_AI_ANALYTICS);
        $universityId = $request->user()->university_id;
        $departmentId = $request->user()->isDepartmentAdmin() ? $request->user()->department_id : null;
        $summary = $this->analytics->summary($universityId, $departmentId);
        return view('ai.analytics', compact('summary'));
    }

    public function writingAssistant(Request $request)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:50000'], 'type' => ['nullable', 'string']]);
        if (! $this->writing->isEnabled($request->user()?->university_id)) return response()->json(['enabled' => false]);
        return response()->json($this->writing->analyze($data['text'], $data['type'] ?? null, $request->user())->toArray());
    }

    public function citationAssistant(Request $request)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:50000'], 'style' => ['nullable', 'in:apa,mla,chicago,harvard,ieee,vancouver']]);
        if (! $this->citation->isEnabled($request->user()?->university_id)) return response()->json(['enabled' => false]);
        return response()->json($this->citation->analyze($data['text'], $data['style'] ?? 'apa', $request->user())->toArray());
    }

    protected function aiSettings(?int $universityId = null): array
    {
        $layout = $this->runtime->layoutRequirements($universityId);
        $fonts = $layout['required_fonts'] ?? [];
        return [
            'ai_automatic_failover' => $this->runtime->automaticFailover($universityId),
            'ai_fast_failover' => $this->runtime->fastFailover($universityId),
            'ai_provider_health_enabled' => $this->runtime->providerHealthChecking($universityId),
            'ai_enable_cache' => $this->runtime->cacheEnabled($universityId),
            'ai_enable_logging' => $this->runtime->loggingEnabled($universityId),
            'ai_grounding_enabled' => $this->runtime->groundingEnabled($universityId),
            'ai_web_research_enabled' => false,
            'ai_global_system_prompt' => $this->runtime->globalSystemPrompt($universityId),
            'ai_hybrid_escalate_when_clean' => $this->runtime->hybridEscalateWhenClean($universityId),
            'ai_similarity_threshold' => $this->runtime->similarityThreshold($universityId),
            'ai_request_timeout' => $this->runtime->requestTimeout($universityId),
            'ai_retry_count' => $this->runtime->retryCount($universityId),
            'ai_retry_delay_ms' => $this->runtime->retryDelayMs($universityId),
            'ai_temperature' => $this->runtime->globalTemperature($universityId),
            'ai_max_tokens' => $this->runtime->maxTokens($universityId),
            'ai_context_limit' => $this->runtime->contextLimit($universityId),
            'ai_daily_request_limit' => $this->runtime->dailyRequestLimit($universityId),
            'ai_monthly_request_limit' => $this->runtime->monthlyRequestLimit($universityId),
            'ai_max_cost' => $this->runtime->maxMonthlyCost($universityId),
            'ai_rate_limit_per_minute' => $this->runtime->rateLimitPerMinute($universityId),
            'ai_cache_ttl' => $this->runtime->cacheTtl($universityId),
            'ai_editor_suggestions_enabled' => (bool) SettingService::get('ai_editor_suggestions_enabled', true, $universityId),
            'ai_editor_suggestion_min_chars' => (int) SettingService::get('ai_editor_suggestion_min_chars', 60, $universityId),
            'ai_editor_suggestion_delay_ms' => (int) SettingService::get('ai_editor_suggestion_delay_ms', 1600, $universityId),
            'ai_min_word_count' => (int) SettingService::get('ai_min_word_count', 200, $universityId),
            'ai_max_word_count' => (int) SettingService::get('ai_max_word_count', 20000, $universityId),
            'ai_institution_required_sections' => (string) SettingService::get('ai_institution_required_sections', '', $universityId),
            'ai_grounded_pattern_learning_enabled' => (bool) SettingService::get('ai_grounded_pattern_learning_enabled', true, $universityId),
            'ai_grounded_min_question_chars' => (int) SettingService::get('ai_grounded_min_question_chars', 3, $universityId),
            'ai_grounded_gibberish_threshold' => (float) SettingService::get('ai_grounded_gibberish_threshold', 0.60, $universityId),
            'ai_grounded_relevance_threshold' => (float) SettingService::get('ai_grounded_relevance_threshold', 0.18, $universityId),
            'ai_grounded_lexical_floor' => (float) SettingService::get('ai_grounded_lexical_floor', 0.20, $universityId),
            'ai_grounded_citation_coverage_min' => (float) SettingService::get('ai_grounded_citation_coverage_min', 0.85, $universityId),
            'ai_grounded_support_threshold' => (float) SettingService::get('ai_grounded_support_threshold', 0.20, $universityId),
            'ai_grounded_support_coverage_min' => (float) SettingService::get('ai_grounded_support_coverage_min', 0.70, $universityId),
            'ai_layout_required_fonts' => $fonts,
            'ai_layout_page_size' => $layout['page_size'] ?? 'A4',
            'ai_layout_min_margin_inches' => $layout['min_margin_inches'] ?? 1.0,
            'ai_layout_line_spacing' => $layout['line_spacing'] ?? '1.5',
            'ai_layout_min_font_size' => $layout['min_font_size_pt'] ?? 10,
            'ai_layout_require_page_numbering' => (bool) ($layout['require_page_numbering'] ?? false),
            'ai_layout_require_branding' => (bool) ($layout['require_institution_branding'] ?? false),
        ];
    }

    protected function rulePackKeys(): array
    {
        return ['academic', 'assignment', 'research', 'project', 'siwes', 'seminar', 'citation', 'formatting', 'template', 'knowledge_hub', 'layout', 'deadline', 'institution', 'discussion', 'plagiarism'];
    }

    protected function rulePackSettings(?int $universityId = null): array
    {
        $out = [];
        foreach ($this->rulePackKeys() as $pack) $out[$pack] = (bool) SettingService::get('ai_rulepack_'.$pack, true, $universityId);
        return $out;
    }

    /**
     * Validate the pending AI configuration before anything is written.
     *
     * Platform administrators may be replacing provider configuration in the
     * same request, so this method evaluates the submitted provider values plus
     * the currently stored secret/bootstrap fallback. Institution admins cannot
     * alter platform credentials and therefore must select an already configured
     * provider.
     *
     * @param array<string,mixed> $data
     * @param list<string> $externalProviders
     */
    private function validateRequestedConfiguration(Request $request, array $data, ?int $scope, $user, array $externalProviders): void
    {
        if (! in_array($data['ai_mode'], [AiMode::PROVIDER->value, AiMode::HYBRID->value], true)) {
            return;
        }

        $selected = [
            'ai_default_provider' => $data['ai_default_provider'],
        ];
        if (($data['ai_fallback_provider'] ?? 'none') !== 'none') {
            $selected['ai_fallback_provider'] = $data['ai_fallback_provider'];
        }
        if (($data['ai_secondary_fallback_provider'] ?? 'none') !== 'none') {
            $selected['ai_secondary_fallback_provider'] = $data['ai_secondary_fallback_provider'];
        }

        foreach ((array) config('ai.features', []) as $feature) {
            $provider = (string) $request->input("feature_provider.{$feature}", 'global');
            if ($provider !== 'global' && $provider !== AiProviderName::RULE_BASED->value) {
                $selected["feature_provider.{$feature}"] = $provider;
            }
        }

        foreach ($selected as $field => $provider) {
            if (! in_array($provider, $externalProviders, true)) {
                throw ValidationException::withMessages([$field => 'Select a supported external AI provider.']);
            }

            if (! $user->isSuperAdmin()) {
                if (! $this->runtime->providerEnabled($provider, $scope)) {
                    throw ValidationException::withMessages([$field => 'The selected provider is disabled by the platform administrator.']);
                }
                if (! $this->runtime->providerConfigurationComplete($provider, $scope)) {
                    throw ValidationException::withMessages([$field => 'The selected provider is not completely configured by the platform administrator.']);
                }
                continue;
            }

            if (! $request->boolean("provider_{$provider}_enabled")) {
                throw ValidationException::withMessages([$field => 'A provider selected for active routing must also be enabled.']);
            }

            $providerModel = trim((string) $request->input("provider_{$provider}_model", $this->runtime->providerModel($provider, null)));
            $models = array_values(array_unique(array_filter(array_map(
                'trim',
                explode(',', (string) $request->input("provider_{$provider}_models", implode(',', $this->runtime->providerModels($provider, null))))
            ))));
            if ($providerModel !== '' && ! in_array($providerModel, $models, true)) {
                $models[] = $providerModel;
            }

            $existing = $this->runtime->providerConfig($provider, null);
            $newSecret = trim((string) $request->input("provider_{$provider}_api_key", ''));
            $apiKey = $newSecret !== '' ? $newSecret : trim((string) ($existing['api_key'] ?? ''));
            $baseUrl = trim((string) $request->input("provider_{$provider}_base_url", ''));
            $bootstrap = (array) config('ai.providers.'.$provider, []);
            $endpoint = $baseUrl !== ''
                ? $baseUrl
                : trim((string) ($bootstrap['endpoint'] ?? $bootstrap['base_url'] ?? $existing['endpoint'] ?? $existing['base_url'] ?? ''));

            if ($providerModel === '') {
                throw ValidationException::withMessages(["provider_{$provider}_model" => 'Configure a model before using this provider.']);
            }

            $requiresKey = $provider !== AiProviderName::OLLAMA->value;
            if ($requiresKey && $apiKey === '') {
                throw ValidationException::withMessages(["provider_{$provider}_api_key" => 'This provider requires an API key. Existing secrets are preserved when this field is left blank.']);
            }
            if (in_array($provider, [AiProviderName::AZURE_OPENAI->value, AiProviderName::OLLAMA->value], true) && $endpoint === '') {
                throw ValidationException::withMessages(["provider_{$provider}_base_url" => 'This provider requires an endpoint/base URL.']);
            }

            $routingModel = match ($field) {
                'ai_default_provider' => trim((string) ($data['ai_default_model'] ?? '')) ?: $providerModel,
                'ai_fallback_provider' => trim((string) ($data['ai_fallback_model'] ?? '')) ?: $providerModel,
                'ai_secondary_fallback_provider' => trim((string) ($data['ai_secondary_fallback_model'] ?? '')) ?: $providerModel,
                default => str_starts_with($field, 'feature_provider.')
                    ? (trim((string) $request->input('feature_model.'.substr($field, strlen('feature_provider.')), 'global')) ?: 'global')
                    : $providerModel,
            };

            if ($routingModel !== 'global' && $routingModel !== '' && ! in_array($routingModel, $models, true)) {
                throw ValidationException::withMessages([$field => "The selected model '{$routingModel}' is not configured for {$provider}."]);
            }
        }
    }
}
