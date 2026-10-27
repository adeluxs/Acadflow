<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CommerceRevenueAllocation;
use App\Models\ContentVersion;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePromotionCampaign;
use App\Models\KnowledgePublication;
use App\Models\KnowledgePublicationType;
use App\Models\KnowledgeSeries;
use App\Models\KnowledgeTag;
use App\Models\WalletAccount;
use App\Services\ContentWorkspaceService;
use App\Services\Knowledge\CitationNetworkService;
use App\Services\Knowledge\CreatorMonetizationService;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\PublicationService;
use App\Services\NotificationService;
use App\Services\RichTextSanitizer;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgePublicationController extends Controller
{
    public function manage(Request $request): View
    {
        $user = $request->user();
        $publications = KnowledgePublication::query()->with(['category', 'sourceResearchProject', 'moderationReport'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('creator_id', $user->id))
            ->when($user->isUniversityAdmin(), fn ($q) => $q->where('university_id', $user->university_id))
            ->when($user->isDepartmentAdmin(), fn ($q) => $q->where('department_id', $user->department_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('updated_at')->paginate(20)->withQueryString();
        $creatorBase = KnowledgePublication::query()->where('creator_id', $user->id);
        $statusCounts = (clone $creatorBase)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $accessCounts = (clone $creatorBase)->selectRaw('access_type, COUNT(*) AS total')->groupBy('access_type')->pluck('total', 'access_type');
        $engagement = (clone $creatorBase)->selectRaw('COALESCE(SUM(view_count),0) AS reads, COALESCE(SUM(bookmark_count),0) AS saves, COALESCE(SUM(comment_count),0) AS comments')->first();
        $seriesCount = KnowledgeSeries::query()->where('creator_id', $user->id)->count();
        $campaignSummary = KnowledgePromotionCampaign::query()->where('creator_id', $user->id)->selectRaw('COUNT(*) AS total, COALESCE(SUM(impressions),0) AS impressions, COALESCE(SUM(clicks),0) AS clicks')->first();
        $sales = CommerceRevenueAllocation::query()->where('beneficiary_user_id', $user->id)->selectRaw('COUNT(*) AS total_sales, COALESCE(SUM(amount_minor),0) AS net_earnings_minor')->first();
        $wallet = WalletAccount::query()->where('user_id', $user->id)->first(['available_balance', 'pending_balance', 'lifetime_earnings', 'available_earnings_minor', 'pending_earnings_minor', 'lifetime_earnings_minor']);
        $topPublications = (clone $creatorBase)->where('status', 'published')->orderByDesc('view_count')->limit(5)->get(['id', 'slug', 'title', 'view_count', 'bookmark_count']);

        return view('knowledge.manage', compact('publications', 'statusCounts', 'accessCounts', 'engagement', 'seriesCount', 'campaignSummary', 'sales', 'wallet', 'topPublications'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', KnowledgePublication::class);
        abort_unless((bool) SettingService::getGlobal('knowledge_free_publishing_enabled', true), 403, 'New Knowledge Hub publishing is currently paused.');
        $series = $request->integer('series') ? KnowledgeSeries::query()->whereKey($request->integer('series'))->where('creator_id', $request->user()->id)->firstOrFail() : null;
        $publication = new KnowledgePublication(['series_id' => $series?->id, 'publication_type_id' => $series?->publication_type_id]);

        return view('knowledge.form', $this->formData($request, $publication));
    }

    public function store(Request $request, PublicationService $publications): RedirectResponse
    {
        $this->authorize('create', KnowledgePublication::class);
        $publication = $publications->createDraft($this->validated($request), $request->user());

        return redirect()->route('knowledge.manage.edit', $publication)->with('success', 'Knowledge Hub draft created.');
    }

    public function showManage(Request $request, KnowledgePublication $publication): View
    {
        $this->authorize('view', $publication);
        $publication->load([
            'document.versions.author', 'tags', 'category', 'creator', 'sourceResearchProject',
            'moderationReports.plagiarismCheck.matches', 'moderationReport', 'digitalFiles.mediaAsset',
        ]);

        return view('knowledge.manage-show', compact('publication'));
    }

    public function edit(Request $request, KnowledgePublication $publication): View|RedirectResponse
    {
        if (! $request->user()->can('update', $publication)) {
            // A creator must always be able to open their own publication. When
            // its workflow state is read-only (for example pending review), show
            // the management view instead of turning the Open action into a 403.
            if ($publication->creator_id === $request->user()->id || $request->user()->can('view', $publication)) {
                return redirect()->route('knowledge.manage.show', $publication)
                    ->with('info', 'This publication is currently read-only. You can still review its status and available workflow actions here.');
            }
            abort(403);
        }

        $publication->load(['document.versions.author', 'tags', 'sourceResearchProject', 'moderationReports.plagiarismCheck.matches', 'digitalFiles.mediaAsset']);

        return view('knowledge.form', $this->formData($request, $publication));
    }

    public function update(Request $request, KnowledgePublication $publication, PublicationService $publications): RedirectResponse
    {
        $this->authorize('update', $publication);
        $publications->updateDraft($publication, $this->validated($request), $request->user());

        return back()->with('success', 'Publication saved with version history.');
    }

    public function preview(Request $request, KnowledgePublication $publication): View
    {
        abort_unless($publication->creator_id === $request->user()->id || $request->user()->can('moderate', $publication), 403);
        $publication->load(['document', 'creator', 'category', 'tags', 'sourceResearchProject', 'digitalFiles.mediaAsset']);

        return view('knowledge.preview', ['publication' => $publication, 'hasAccess' => true]);
    }

    public function submit(Request $request, KnowledgePublication $publication, ModerationService $moderation): RedirectResponse
    {
        $this->authorize('submit', $publication);
        abort_if(trim(strip_tags((string) $publication->document->body)) === '', 422, 'Publication content cannot be empty.');
        abort_if($publication->access_type === 'premium' && Money::toMinor((string) $publication->price) <= 0, 422, 'Premium publications require a price.');
        $publication->update(['status' => 'pending_review', 'submitted_at' => now(), 'moderation_note' => null]);
        $publication->document->update(['status' => 'review']);
        $report = $moderation->queue($publication, $request->user());

        return back()->with('success', 'Publication submitted. Automated quality and similarity checks are queued for the human moderator. Report '.$report->uuid.'.');
    }

    public function moderate(Request $request, KnowledgePublication $publication, ModerationService $moderation, CitationNetworkService $citations): RedirectResponse
    {
        $this->authorize('moderate', $publication);
        $data = $request->validate(['decision' => ['required', 'in:approve,request_changes,reject,unpublish,archive'], 'note' => ['nullable', 'string', 'max:5000'], 'scheduled_at' => ['nullable', 'date', 'after:now']]);
        $old = $publication->status;
        if ($data['decision'] === 'approve') {
            if (! empty($data['scheduled_at'])) {
                $publication->update(['status' => 'scheduled', 'scheduled_at' => $data['scheduled_at'], 'moderation_note' => $data['note'] ?? null, 'moderated_by' => $request->user()->id]);
                $publication->document->update(['status' => 'review']);
            } else {
                $moderation->publish($publication, $request->user(), $data['note'] ?? null);
                $citations->rebuild($publication->fresh());
            }
        } else {
            $status = match ($data['decision']) {
                'request_changes' => 'changes_requested','reject' => 'rejected','unpublish' => 'unpublished','archive' => 'archived'
            };
            $publication->update(['status' => $status, 'moderation_note' => $data['note'] ?? null, 'moderated_by' => $request->user()->id, 'scheduled_at' => null]);
            $publication->document->update(['status' => $status]);
        }
        AuditLog::log('knowledge_publication_moderated', $request->user()->id, KnowledgePublication::class, $publication->id, $old, $publication->fresh()->status, $request->ip(), $request->userAgent(), 'status');
        if ($data['decision'] !== 'approve') {
            $publication->loadMissing('creator');
            app(NotificationService::class)->send($publication->creator, 'knowledge_publication_reviewed', 'Publication review updated', $publication->title.' is now '.str($publication->status)->replace('_', ' ').'.'.(! empty($data['note']) ? ' '.$data['note'] : ''), ['publication_id' => $publication->id, 'url' => route('knowledge.manage.show', $publication), 'event_key' => 'knowledge-review:'.$publication->id.':'.$publication->updated_at?->timestamp]);
        }

        return back()->with('success', 'Moderation decision recorded.');
    }

    public function duplicate(Request $request, KnowledgePublication $publication, ContentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('view', $publication);
        abort_unless($publication->creator_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $copy = DB::transaction(function () use ($publication, $request, $workspace) {
            $publication->load('document', 'tags');
            $doc = $workspace->create(['document_type' => 'knowledge_publication', 'title' => $publication->title.' (Copy)', 'body' => $publication->document->body, 'status' => 'draft', 'visibility' => 'private'], $request->user());
            $copy = $publication->replicate(['uuid', 'slug', 'status', 'submitted_at', 'scheduled_at', 'published_at', 'moderated_by', 'moderation_report_id', 'view_count', 'bookmark_count', 'comment_count', 'share_count', 'download_count', 'featured_at', 'pinned_at']);
            $copy->creator_id = $request->user()->id;
            $copy->university_id = $request->user()->university_id;
            $copy->department_id = $request->user()->department_id;
            $copy->content_document_id = $doc->id;
            $copy->title = $publication->title.' (Copy)';
            $copy->status = 'draft';
            $copy->visibility = 'private';
            $copy->save();
            $copy->tags()->sync($publication->tags->pluck('id'));

            return $copy;
        });

        return redirect()->route('knowledge.manage.edit', $copy)->with('success', 'Publication duplicated as a private draft.');
    }

    public function restoreVersion(Request $request, KnowledgePublication $publication, ContentVersion $version, ContentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('update', $publication);
        $workspace->restore($publication->document, $version, $request->user());
        $publication->update(['status' => 'draft']);

        return back()->with('success', 'Selected version restored as a new version.');
    }

    public function feature(Request $request, KnowledgePublication $publication): RedirectResponse
    {
        $this->authorize('moderate', $publication);
        $data = $request->validate(['action' => ['required', 'in:feature,unfeature,pin,unpin']]);
        $publication->update(match ($data['action']) {
            'feature' => ['featured_at' => now()],'unfeature' => ['featured_at' => null],'pin' => ['pinned_at' => now()],'unpin' => ['pinned_at' => null]
        });

        return back()->with('success', 'Publication placement updated.');
    }

    public function bookmark(Request $request, KnowledgePublication $publication): RedirectResponse
    {
        $this->authorize('view', $publication);
        $bookmark = $publication->bookmarks()->where('user_id', $request->user()->id)->first();
        if ($bookmark) {
            $bookmark->delete();
            $publication->whereKey($publication->id)->where('bookmark_count', '>', 0)->decrement('bookmark_count');
            $message = 'Bookmark removed.';
        } else {
            $publication->bookmarks()->create(['user_id' => $request->user()->id, 'created_at' => now()]);
            $publication->increment('bookmark_count');
            $message = 'Publication bookmarked.';
        }

        return back()->with('success', $message);
    }

    public function destroy(KnowledgePublication $publication): RedirectResponse
    {
        $this->authorize('delete', $publication);
        $publication->update(['status' => 'archived']);
        $publication->delete();

        return redirect()->route('knowledge.manage')->with('success', 'Publication moved to archive.');
    }

    public function forceDestroy(Request $request, string $publication): RedirectResponse
    {
        $model = KnowledgePublication::withTrashed()->where('slug', $publication)->firstOrFail();
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($model->trashed(), 422, 'Archive the publication before permanent deletion.');
        $model->forceDelete();

        return back()->with('success', 'Publication permanently deleted by a super administrator.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:1000000'], 'excerpt' => ['nullable', 'string', 'max:2000'], 'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'], 'publication_type_id' => ['required', 'integer', 'exists:knowledge_publication_types,id'], 'series_id' => ['nullable', 'integer', 'exists:knowledge_series,id'], 'chapter_number' => ['nullable', 'integer', 'min:1', 'max:10000'], 'language' => ['nullable', 'string', 'max:10'], 'audience' => ['nullable', 'string', 'max:80'], 'content_warning' => ['nullable', 'string', 'max:1000'], 'doi' => ['nullable', 'string', 'max:255'], 'copyright' => ['nullable', 'string', 'max:255'], 'license_type' => ['nullable', 'string', 'max:80'], 'ownership_declaration' => ['nullable', 'accepted'], 'visibility' => ['required', 'in:public,institution'], 'access_type' => ['required', 'in:free,premium,institution'], 'price' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'tags' => ['nullable', 'string', 'max:1000']]);
        $data['body'] = app(RichTextSanitizer::class)->sanitize($data['body']);
        abort_if(trim(strip_tags($data['body'])) === '', 422, 'Publication content cannot be empty.');
        if (! empty($data['category_id'])) {
            $category = KnowledgeCategory::findOrFail($data['category_id']);
            abort_unless($category->university_id === null || $request->user()->isSuperAdmin() || $category->university_id === $request->user()->university_id, 403);
            $allowed = $category->allowed_publication_types ?? [];
            abort_if($allowed !== [] && ! in_array((int) $data['publication_type_id'], array_map('intval', $allowed), true), 422, 'This publication type is not allowed in the selected category.');
        }
        $type = KnowledgePublicationType::query()->findOrFail($data['publication_type_id']);
        abort_if($type->is_academic && ! (bool) SettingService::getGlobal('knowledge_research_content_enabled', true), 403, 'Academic and research publishing is currently paused.');
        abort_if($type->area === 'stories_creativity' && ! (bool) SettingService::getGlobal('knowledge_stories_enabled', true), 403, 'Story publishing is currently paused.');
        if (($data['access_type'] ?? 'free') === 'premium') {
            abort_unless(! empty($data['ownership_declaration']), 422, 'Confirm that you own or have permission to sell this content.');
            $minimumKey = ! empty($data['series_id']) ? 'knowledge_minimum_chapter_price' : 'knowledge_minimum_publication_price';
            abort_if(! empty($data['series_id']) && ! (bool) SettingService::getGlobal('knowledge_premium_chapters_enabled', true), 403, 'Premium chapters are currently disabled.');
            abort_if(Money::toMinor((string) ($data['price'] ?? '0')) < Money::toMinor((string) SettingService::getGlobal($minimumKey, $minimumKey === 'knowledge_minimum_chapter_price' ? '50' : '100')), 422, 'Premium price is below the configured minimum.');
        }

        return $data;
    }

    protected function categoriesFor(Request $request)
    {
        return KnowledgeCategory::query()->with('parent:id,name')->where('is_active', true)->where(fn ($q) => $q->whereNull('university_id')->orWhere('university_id', $request->user()->university_id))->orderBy('sort_order')->orderBy('name')->get();
    }

    private function formData(Request $request, KnowledgePublication $publication): array
    {
        return ['publication' => $publication, 'categories' => $this->categoriesFor($request), 'publicationTypes' => KnowledgePublicationType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(), 'seriesOptions' => KnowledgeSeries::query()->where('creator_id', $request->user()->id)->whereIn('status', ['draft', 'published'])->orderBy('title')->get(), 'minimumPrice' => (string) SettingService::getGlobal('knowledge_minimum_publication_price', '100'), 'monetizationApproved' => app(CreatorMonetizationService::class)->isApproved($request->user())];
    }

    protected function syncTags(KnowledgePublication $publication, string $tags): void
    {
        $ids = collect(explode(',', $tags))->map(fn ($t) => trim($t))->filter()->unique(fn ($t) => Str::lower($t))->take(15)->map(function ($name) {
            $slug = Str::slug($name);

            return KnowledgeTag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        });
        $publication->tags()->sync($ids->all());
    }

    private function wordCount(string $body): int
    {
        return str_word_count(strip_tags($body));
    }

    private function readingTime(string $body): int
    {
        return max(1, (int) ceil($this->wordCount($body) / 220));
    }
}
