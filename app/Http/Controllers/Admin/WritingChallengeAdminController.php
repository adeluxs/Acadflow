<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgeRewardPayout;
use App\Models\KnowledgeWritingChallenge;
use App\Models\KnowledgeWritingEntry;
use App\Services\Knowledge\WritingChallengeService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Super-admin management and judging of official AcadSyntra Writing Challenges. */
class WritingChallengeAdminController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $challenge = KnowledgeWritingChallenge::create($this->data($request) + ['created_by' => $request->user()->id]);
        AuditLog::log('knowledge.writing_challenge.created', $request->user()->id, KnowledgeWritingChallenge::class, $challenge->id, null, $challenge->only(['title', 'status']));

        return redirect()->route('admin.knowledge.read-earn.writing.show', $challenge)->with('success', 'Writing Challenge saved.');
    }

    public function update(Request $request, KnowledgeWritingChallenge $challenge): RedirectResponse
    {
        abort_if($challenge->results_published_at !== null, 422, 'Results are published; the challenge can no longer be edited.');
        $old = $challenge->only(['title', 'status', 'prizes', 'starts_at', 'submission_deadline']);
        $challenge->update($this->data($request));
        AuditLog::log('knowledge.writing_challenge.updated', $request->user()->id, KnowledgeWritingChallenge::class, $challenge->id, $old, $challenge->only(['title', 'status', 'prizes']));

        return back()->with('success', 'Writing Challenge updated.');
    }

    public function show(KnowledgeWritingChallenge $challenge, WritingChallengeService $service): View
    {
        $service->refreshReaderScores($challenge);
        $entries = $challenge->entries()->with(['publication:id,uuid,slug,title,category_id,published_at,content_document_id', 'publication.document:id,word_count', 'user:id,first_name,last_name,email,created_at', 'track:id,name', 'scores'])
            ->orderByRaw("CASE status WHEN 'disqualified' THEN 1 ELSE 0 END")->orderByDesc('final_score')->orderByDesc('judge_score')->orderByDesc('verified_reads')->get();
        $categories = KnowledgeCategory::query()->whereNull('university_id')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $payouts = KnowledgeRewardPayout::query()->where('scope_key', $challenge->scopeKey())->with('user:id,first_name,last_name,email')->orderBy('rank')->get();

        return view('admin.knowledge.writing-challenge', compact('challenge', 'entries', 'categories', 'payouts'));
    }

    public function score(Request $request, KnowledgeWritingEntry $entry, WritingChallengeService $service): RedirectResponse
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'integer', 'min:0', 'max:25'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $service->score($entry, $request->user(), $data['scores'], $data['comment'] ?? null);

        return back()->with('success', 'Score saved for "'.$entry->publication?->title.'".');
    }

    public function disqualify(Request $request, KnowledgeWritingEntry $entry, WritingChallengeService $service): RedirectResponse
    {
        $data = $request->validate(['disqualified' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);
        $service->setDisqualified($entry, (bool) $data['disqualified'], $data['note'] ?? null, $request->user());

        return back()->with('success', $data['disqualified'] ? 'Entry disqualified.' : 'Entry reinstated.');
    }

    public function publish(Request $request, KnowledgeWritingChallenge $challenge, WritingChallengeService $service): RedirectResponse
    {
        $drafts = $service->publishResults($challenge, $request->user());

        return back()->with('success', 'Results published and entrants notified. '.$drafts.' prize'.($drafts === 1 ? '' : 's').' drafted: check them below, then pay.');
    }

    public function announce(Request $request, KnowledgeWritingChallenge $challenge, WritingChallengeService $service): RedirectResponse
    {
        $batches = $service->announce($challenge, $request->user());

        return back()->with('success', 'Announcement queued for every active user ('.$batches.' batch'.($batches === 1 ? '' : 'es').').');
    }

    /** @return array<string,mixed> */
    private function data(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'submission_deadline' => ['required', 'date', 'after:starts_at'],
            'results_at' => ['required', 'date', 'after_or_equal:submission_deadline'],
            'tracks' => ['nullable', 'array', 'max:10'],
            'tracks.*' => ['integer', 'exists:knowledge_categories,id'],
            'prizes' => ['required', 'string', 'max:500', 'regex:/^\s*\d{1,9}(?:\s*,\s*\d{1,9})*\s*$/'],
            'track_prize' => ['nullable', 'regex:/^\d{1,9}$/'],
            'min_words' => ['required', 'integer', 'min:100', 'max:20000'],
            'min_questions' => ['required', 'integer', 'min:0', 'max:10'],
            'judge_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'finalists' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
        ]);
        $prizes = array_values(array_map(fn ($amount) => Money::toMinor(trim($amount)), array_filter(explode(',', $data['prizes']), fn ($amount) => trim($amount) !== '')));

        return [
            'title' => $data['title'], 'theme' => $data['theme'] ?? null, 'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'], 'submission_deadline' => $data['submission_deadline'], 'results_at' => $data['results_at'],
            'tracks' => array_values(array_map('intval', $data['tracks'] ?? [])), 'prizes' => $prizes,
            'track_prize_minor' => Money::toMinor((string) ($data['track_prize'] ?? '0')), 'min_words' => (int) $data['min_words'],
            'min_questions' => (int) $data['min_questions'], 'judge_weight' => (int) $data['judge_weight'], 'finalists' => (int) $data['finalists'], 'status' => $data['status'],
        ];
    }
}
