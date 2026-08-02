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
use App\Models\Submission;
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
     * Admin: AI settings page.
     */
    public function settings()
    {
        $this->authorizeAi(Permission::MANAGE_AI_SETTINGS);

        return view('ai.settings', [
            'modes' => AiMode::cases(),
            'providers' => AiProviderName::cases(),
            'mode' => $this->router->mode()->value,
            'defaultProvider' => $this->router->defaultProviderName(),
            'fallbackProvider' => $this->router->fallbackProviderName(),
            'settings' => $this->aiSettings(),
            'rulePacks' => $this->rulePackSettings(),
            'features' => config('ai.features', []),
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
        ]);

        foreach ($data as $key => $value) {
            SettingService::set($key, $value, is_numeric($value) ? 'integer' : 'string');
        }

        // Toggles
        foreach (['ai_enable_rule_engine', 'ai_enable_external_ai', 'ai_enable_hybrid_mode', 'ai_enable_cache', 'ai_enable_logging', 'ai_hybrid_escalate_when_clean'] as $toggle) {
            SettingService::set($toggle, $request->boolean($toggle), 'boolean');
        }

        foreach (config('ai.features', []) as $feature) {
            SettingService::set('ai_feature_'.$feature, $request->boolean('ai_feature_'.$feature), 'boolean');
        }

        foreach ($this->rulePackKeys() as $pack) {
            SettingService::set('ai_rulepack_'.$pack, $request->boolean('ai_rulepack_'.$pack), 'boolean');
        }

        // Layout requirements (institution-level defaults)
        $layoutFonts = $request->input('ai_layout_required_fonts', []);
        if (! is_array($layoutFonts)) {
            $layoutFonts = array_filter(array_map('trim', explode(',', (string) $layoutFonts)), fn ($f) => $f !== '');
        }
        SettingService::set('ai_layout_required_fonts', json_encode(array_values($layoutFonts)), 'string');
        SettingService::set('ai_layout_page_size', $request->input('ai_layout_page_size', 'A4'), 'string');
        SettingService::set('ai_layout_min_margin_inches', $request->input('ai_layout_min_margin_inches', 1.0), 'decimal');
        SettingService::set('ai_layout_line_spacing', $request->input('ai_layout_line_spacing', '1.5'), 'string');
        SettingService::set('ai_layout_min_font_size', $request->input('ai_layout_min_font_size', 10), 'integer');
        SettingService::set('ai_layout_require_page_numbering', $request->boolean('ai_layout_require_page_numbering'), 'boolean');
        SettingService::set('ai_layout_require_branding', $request->boolean('ai_layout_require_branding'), 'boolean');

        // Rule pack / mode changes invalidate cached analyses (Phase 7).
        $this->manager->invalidateFeature('submission_validator');
        $this->manager->invalidateFeature('plagiarism');
        $this->manager->invalidateFeature('citation_assistant');
        $this->manager->invalidateFeature('writing_assistant');

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

    protected function analytics(Request $request)
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

        if (! $this->writing->isEnabled()) {
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
            'style' => ['nullable', 'in:apa,mla,chicago,harvard,ieee'],
        ]);

        if (! $this->citation->isEnabled()) {
            return response()->json(['enabled' => false], 200);
        }

        $response = $this->citation->analyze($data['text'], $data['style'] ?? 'apa', $request->user());

        return response()->json($response->toArray());
    }

    protected function aiSettings(): array
    {
        $layoutRequiredFonts = SettingService::get('ai_layout_required_fonts', config('ai.layout_requirements.required_fonts', []));
        if (is_string($layoutRequiredFonts)) {
            $layoutRequiredFonts = json_decode($layoutRequiredFonts, true) ?: [];
        }

        return [
            'ai_enable_rule_engine' => (bool) SettingService::get('ai_enable_rule_engine', config('ai.enable_rule_engine', true)),
            'ai_enable_external_ai' => (bool) SettingService::get('ai_enable_external_ai', config('ai.enable_external_ai', false)),
            'ai_enable_hybrid_mode' => (bool) SettingService::get('ai_enable_hybrid_mode', config('ai.enable_hybrid_mode', true)),
            'ai_enable_cache' => (bool) SettingService::get('ai_enable_cache', config('ai.enable_cache', true)),
            'ai_enable_logging' => (bool) SettingService::get('ai_enable_logging', config('ai.enable_logging', true)),
            'ai_hybrid_escalate_when_clean' => (bool) SettingService::get('ai_hybrid_escalate_when_clean', config('ai.hybrid_escalate_when_clean', false)),
            'ai_similarity_threshold' => (int) SettingService::get('ai_similarity_threshold', config('ai.similarity_threshold', 20)),
            'ai_request_timeout' => (int) SettingService::get('ai_request_timeout', config('ai.request_timeout', 30)),
            'ai_max_tokens' => (int) SettingService::get('ai_max_tokens', config('ai.max_tokens', 2048)),
            'ai_daily_request_limit' => (int) SettingService::get('ai_daily_request_limit', config('ai.daily_request_limit', 1000)),
            'ai_monthly_request_limit' => (int) SettingService::get('ai_monthly_request_limit', config('ai.monthly_request_limit', 30000)),
            'ai_max_cost' => (float) SettingService::get('ai_max_cost', config('ai.max_cost', 100)),

            // Layout requirements
            'ai_layout_required_fonts' => $layoutRequiredFonts,
            'ai_layout_page_size' => SettingService::get('ai_layout_page_size', config('ai.layout_requirements.page_size', 'A4')),
            'ai_layout_min_margin_inches' => SettingService::get('ai_layout_min_margin_inches', config('ai.layout_requirements.min_margin_inches', 1.0)),
            'ai_layout_line_spacing' => SettingService::get('ai_layout_line_spacing', config('ai.layout_requirements.line_spacing', '1.5')),
            'ai_layout_min_font_size' => SettingService::get('ai_layout_min_font_size', config('ai.layout_requirements.min_font_size_pt', 10)),
            'ai_layout_require_page_numbering' => (bool) SettingService::get('ai_layout_require_page_numbering', config('ai.layout_requirements.require_page_numbering', false)),
            'ai_layout_require_branding' => (bool) SettingService::get('ai_layout_require_branding', config('ai.layout_requirements.require_institution_branding', false)),
        ];
    }

    protected function rulePackKeys(): array
    {
        return ['academic', 'assignment', 'project', 'siwes', 'citation', 'formatting', 'template', 'layout', 'deadline', 'institution', 'discussion', 'plagiarism'];
    }

    protected function rulePackSettings(): array
    {
        $out = [];
        foreach ($this->rulePackKeys() as $pack) {
            $out[$pack] = (bool) SettingService::get('ai_rulepack_'.$pack, true);
        }

        return $out;
    }
}
