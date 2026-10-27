<?php

namespace App\Http\Controllers;

use App\Models\EngagementReaction;
use App\Models\EngagementSubscription;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePromotionCampaign;
use App\Models\KnowledgePublication;
use App\Models\KnowledgePublicationType;
use App\Services\Discovery\RecommendationService;
use App\Services\EngagementService;
use App\Services\KnowledgeDiscoveryService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeHubController extends Controller
{
    public function index(Request $request, KnowledgeDiscoveryService $discovery, RecommendationService $recommendations): View
    {
        $publications = $discovery->search($request->only(['q', 'category', 'type', 'access', 'area', 'feed', 'recency', 'sort']), $request->user());
        $categories = KnowledgeCategory::query()->where('is_active', true)->where(fn ($q) => $q->whereNull('university_id')->when($request->user()?->university_id, fn ($scope) => $scope->orWhere('university_id', $request->user()->university_id)))->orderBy('sort_order')->orderBy('name')->get();
        $publicationTypes = KnowledgePublicationType::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'area']);
        $recommended = $request->user() && (bool) SettingService::getGlobal('knowledge_recommendations_enabled', true) ? $recommendations->forUser($request->user(), 8) : collect();
        $sponsored = collect();
        if ((bool) SettingService::getGlobal('knowledge_promotions_enabled', true)) {
            KnowledgePromotionCampaign::query()->where('status', 'active')->where('ends_at', '<=', now())->update(['status' => 'expired']);
            $sponsored = KnowledgePromotionCampaign::query()->with(['publication.creator:id,first_name,last_name', 'publication.category:id,name'])
                ->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                ->whereHas('publication', fn ($query) => $query->where('status', 'published')->where('visibility', 'public'))
                ->inRandomOrder()->limit(3)->get();
            if ($sponsored->isNotEmpty()) {
                KnowledgePromotionCampaign::query()->whereKey($sponsored->modelKeys())->increment('impressions');
            }
        }

        return view('knowledge.index', compact('publications', 'categories', 'publicationTypes', 'recommended', 'sponsored'));
    }

    public function show(Request $request, KnowledgePublication $publication, KnowledgeDiscoveryService $discovery, EngagementService $engagement, RecommendationService $recommendations): View
    {
        $user = $request->user();
        if ($user) {
            $this->authorize('view', $publication);
        } else {
            abort_unless($publication->isPublished() && $publication->visibility === 'public' && $publication->access_type !== 'institution', 404);
        }

        $publication->load(['creator.creatorProfile', 'creator.reputationProfile', 'category', 'publicationType', 'series', 'tags', 'university', 'department', 'sourceResearchProject', 'digitalFiles.mediaAsset', 'moderationReport']);
        $publication->loadCount('citationsReceived');
        $discovery->recordView($publication, $user, ['referrer' => $request->headers->get('referer')]);
        if ($user) {
            $recommendations->record($user, $publication, 'view', 1.0);
        }
        $bookmarked = $user ? $publication->bookmarks()->where('user_id', $user->id)->exists() : false;
        $following = $user ? EngagementSubscription::query()->where('user_id', $user->id)->where('subscribable_type', $publication->getMorphClass())->where('subscribable_id', $publication->id)->exists() : false;
        $hasAccess = $publication->access_type === 'free'
            || ($publication->access_type === 'institution' && $user && $publication->university_id === $user->university_id)
            || ($user && ($publication->creator_id === $user->id || $user->isAdmin() || $user->hasEntitlement($publication)));
        if ($hasAccess) {
            $publication->load('document');
        }
        $comments = $engagement->commentsFor($publication, 30);
        $reactionCounts = EngagementReaction::query()->where('reactable_type', $publication->getMorphClass())->where('reactable_id', $publication->id)->selectRaw('reaction, COUNT(*) as total')->groupBy('reaction')->pluck('total', 'reaction');
        $related = KnowledgePublication::query()->with(['creator', 'category'])->where('status', 'published')->where('id', '!=', $publication->id)->where(function ($q) use ($publication) {
            $q->where('category_id', $publication->category_id)->orWhere('department_id', $publication->department_id);
        })->where(function ($q) use ($user) {
            $q->where('visibility', 'public');
            if ($user) {
                $q->orWhere(fn ($i) => $i->where('visibility', 'institution')->where('university_id', $user->university_id));
            }
        })->limit(6)->get();
        $gateways = app(PaymentGatewayManager::class)->getCheckoutGateways();
        $previousChapter = $nextChapter = null;
        if ($publication->series_id) {
            $chapterQuery = KnowledgePublication::query()->where('series_id', $publication->series_id)->where('status', 'published')->where('published_at', '<=', now());
            $previousChapter = (clone $chapterQuery)->where('chapter_number', '<', $publication->chapter_number)->orderByDesc('chapter_number')->first(['id', 'slug', 'title', 'chapter_number']);
            $nextChapter = (clone $chapterQuery)->where('chapter_number', '>', $publication->chapter_number)->orderBy('chapter_number')->first(['id', 'slug', 'title', 'chapter_number']);
        }

        return view('knowledge.show', compact('publication', 'bookmarked', 'following', 'hasAccess', 'comments', 'reactionCounts', 'related', 'gateways', 'previousChapter', 'nextChapter'));
    }
}
