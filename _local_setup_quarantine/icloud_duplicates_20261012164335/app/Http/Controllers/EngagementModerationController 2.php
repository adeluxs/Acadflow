<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Discussion;
use App\Models\EngagementComment;
use App\Models\EngagementReport;
use App\Models\KnowledgePublication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EngagementModerationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeModerator($request);

        $reports = EngagementReport::query()
            ->with(['reporter', 'reviewer', 'reportable'])
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('university_id', $request->user()->university_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('moderation.engagement', compact('reports'));
    }

    public function review(Request $request, EngagementReport $report): RedirectResponse
    {
        $this->authorizeModerator($request);
        $this->assertTenant($request, $report);

        $data = $request->validate([
            'decision' => ['required', 'in:dismiss,hide,restore,lock_thread,unpublish'],
            'resolution' => ['required', 'string', 'max:5000'],
        ]);

        $target = $report->reportable;
        abort_unless($target, 404, 'The reported record no longer exists.');

        match ($data['decision']) {
            'hide' => $this->hideTarget($target),
            'restore' => $this->restoreTarget($target),
            'lock_thread' => $this->lockThread($target),
            'unpublish' => $this->unpublish($target),
            default => null,
        };

        $report->update([
            'status' => $data['decision'] === 'dismiss' ? 'dismissed' : 'resolved',
            'reviewed_by' => $request->user()->id,
            'resolution' => $data['resolution'],
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Moderation decision recorded with an audit trail.');
    }

    private function hideTarget(object $target): void
    {
        abort_unless($target instanceof EngagementComment, 422, 'Only comments can be hidden through this action.');
        $target->update(['status' => 'hidden']);
    }

    private function restoreTarget(object $target): void
    {
        abort_unless($target instanceof EngagementComment, 422, 'Only comments can be restored through this action.');
        $target->update(['status' => 'visible']);
    }

    private function lockThread(object $target): void
    {
        $thread = $target instanceof EngagementComment
            ? $target->thread
            : ($target instanceof Discussion ? $target->engagementThread : null);
        abort_unless($thread, 422, 'This record has no shared discussion thread.');
        $thread->update(['status' => 'closed', 'is_locked' => true]);
        if ($target instanceof Discussion) {
            $target->update(['status' => 'closed']);
        }
    }

    private function unpublish(object $target): void
    {
        abort_unless($target instanceof KnowledgePublication, 422, 'Only Knowledge Hub publications can be unpublished.');
        $target->update([
            'status' => 'archived',
            'moderated_by' => auth()->id(),
            'moderation_note' => 'Unpublished after a reviewed engagement report.',
        ]);
    }

    private function authorizeModerator(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function assertTenant(Request $request, EngagementReport $report): void
    {
        if (! $request->user()->isSuperAdmin()) {
            abort_unless($report->university_id === $request->user()->university_id, 404);
        }
    }
}
