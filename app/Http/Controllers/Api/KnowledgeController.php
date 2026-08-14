<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EngagementComment;
use App\Models\EngagementThread;
use App\Models\KnowledgePublication;
use App\Services\Ai\GroundedCompanionService;
use App\Services\EngagementService;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\PublicationService;
use App\Services\KnowledgeDiscoveryService;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request, KnowledgeDiscoveryService $discovery)
    {
        return $discovery->search($request->only(['q', 'category', 'type', 'access']), $request->user());
    }

    public function show(Request $request, KnowledgePublication $publication)
    {
        $this->authorize('view', $publication);
        $publication->load(['document.versions.author', 'creator.creatorProfile', 'creator.reputationProfile', 'category', 'tags', 'sourceResearchProject', 'digitalFiles.mediaAsset', 'moderationReport']);
        $hasAccess = $publication->access_type === 'free'
            || ($publication->access_type === 'institution' && $publication->university_id === $request->user()->university_id)
            || $publication->creator_id === $request->user()->id || $request->user()->isAdmin() || $request->user()->hasEntitlement($publication);
        if (! $hasAccess) $publication->document?->setAttribute('body', null);
        return response()->json(['publication' => $publication, 'has_access' => $hasAccess]);
    }

    public function store(Request $request, PublicationService $publications)
    {
        $this->authorize('create', KnowledgePublication::class);
        $data = $this->validatePublication($request);
        return response()->json($publications->createDraft($data, $request->user()), 201);
    }

    public function update(Request $request, KnowledgePublication $publication, PublicationService $publications)
    {
        $this->authorize('update', $publication);
        return response()->json($publications->updateDraft($publication, $this->validatePublication($request), $request->user()));
    }

    public function submit(Request $request, KnowledgePublication $publication, ModerationService $moderation)
    {
        $this->authorize('submit', $publication);
        abort_if(trim(strip_tags((string) $publication->document?->body)) === '', 422, 'Publication content cannot be empty.');
        abort_if($publication->access_type === 'premium' && (float) $publication->price <= 0, 422, 'Premium publications require a price.');
        $publication->update(['status' => 'pending_review', 'submitted_at' => now(), 'moderation_note' => null]);
        $publication->document->update(['status' => 'review']);
        return response()->json($moderation->queue($publication, $request->user()), 202);
    }

    public function comments(Request $request, KnowledgePublication $publication, EngagementService $engagement)
    {
        $this->authorize('view', $publication);
        return $engagement->commentsFor($publication, min(100, max(1, $request->integer('per_page', 30))));
    }

    public function comment(Request $request, KnowledgePublication $publication, EngagementService $engagement)
    {
        $this->authorize('view', $publication);
        $data = $request->validate(['body' => 'required|string|max:20000', 'parent_id' => 'nullable|integer|exists:engagement_comments,id']);
        if ($data['parent_id'] ?? null) {
            $thread = EngagementThread::query()
                ->where('target_type', $publication::class)
                ->where('target_id', $publication->id)
                ->first();
            abort_unless($thread && EngagementComment::query()
                ->whereKey($data['parent_id'])
                ->where('engagement_thread_id', $thread->id)
                ->exists(), 422, 'Parent comment is outside this publication.');
        }
        return response()->json($engagement->comment($publication, $request->user(), $data['body'], ['parent_id' => $data['parent_id'] ?? null, 'visibility' => $publication->visibility]), 201);
    }

    public function react(Request $request, KnowledgePublication $publication, EngagementService $engagement)
    {
        $this->authorize('view', $publication);
        $data = $request->validate(['reaction' => 'required|in:like,helpful,insightful,celebrate']);
        return response()->json(['active' => $engagement->react($publication, $request->user(), $data['reaction'])]);
    }

    public function follow(Request $request, KnowledgePublication $publication, EngagementService $engagement)
    {
        $this->authorize('view', $publication);
        return response()->json(['following' => $engagement->subscribe($publication, $request->user())]);
    }

    public function companion(Request $request, KnowledgePublication $publication, GroundedCompanionService $companion)
    {
        $this->authorize('view', $publication);
        $data = $request->validate(['question' => 'required|string|min:3|max:2000']);
        return response()->json($companion->ask($publication, $data['question'], $request->user())->load('sources'), 201);
    }

    private function validatePublication(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255', 'body' => 'required|string|max:2000000', 'category_id' => 'nullable|integer|exists:knowledge_categories,id',
            'doi' => 'nullable|string|max:255', 'content_type' => 'required|string|max:80', 'language' => 'nullable|string|max:10', 'excerpt' => 'nullable|string|max:2000',
            'visibility' => 'required|in:private,public,institution', 'access_type' => 'required|in:free,premium,institution', 'price' => 'nullable|numeric|min:0',
            'tags' => 'nullable', 'copyright' => 'nullable|string|max:255',
        ]);
    }
}
