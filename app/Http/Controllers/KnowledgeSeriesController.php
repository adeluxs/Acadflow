<?php

namespace App\Http\Controllers;

use App\Models\EngagementSubscription;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePublication;
use App\Models\KnowledgePublicationType;
use App\Models\KnowledgeReadingProgress;
use App\Models\KnowledgeSeries;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeSeriesController extends Controller
{
    public function index(Request $request): View
    {
        $query = KnowledgeSeries::query()->with(['creator:id,uuid,first_name,last_name', 'category:id,name', 'publicationType:id,name'])->withCount(['chapters' => fn ($q) => $q->where('status', 'published')]);
        if ($request->routeIs('knowledge.series.manage')) {
            $query->where('creator_id', $request->user()->id);
        } else {
            $query->where('status', 'published')->where('visibility', 'public')->whereNotNull('published_at')->where('published_at', '<=', now());
        }
        $series = $query->latest('updated_at')->paginate(18)->withQueryString();

        return view('knowledge.series.index', ['series' => $series, 'managing' => $request->routeIs('knowledge.series.manage')]);
    }

    public function create(): View
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_series_enabled', true), 404);

        return view('knowledge.series.form', ['series' => new KnowledgeSeries] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) SettingService::getGlobal('knowledge_series_enabled', true), 404);
        $data = $this->validated($request);
        $series = KnowledgeSeries::create($data + ['creator_id' => $request->user()->id, 'status' => 'draft']);

        return redirect()->route('knowledge.series.edit', $series)->with('success', 'Series created. Add and publish chapters when ready.');
    }

    public function edit(Request $request, KnowledgeSeries $series): View
    {
        $this->owner($request, $series);
        $series->load(['chapters' => fn ($q) => $q->with('publicationType:id,name')->orderBy('chapter_number')]);

        return view('knowledge.series.form', compact('series') + $this->options());
    }

    public function update(Request $request, KnowledgeSeries $series): RedirectResponse
    {
        $this->owner($request, $series);
        $data = $this->validated($request);
        $data['status'] = $request->validate(['status' => ['required', 'in:draft,published,archived']])['status'];
        if ($data['status'] === 'published') {
            abort_unless($series->chapters()->where('status', 'published')->exists(), 422, 'Publish at least one approved chapter before publishing the series.');
            $data['published_at'] = $series->published_at ?: now();
        }
        $series->update($data);

        return back()->with('success', 'Series details updated.');
    }

    public function show(Request $request, KnowledgeSeries $series): View
    {
        $owner = $request->user()?->id === $series->creator_id;
        abort_unless($owner || ($series->status === 'published' && $series->visibility === 'public'), 404);
        $series->load(['creator.creatorProfile', 'category', 'publicationType', 'chapters' => fn ($q) => $q->with('publicationType:id,name')->when(! $owner, fn ($chapters) => $chapters->where('status', 'published')->where('published_at', '<=', now()))]);
        $progress = $request->user() ? KnowledgeReadingProgress::query()->where('user_id', $request->user()->id)->where('knowledge_series_id', $series->id)->first() : null;
        $following = $request->user() ? EngagementSubscription::query()->where('user_id', $request->user()->id)->where('subscribable_type', $series->getMorphClass())->where('subscribable_id', $series->id)->exists() : false;

        return view('knowledge.series.show', compact('series', 'progress', 'following', 'owner'));
    }

    public function follow(Request $request, KnowledgeSeries $series): RedirectResponse
    {
        abort_unless($series->status === 'published', 404);
        $key = ['user_id' => $request->user()->id, 'subscribable_type' => $series->getMorphClass(), 'subscribable_id' => $series->id];
        $subscription = EngagementSubscription::query()->where($key)->first();
        if ($subscription) {
            $subscription->delete();
        } else {
            EngagementSubscription::create($key + ['frequency' => 'immediate', 'is_muted' => false]);
        }

        return back()->with('success', $subscription ? 'Series alerts stopped.' : 'You will receive new chapter alerts.');
    }

    public function progress(Request $request, KnowledgeSeries $series, KnowledgePublication $publication): RedirectResponse
    {
        abort_unless($publication->series_id === $series->id && $publication->isPublished(), 404);
        $data = $request->validate(['progress_percent' => ['required', 'integer', 'min:0', 'max:100']]);
        KnowledgeReadingProgress::updateOrCreate(['user_id' => $request->user()->id, 'knowledge_series_id' => $series->id], ['knowledge_publication_id' => $publication->id, 'progress_percent' => $data['progress_percent'], 'last_read_at' => now(), 'completed_at' => $data['progress_percent'] === 100 ? now() : null]);

        return back()->with('success', 'Reading progress saved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'], 'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'], 'publication_type_id' => ['required', 'integer', 'exists:knowledge_publication_types,id'], 'genre' => ['nullable', 'string', 'max:100'], 'visibility' => ['required', 'in:public'], 'completion_status' => ['required', 'in:ongoing,completed,paused']]);
        $type = KnowledgePublicationType::query()->whereKey($data['publication_type_id'])->where('is_active', true)->firstOrFail();
        abort_unless($type->supports_series, 422, 'Choose a publication type that supports a series.');

        return $data;
    }

    private function owner(Request $request, KnowledgeSeries $series): void
    {
        abort_unless($series->creator_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);
    }

    private function options(): array
    {
        return ['types' => KnowledgePublicationType::query()->where('is_active', true)->where('supports_series', true)->orderBy('sort_order')->get(), 'categories' => KnowledgeCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()];
    }
}
