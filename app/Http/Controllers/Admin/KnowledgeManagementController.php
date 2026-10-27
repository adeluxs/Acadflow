<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreatorMonetizationApplication;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePromotionCampaign;
use App\Models\KnowledgePromotionPackage;
use App\Models\KnowledgePublicationType;
use App\Services\NotificationService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KnowledgeManagementController extends Controller
{
    private const TOGGLES = ['knowledge_free_publishing_enabled', 'knowledge_research_content_enabled', 'knowledge_stories_enabled', 'knowledge_series_enabled', 'knowledge_paid_content_enabled', 'knowledge_premium_chapters_enabled', 'knowledge_publisher_monetization_enabled', 'knowledge_promotions_enabled', 'knowledge_withdrawals_enabled', 'knowledge_comments_enabled', 'knowledge_reactions_enabled', 'knowledge_follows_enabled', 'knowledge_recommendations_enabled'];

    public function index(): View
    {
        $settings = SettingService::getMany(array_fill_keys(self::TOGGLES, true));
        $settings += SettingService::getMany(['knowledge_minimum_publication_price' => '100', 'knowledge_minimum_chapter_price' => '50', 'knowledge_platform_commission_percentage' => '15', 'creator_earnings_hold_days' => 3, 'minimum_withdrawal_amount' => '1000']);
        $types = KnowledgePublicationType::query()->orderBy('sort_order')->orderBy('name')->limit(100)->get();
        $categories = KnowledgeCategory::query()->with('parent:id,name')->withCount('publications')->orderBy('sort_order')->orderBy('name')->paginate(30, ['*'], 'categories_page');
        $applications = CreatorMonetizationApplication::query()->with(['user:id,uuid,first_name,last_name,email', 'payoutAccount:id,provider,bank_name,currency,is_verified'])->latest('submitted_at')->paginate(20, ['*'], 'applications_page');
        $promotionPackages = KnowledgePromotionPackage::query()->orderBy('sort_order')->limit(50)->get();
        $promotionCampaigns = KnowledgePromotionCampaign::query()->with(['creator:id,uuid,first_name,last_name', 'publication:id,slug,title', 'package:id,name'])->latest()->paginate(20, ['*'], 'promotions_page');

        return view('admin.knowledge.index', compact('settings', 'types', 'categories', 'applications', 'promotionPackages', 'promotionCampaigns'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        foreach (self::TOGGLES as $key) {
            SettingService::set($key, $request->boolean($key), 'boolean', null, $request->user()->id);
        }
        $data = $request->validate(['knowledge_minimum_publication_price' => ['required', 'numeric', 'min:0'], 'knowledge_minimum_chapter_price' => ['required', 'numeric', 'min:0']]);
        SettingService::set('knowledge_minimum_publication_price', $data['knowledge_minimum_publication_price'], 'decimal', null, $request->user()->id);
        SettingService::set('knowledge_minimum_chapter_price', $data['knowledge_minimum_chapter_price'], 'decimal', null, $request->user()->id);

        return back()->with('success', 'Knowledge Hub runtime settings updated.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->typeData($request);
        KnowledgePublicationType::create($data + ['slug' => Str::slug($data['slug'] ?: $data['name'])]);

        return back()->with('success', 'Publication type created.');
    }

    public function updateType(Request $request, KnowledgePublicationType $publicationType): RedirectResponse
    {
        $publicationType->update($this->typeData($request, $publicationType));

        return back()->with('success', 'Publication type updated without changing existing publications.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->categoryData($request);
        KnowledgeCategory::create($data + ['slug' => Str::slug($data['slug'] ?: $data['name']), 'university_id' => null]);

        return back()->with('success', 'Knowledge category created.');
    }

    public function updateCategory(Request $request, KnowledgeCategory $category): RedirectResponse
    {
        abort_unless($category->university_id === null, 403);
        $category->update($this->categoryData($request, $category));

        return back()->with('success', 'Knowledge category updated. Existing links and IDs were preserved.');
    }

    public function reviewApplication(Request $request, CreatorMonetizationApplication $application): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject', 'suspend'])], 'review_note' => ['nullable', 'string', 'max:3000']]);
        $status = match ($data['decision']) {
            'approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended'
        };
        $application->update(['status' => $status, 'reviewed_by' => $request->user()->id, 'review_note' => $data['review_note'] ?? null, 'reviewed_at' => now(), 'suspended_at' => $status === 'suspended' ? now() : null]);
        app(NotificationService::class)->send($application->user, 'creator_monetization_reviewed', 'Creator monetization '.str($status)->replace('_', ' '), 'Your creator monetization application was '.$status.'.'.(! empty($data['review_note']) ? ' '.$data['review_note'] : ''), ['url' => route('knowledge.creator.edit').'#creator-monetization', 'event_key' => 'creator-monetization-review:'.$application->id.':'.$application->updated_at?->timestamp]);

        return back()->with('success', 'Creator monetization decision recorded.');
    }

    public function storePromotionPackage(Request $request): RedirectResponse
    {
        $data = $this->promotionPackageData($request);
        KnowledgePromotionPackage::create($data + ['slug' => Str::slug($data['slug'] ?: $data['name'])]);

        return back()->with('success', 'Promotion package created.');
    }

    public function updatePromotionPackage(Request $request, KnowledgePromotionPackage $promotionPackage): RedirectResponse
    {
        $promotionPackage->update($this->promotionPackageData($request, $promotionPackage));

        return back()->with('success', 'Promotion package updated. Existing campaign price snapshots were preserved.');
    }

    public function reviewPromotion(Request $request, KnowledgePromotionCampaign $campaign): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['pause', 'resume', 'cancel'])], 'review_note' => ['nullable', 'string', 'max:3000']]);
        $status = match ($data['decision']) {
            'pause' => 'paused', 'resume' => ($campaign->ends_at?->isFuture() ? 'active' : 'expired'), 'cancel' => 'cancelled'
        };
        $campaign->update(['status' => $status, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null]);

        return back()->with('success', 'Promotion campaign status updated.');
    }

    private function typeData(Request $request, ?KnowledgePublicationType $type = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string', 'max:100', Rule::unique('knowledge_publication_types', 'slug')->ignore($type?->id)], 'area' => ['required', Rule::in(['academics_research', 'campus_student_life', 'stories_creativity', 'life_personal_growth', 'skills_business_professional'])], 'description' => ['nullable', 'string', 'max:2000'], 'icon' => ['nullable', 'string', 'max:50'], 'supports_series' => ['sometimes', 'boolean'], 'is_academic' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000']]) + ['supports_series' => $request->boolean('supports_series'), 'is_academic' => $request->boolean('is_academic'), 'is_active' => $request->boolean('is_active')];
    }

    private function categoryData(Request $request, ?KnowledgeCategory $category = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string', 'max:120', Rule::unique('knowledge_categories', 'slug')->whereNull('university_id')->ignore($category?->id)], 'parent_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'], 'description' => ['nullable', 'string', 'max:2000'], 'area' => ['required', Rule::in(['academics_research', 'campus_student_life', 'stories_creativity', 'life_personal_growth', 'skills_business_professional'])], 'icon' => ['nullable', 'string', 'max:50'], 'allowed_publication_types' => ['nullable', 'array'], 'allowed_publication_types.*' => ['integer', 'exists:knowledge_publication_types,id'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000'], 'is_active' => ['sometimes', 'boolean']]);
        abort_if($category && (int) ($data['parent_id'] ?? 0) === $category->id, 422, 'A category cannot be its own parent.');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function promotionPackageData(Request $request, ?KnowledgePromotionPackage $package = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('knowledge_promotion_packages', 'slug')->ignore($package?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
