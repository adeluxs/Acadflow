<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgePublication;
use App\Services\Discovery\RecommendationService;
use App\Services\EngagementService;
use App\Services\KnowledgeDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeHubController extends Controller
{
    public function index(Request $request, KnowledgeDiscoveryService $discovery, RecommendationService $recommendations): View
    {
        $publications = $discovery->search($request->only(['q', 'category', 'type', 'access']), $request->user());
        $categories = KnowledgeCategory::query()->where('is_active', true)->orderBy('name')->get();
        $recommended = $request->user() ? $recommendations->forUser($request->user(), 8) : collect();

        return view('knowledge.index', compact('publications', 'categories', 'recommended'));
    }

    public function show(Request $request, KnowledgePublication $publication, KnowledgeDiscoveryService $discovery, EngagementService $engagement, RecommendationService $recommendations): View
    {
        $user = $request->user();
        if ($user) {
            $this->authorize('view', $publication);
        } else {
            abort_unless($publication->isPublished() && $publication->visibility === 'public' && $publication->access_type !== 'institution', 404);
        }

        $publication->load(['document.versions', 'creator.creatorProfile', 'creator.reputationProfile', 'category', 'tags', 'university', 'department', 'sourceResearchProject', 'digitalFiles.mediaAsset', 'moderationReport', 'citationsReceived.citingPublication']);
        $discovery->recordView($publication, $user, ['referrer' => $request->headers->get('referer')]);
        if ($user) $recommendations->record($user, $publication, 'view', 1.0);
        $bookmarked = $user ? $publication->bookmarks()->where('user_id', $user->id)->exists() : false;
        $following = $user ? \App\Models\EngagementSubscription::query()->where('user_id',$user->id)->where('subscribable_type',$publication->getMorphClass())->where('subscribable_id',$publication->id)->exists() : false;
        $hasAccess = $publication->access_type === 'free'
            || ($publication->access_type === 'institution' && $user && $publication->university_id === $user->university_id)
            || ($user && ($publication->creator_id === $user->id || $user->isAdmin() || $user->hasEntitlement($publication)));
        $comments = $engagement->commentsFor($publication, 30);
        $reactionCounts = \App\Models\EngagementReaction::query()->where('reactable_type',$publication->getMorphClass())->where('reactable_id',$publication->id)->selectRaw('reaction, COUNT(*) as total')->groupBy('reaction')->pluck('total','reaction');
        $related = KnowledgePublication::query()->with(['creator','category'])->where('status','published')->where('id', '!=', $publication->id)->where(function($q)use($publication){$q->where('category_id',$publication->category_id)->orWhere('department_id',$publication->department_id);})->where(function($q)use($user){$q->where('visibility','public');if($user)$q->orWhere(fn($i)=>$i->where('visibility','institution')->where('university_id',$user->university_id));})->limit(6)->get();
        $gateways = \App\Models\PaymentGateway::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('knowledge.show', compact('publication', 'bookmarked', 'following', 'hasAccess', 'comments', 'reactionCounts', 'related', 'gateways'));
    }
}
