<?php

namespace App\Http\Controllers;

use App\Models\KnowledgePublication;
use App\Services\Knowledge\ReaderAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeReaderAnalyticsController extends Controller
{
    /** Creator view: numbers only, no reader names. */
    public function creator(Request $request, KnowledgePublication $publication, ReaderAnalyticsService $analytics): View
    {
        abort_unless($publication->creator_id === $request->user()->id || $request->user()->can('moderate', $publication), 403);

        return view('knowledge.readers', [
            'publication' => $publication,
            'summary' => $analytics->summary($publication),
            'universities' => $analytics->breakdown($publication, 'university'),
            'departments' => $analytics->breakdown($publication, 'department'),
            'questions' => $analytics->questionStats($publication),
            'minGroup' => ReaderAnalyticsService::MIN_GROUP,
        ]);
    }

    /** Admin view: everything, including who each reader is and how they did on the quiz. */
    public function admin(Request $request, KnowledgePublication $publication, ReaderAnalyticsService $analytics): View
    {
        $search = trim((string) $request->query('q', '')) ?: null;

        return view('admin.knowledge.readers', [
            'publication' => $publication->loadMissing('creator:id,first_name,last_name,email'),
            'summary' => $analytics->summary($publication),
            'universities' => $analytics->breakdown($publication, 'university', true),
            'questions' => $analytics->questionStats($publication),
            'readers' => $analytics->readers($publication, $search),
            'search' => $search,
        ]);
    }

    /** Admin: pick a guide to inspect, most-read first, with title search. */
    public function adminIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $publications = KnowledgePublication::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('status', 'published')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))
            ->with('creator:id,first_name,last_name')->orderByDesc('view_count')
            ->paginate(30, ['id', 'slug', 'title', 'creator_id', 'view_count', 'access_type', 'published_at'])->withQueryString();

        return view('admin.knowledge.readers-index', compact('publications', 'search'));
    }
}
