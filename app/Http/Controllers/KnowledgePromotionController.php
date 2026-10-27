<?php

namespace App\Http\Controllers;

use App\Models\KnowledgePromotionCampaign;
use App\Models\KnowledgePromotionPackage;
use App\Models\KnowledgePublication;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KnowledgePromotionController extends Controller
{
    public function index(Request $request, PaymentGatewayManager $gateways): View
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_promotions_enabled', true), 404);
        $campaigns = KnowledgePromotionCampaign::query()
            ->with(['publication:id,slug,title', 'package:id,name,duration_days'])
            ->where('creator_id', $request->user()->id)
            ->latest()->paginate(20);
        $packages = KnowledgePromotionPackage::query()->where('is_active', true)->orderBy('sort_order')->get();
        $publications = KnowledgePublication::query()->where('creator_id', $request->user()->id)
            ->where('status', 'published')->where('visibility', 'public')->latest('published_at')->limit(100)->get(['id', 'title', 'slug']);
        $checkoutGateways = $gateways->getCheckoutGateways();

        return view('knowledge.promotions.index', compact('campaigns', 'packages', 'publications', 'checkoutGateways'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_promotions_enabled', true), 404);
        $data = $request->validate([
            'knowledge_publication_id' => ['required', 'integer'],
            'knowledge_promotion_package_id' => ['required', 'integer', Rule::exists('knowledge_promotion_packages', 'id')->where('is_active', true)],
        ]);
        $publication = KnowledgePublication::query()->whereKey($data['knowledge_publication_id'])->where('creator_id', $request->user()->id)
            ->where('status', 'published')->where('visibility', 'public')->firstOrFail();
        $package = KnowledgePromotionPackage::query()->whereKey($data['knowledge_promotion_package_id'])->where('is_active', true)->firstOrFail();
        $campaign = KnowledgePromotionCampaign::create([
            'creator_id' => $request->user()->id,
            'knowledge_publication_id' => $publication->id,
            'knowledge_promotion_package_id' => $package->id,
            'title' => $package->name.' — '.$publication->title,
            'price' => $package->price,
            'status' => 'draft',
        ]);

        return redirect()->route('knowledge.promotions.checkout', $campaign)->with('success', 'Campaign prepared. Complete payment to submit it for activation.');
    }

    public function checkout(Request $request, KnowledgePromotionCampaign $campaign, PaymentGatewayManager $gateways): View
    {
        abort_unless($campaign->creator_id === $request->user()->id, 403);
        $campaign->load(['publication:id,slug,title', 'package:id,name,duration_days']);

        return view('knowledge.promotions.checkout', ['campaign' => $campaign, 'checkoutGateways' => $gateways->getCheckoutGateways()]);
    }

    public function click(KnowledgePromotionCampaign $campaign): RedirectResponse
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_promotions_enabled', true) && $campaign->isDeliverable(), 404);
        $campaign->increment('clicks');

        return redirect()->route('knowledge.show', $campaign->publication);
    }
}
