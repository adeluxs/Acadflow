<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\AiAnalytics;
use App\Ai\AiManager;
use App\Ai\AiRouter;
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
use App\Models\Submission;
use App\Models\Course;
use App\Services\Ai\AcademicAssistantService;
use App\Services\SettingService;
use Illuminate\Http\Request;

/**
 * AI Academic Assistant controller.
 *
 * Exposes: admin settings UI, analytics dashboard, on-demand re-analysis, and
 * the writing/citation assistant endpoints. All AI operations flow through the
 * centralized AiManager.
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
    ) {}

    /**
     * Permission guard consistent with the rest of the app (User::hasPermission),
     * so super_admin / university_admin / department_admin are all allowed.
     */
    protected function authorizeAi(Permission $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403);
        }
    }

    /**
     * Shared student/lecturer/member AI Academic Assistant workspace.
     */
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

        return view('ai.assistant', [
            'courses' => $courses,
            'mode' => $this->router->mode($user->university_id)->value,
            'provider' => $this->router->defaultProviderName($user->university_id),
            'externalAiEnabled' => (bool) SettingService::get('ai_enable_external_ai', config('ai.enable_external_ai', false), $user->university_id),
            'selectedTool' => in_array($request->query('tool'), ['ask', 'writing', 'citation'], true) ? $request->query('tool') : 'ask',
        ]);
    }

    /**
     * Process a user-facing assistant request through the existing AI modules.
     */
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
                return response()->json(['success' => false, 'answer' => 'The writing assistant is disabled for your institution.'], 200);
            }
            $response = $this->writing->analyze($data['message'], 'general', $user);
            return response()->json($this->assistantModulePayload($response->toArray(), 'Writing review'));
        }

        if ($data['tool'] === 'citation') {
            if (! $this->citation->isEnabled($user->university_id)) {
                return response()->json(['success' => false, 'answer' => 'The citation assistant is disabled for your institution.'], 200);
            }
            $response = $this->citation->analyze($data['message'], $data['style'] ?? 'apa', $user);
            return response()->json($this->assistantModulePayload($response->toArray(), 'Citation review'));
        }

        return response()->json($this->assistant->ask($user, $data['message'], $courseId));
    }

    /** @param array<string,mixed> $payload */
    private function assistantModulePayload(array $payload, string $fallbackTitle): array
    {
        $answer = $payload['data']['answer'] ?? null;
        $findings = $payload['findings'] ?? $payload['issues'] ?? [];
        if ((! is_string($answer) || trim($answer) === '') && is_array($findings) && $findings !== []) {
            $answer = collect($findings)->map(function ($finding): string {
                if (! is_array($finding)) return (string) $finding;
                $message = $finding['message'] ?? $finding['issue'] ?? $finding['title'] ?? 'Suggestion';
                $suggestion = $finding['suggestion'] ?? null;
                return trim((string) $message).($suggestion ? ' — '.trim((string) $suggestion) : '');
            })->implode("\n");
        }
        if (! is_string($answer) || trim($answer) === '') {
            $answer = $payload['summary'] ?? null;
        }

        return [
            'success' => (bool) ($payload['success'] ?? true),
            'answer' => (is_string($answer) && trim($answer) !== '') ? $answer : $fallbackTitle.' completed. No additional suggestions were returned.',
            'provider' => $payload['provider'] ?? $payload['source'] ?? 'rule_engine',
            'confidence' => $payload['confidence'] ?? null,
            'sources' => [],
            'request_id' => $payload['request_id'] ?? null,
        ];
    }

    /**
     * Admin: AI settings page.
     */
    public function settings()
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);

        $scopeUniversityId = auth()->user()->isSuperAdmin() ? null : auth()->user()->university_id;

        return view('ai.settings', [
            'modes' => AiMode::cases(),
            'providers' => AiProviderName::cases(),
            'mode' => $this->router->mode($scopeUniversityId)->value,
            'defaultProvider' => $this->router->defaultProviderName($scopeUniversityId),
            'fallbackProvider' => $this->router->fallbackProviderName($scopeUniversityId),
            'settings' => $this->aiSettings($scopeUniversityId),
            'rulePacks' => $this->rulePackSettings($scopeUniversityId),
            'features' => config('ai.features', []),
            'promptVersions' => AiPromptVersion::query()->where(function ($query) {
                if (auth()->user()->isSuperAdmin()) $query->whereNotNull('id');
                else $query->whereNull('university_id')->orWhere('university_id', auth()->user()->university_id);
            })->orderBy('feature')->orderByDesc('version')->get(),
        ]);
    }

    /**
     * Admin: persist AI settings.
     */
    public function updateSettings(Request $request)
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);

        $data = $request->validate([
            'ai_mode' => ['required', 'in:'.implode(',', AiMode::values())],
            'ai_default_provider' => ['required', 'in:'.implode(',', AiProviderName::values())],
            'ai_fallback_provider' => ['required', 'in:'.implode(',', AiProviderName::values())],
            'ai_similarity_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'ai_request_timeout' => ['required', 'integer', 'min:1', 'max:300'],
            'ai_max_tokens' => ['required', 'integer', 'min:1', 'max:32000'],
            'ai_daily_request_limit' => ['required', 'integer', 'min:0'],
            'ai_monthly_request_limit' => ['required', 'integer', 'min:0'],
            'ai_max_cost' => ['required', 'numeric', 'min:0'],
            'ai_cache_ttl' => ['required', 'integer', 'min:60', 'max:2592000'],
            'ai_provider_priority' => ['required', 'string', 'max:1000'],
            'ai_max_document_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'ai_document_formats' => ['required', 'string', 'max:500'],
        ]);

        $scopeUniversityId = auth()->user()->isSuperAdmin() ? null : auth()->user()->university_id;
        $actorId = auth()->id();
        $providerPriority = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['ai_provider_priority'])))));
        $validProviders = AiProviderName::values();
        if (array_diff($providerPriority, $validProviders)) {
            return back()->withErrors(['ai_provider_priority' => 'Provider priority contains an unsupported provider.'])->withInput();
        }
        $formats = array_values(array_unique(array_filter(array_map(fn ($item) => strtolower(trim($item)), explode(',', $data['ai_document_formats'])))));
        foreach ($data as $key => $value) {
            if ($key === 'ai_provider_priority') $value = json_encode($providerPriority);
            if ($key === 'ai_document_formats') $value = json_encode($formats);
            SettingService::set($key, $value, is_numeric($value) ? 'integer' : 'string', $scopeUniversityId, $actorId);
        }

        // Toggles
        foreach (['ai_enable_rule_engine', 'ai_enable_external_ai', 'ai_enable_hybrid_mode', 'ai_enable_cache', 'ai_enable_logging', 'ai_hybrid_escalate_when_clean'] as $toggle) {
            SettingService::set($toggle, $request->boolean($toggle), 'boolean', $scopeUniversityId, $actorId);
        }

        foreach (config('ai.features', []) as $feature) {
            SettingService::set('ai_feature_'.$feature, $request->boolean('ai_feature_'.$feature), 'boolean', $scopeUniversityId, $actorId);
        }

        foreach ($this->rulePackKeys() as $pack) {
            SettingService::set('ai_rulepack_'.$pack, $request->boolean('ai_rulepack_'.$pack), 'boolean', $scopeUniversityId, $actorId);
        }

        // Layout requirements (institution-level defaults)
        $layoutFonts = $request->input('ai_layout_required_fonts', []);
        if (! is_array($layoutFonts)) {
            $layoutFonts = array_filter(array_map('trim', explode(',', (string) $layoutFonts)), fn ($f) => $f !== '');
        }
        SettingService::set('ai_layout_required_fonts', json_encode(array_values($layoutFonts)), 'string', $scopeUniversityId, $actorId);
        SettingService::set('ai_layout_page_size', $request->input('ai_layout_page_size', 'A4'), 'string', $scopeUniversityId, $actorId);
        SettingService::set('ai_layout_min_margin_inches', $request->input('ai_layout_min_margin_inches', 1.0), 'decimal', $scopeUniversityId, $actorId);
        SettingService::set('ai_layout_line_spacing', $request->input('ai_layout_line_spacing', '1.5'), 'string', $scopeUniversityId, $actorId);
        SettingService::set('ai_layout_min_font_size', $request->input('ai_layout_min_font_size', 10), 'integer', $scopeUniversityId, $actorId);
        SettingService::set('ai_layout_require_page_numbering', $request->boolean('ai_layout_require_page_numbering'), 'boolean', $scopeUniversityId, $actorId);
        SettingService::set('ai_layout_require_branding', $request->boolean('ai_layout_require_branding'), 'boolean', $scopeUniversityId, $actorId);

        // Provider, quota, prompt-policy and rule-pack changes invalidate every cached feature deterministically.
        $this->manager->invalidateAll();

        return redirect()->route('ai.settings')->with('success', 'AI settings updated.');
    }

    /**
     * Student/Lecturer: view AI analysis for a submission.
     */
    public function submissionAnalysis(Submission $submission)
    {
        $this->authorize('view', $submission);

        $analyses = AiAnalysis::where('submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->get();

        return view('ai.submission-analysis', compact('submission', 'analyses'));
    }

    /**
     * On-demand re-run analysis for a submission.
     */
    public function reanalyze(Request $request, Submission $submission)
    {
        $this->authorize('view', $submission);

        $features = $request->input('features', ['submission_validator', 'plagiarism']);
        event(new SubmissionAiAnalysisRequested($submission, $request->user(), (array) $features));

        return back()->with('success', 'AI analysis queued. Refresh shortly to see results.');
    }

    /**
     * Lecturer: view/edit my layout preferences.
     */
    public function lecturerLayoutPreferences()
    {
        $user = auth()->user();
        if (! $user || ! $user->isLecturer()) {
            abort(403);
        }

        $prefs = \App\Models\LecturerLayoutPreference::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        $institutionDefaults = config('ai.layout_requirements', []);

        return view('ai.lecturer-layout-preferences', [
            'prefs' => $prefs,
            'institutionDefaults' => $institutionDefaults,
            'pageSizes' => ['A4', 'Letter', 'Legal', 'A3', 'A5'],
            'lineSpacings' => ['1.0', '1.15', '1.5', '2.0'],
        ]);
    }

    /**
     * Lecturer: save my layout preferences.
     */
    public function saveLecturerLayoutPreferences(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->isLecturer()) {
            abort(403);
        }

        $validated = $request->validate([
            'required_fonts' => ['nullable', 'array'],
            'required_fonts.*' => ['nullable', 'string', 'max:100'],
            'page_size' => ['nullable', 'string', 'max:20'],
            'min_margin_inches' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'line_spacing' => ['nullable', 'string', 'max:10'],
            'min_font_size_pt' => ['nullable', 'integer', 'min:6', 'max:72'],
            'require_page_numbering' => ['boolean'],
            'require_institution_branding' => ['boolean'],
        ]);

        $prefs = \App\Models\LecturerLayoutPreference::firstOrCreate(['user_id' => $user->id]);
        $prefs->fill($validated);
        $prefs->save();

        return redirect()->route('ai.lecturer.layout.preferences')
            ->with('success', 'Layout preferences saved. They will be used when you analyze submissions.');
    }

    public function analytics(Request $request)
    {
        $this->authorizeAi(Permission::VIEW_AI_ANALYTICS);

        $universityId = $request->user()->university_id;
        $departmentId = $request->user()->isDepartmentAdmin() ? $request->user()->department_id : null;

        $summary = $this->analytics->summary($universityId, $departmentId);

        return view('ai.analytics', compact('summary'));
    }

    /**
     * Writing assistant endpoint (returns reviewable suggestions).
     */
    public function writingAssistant(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:50000'],
            'type' => ['nullable', 'string'],
        ]);

        if (! $this->writing->isEnabled($request->user()?->university_id)) {
            return response()->json(['enabled' => false], 200);
        }

        $response = $this->writing->analyze($data['text'], $data['type'] ?? null, $request->user());

        return response()->json($response->toArray());
    }

    /**
     * Citation assistant endpoint.
     */
    public function citationAssistant(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:50000'],
            'style' => ['nullable', 'in:apa,mla,chicago,harvard,ieee,vancouver'],
        ]);

        if (! $this->citation->isEnabled($request->user()?->university_id)) {
            return response()->json(['enabled' => false], 200);
        }

        $response = $this->citation->analyze($data['text'], $data['style'] ?? 'apa', $request->user());

        return response()->json($response->toArray());
    }

    protected function aiSettings(?int $universityId = null): array
    {
        $layoutRequiredFonts = SettingService::get('ai_layout_required_fonts', config('ai.layout_requirements.required_fonts', []), $universityId);
        if (is_string($layoutRequiredFonts)) {
            $layoutRequiredFonts = json_decode($layoutRequiredFonts, true) ?: [];
        }

        return [
            'ai_enable_rule_engine' => (bool) SettingService::get('ai_enable_rule_engine', config('ai.enable_rule_engine', true), $universityId),
            'ai_enable_external_ai' => (bool) SettingService::get('ai_enable_external_ai', config('ai.enable_external_ai', false), $universityId),
            'ai_enable_hybrid_mode' => (bool) SettingService::get('ai_enable_hybrid_mode', config('ai.enable_hybrid_mode', true), $universityId),
            'ai_enable_cache' => (bool) SettingService::get('ai_enable_cache', config('ai.enable_cache', true), $universityId),
            'ai_enable_logging' => (bool) SettingService::get('ai_enable_logging', config('ai.enable_logging', true), $universityId),
            'ai_hybrid_escalate_when_clean' => (bool) SettingService::get('ai_hybrid_escalate_when_clean', config('ai.hybrid_escalate_when_clean', false), $universityId),
            'ai_similarity_threshold' => (int) SettingService::get('ai_similarity_threshold', config('ai.similarity_threshold', 20), $universityId),
            'ai_request_timeout' => (int) SettingService::get('ai_request_timeout', config('ai.request_timeout', 30), $universityId),
            'ai_max_tokens' => (int) SettingService::get('ai_max_tokens', config('ai.max_tokens', 2048), $universityId),
            'ai_daily_request_limit' => (int) SettingService::get('ai_daily_request_limit', config('ai.daily_request_limit', 1000), $universityId),
            'ai_monthly_request_limit' => (int) SettingService::get('ai_monthly_request_limit', config('ai.monthly_request_limit', 30000), $universityId),
            'ai_max_cost' => (float) SettingService::get('ai_max_cost', config('ai.max_cost', 100), $universityId),
            'ai_cache_ttl' => (int) SettingService::get('ai_cache_ttl', config('ai.cache_ttl', 86400), $universityId),
            'ai_provider_priority' => (($p = SettingService::get('ai_provider_priority', config('ai.provider_priority', []), $universityId)) && is_string($p)) ? (json_decode($p, true) ?: []) : $p,
            'ai_max_document_size_mb' => (int) SettingService::get('ai_max_document_size_mb', config('ai.max_document_size_mb', 20), $universityId),
            'ai_document_formats' => (($f = SettingService::get('ai_document_formats', config('ai.document_formats', []), $universityId)) && is_string($f)) ? (json_decode($f, true) ?: []) : $f,

            // Layout requirements
            'ai_layout_required_fonts' => $layoutRequiredFonts,
            'ai_layout_page_size' => SettingService::get('ai_layout_page_size', config('ai.layout_requirements.page_size', 'A4'), $universityId),
            'ai_layout_min_margin_inches' => SettingService::get('ai_layout_min_margin_inches', config('ai.layout_requirements.min_margin_inches', 1.0), $universityId),
            'ai_layout_line_spacing' => SettingService::get('ai_layout_line_spacing', config('ai.layout_requirements.line_spacing', '1.5'), $universityId),
            'ai_layout_min_font_size' => SettingService::get('ai_layout_min_font_size', config('ai.layout_requirements.min_font_size_pt', 10), $universityId),
            'ai_layout_require_page_numbering' => (bool) SettingService::get('ai_layout_require_page_numbering', config('ai.layout_requirements.require_page_numbering', false), $universityId),
            'ai_layout_require_branding' => (bool) SettingService::get('ai_layout_require_branding', config('ai.layout_requirements.require_institution_branding', false), $universityId),
        ];
    }
    protected function rulePackKeys(): array
    {
        return ['academic', 'assignment', 'research', 'project', 'siwes', 'seminar', 'citation', 'formatting', 'template', 'knowledge_hub', 'layout', 'deadline', 'institution', 'discussion', 'plagiarism'];
    }

    protected function rulePackSettings(?int $universityId = null): array
    {
        $out = [];
        foreach ($this->rulePackKeys() as $pack) {
            $out[$pack] = (bool) SettingService::get('ai_rulepack_'.$pack, true, $universityId);
        }

        return $out;
    }
}
