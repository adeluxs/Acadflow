<?php

namespace App\Http\Controllers;

use App\Models\ResearchProject;
use App\Models\ResearchSection;
use App\Services\ContentWorkspaceService;
use App\Services\EngagementService;
use App\Services\ResearchCollaborationService;
use App\Services\ResearchProjectService;
use App\Services\RichTextSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResearchSectionController extends Controller
{
    public function save(Request $request, ResearchProject $research, ResearchSection $section, ContentWorkspaceService $workspace, ResearchProjectService $projects, ResearchCollaborationService $collaboration, RichTextSanitizer $sanitizer): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $research);
        abort_unless($section->research_project_id === $research->id, 404);
        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'change_summary' => ['nullable', 'string', 'max:255'],
        ]);

        abort_if($section->locked_at && ! $request->user()->isAdmin(), 423, 'This approved section is locked. Request an amendment to change it.');
        $before = (string) $section->document->body;
        $cleanBody = $sanitizer->sanitize((string) ($data['body'] ?? ''));
        $document = $workspace->autosave($section->document, $request->user(), $cleanBody, $data['change_summary'] ?? null);
        $bodyText = trim(strip_tags((string) $document->body));
        $section->update(['status' => $bodyText === '' ? 'draft' : 'in_progress', 'completion_percent' => $bodyText === '' ? 0 : min(95, max(10, str_word_count($bodyText) / max(1, (int) (($research->templateVersion?->template_schema['sections'][$section->position]['min_words'] ?? 1000))) * 100))]);
        $collaboration->recordEdit($section, $request->user(), $before, (string) $document->body, $document->versions()->latest('version_number')->value('id'));
        $research->update(['last_activity_at' => now()]);
        $progress = $projects->recalculateProgress($research);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'version' => $document->version_number, 'progress' => $progress, 'autosaved_at' => $document->autosaved_at]);
        }

        return back()->with('success', $section->title.' saved as version '.$document->version_number.'.');
    }

    public function comment(Request $request, ResearchProject $research, ResearchSection $section, EngagementService $engagement): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless($section->research_project_id === $research->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'type' => ['nullable', 'in:comment,correction,question']]);
        $engagement->comment($section->document, $request->user(), $data['body'], [
            'section_key' => $section->key,
            'comment_type' => $data['type'] ?? 'comment',
            'visibility' => 'private',
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function approve(Request $request, ResearchProject $research, ResearchSection $section, ResearchProjectService $projects): RedirectResponse
    {
        $this->authorize('review', $research);
        abort_unless($section->research_project_id === $research->id, 404);
        $lock = (bool) ($research->templateVersion?->template_schema['sections'][$section->position]['locked_after_approval'] ?? true);
        $section->update(['status' => 'approved', 'completion_percent' => 100, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'locked_by' => $lock ? $request->user()->id : null, 'locked_at' => $lock ? now() : null]);
        $projects->recalculateProgress($research);

        return back()->with('success', $section->title.' approved.');
    }

    public function requestCorrection(Request $request, ResearchProject $research, ResearchSection $section, ResearchProjectService $projects): RedirectResponse
    {
        $this->authorize('review', $research);
        abort_unless($section->research_project_id === $research->id, 404);
        $data = $request->validate([
            'type' => ['required', 'in:general,rewrite,methodology,references,discussion,formatting,academic_language'],
            'description' => ['required', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);
        $research->corrections()->create(array_merge($data, [
            'research_section_id' => $section->id,
            'requested_by' => $request->user()->id,
            'assigned_to' => $research->owner_id,
            'status' => 'open',
        ]));
        $section->update(['status' => 'correction_requested']);
        $projects->recalculateProgress($research);

        return back()->with('success', 'Correction request recorded.');
    }
}
