<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeRewardPayout;
use App\Models\KnowledgeWritingChallenge;
use App\Models\KnowledgeWritingEntry;
use App\Services\Knowledge\WritingChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public pages of the official AcadSyntra Writing Challenge. */
class KnowledgeWritingChallengeController extends Controller
{
    public function index(): View
    {
        $challenges = KnowledgeWritingChallenge::query()->whereIn('status', ['published'])->withCount('entries')->latest('starts_at')->limit(20)->get();
        $live = $challenges->first(fn ($c) => in_array($c->phase(), ['open', 'upcoming', 'judging'], true));
        $hallOfFame = KnowledgeWritingEntry::query()->whereNotNull('award')
            ->whereHas('challenge', fn ($q) => $q->whereNotNull('results_published_at'))
            ->with(['publication:id,uuid,slug,title', 'user:id,first_name,last_name,university_id', 'user.university:id,short_name', 'challenge:id,uuid,title,theme'])
            ->latest('updated_at')->limit(12)->get();

        return view('knowledge.writing.index', compact('challenges', 'live', 'hallOfFame'));
    }

    public function show(Request $request, KnowledgeWritingChallenge $challenge, WritingChallengeService $service): View
    {
        abort_unless($challenge->isVisible() || $request->user()?->isSuperAdmin(), 404);
        if ($challenge->phase() === 'open' || $challenge->phase() === 'judging') {
            $service->refreshReaderScores($challenge);
        }
        $board = $service->readersBoard($challenge, 10);
        $tracks = $service->trackCategories($challenge);
        $myEntry = $request->user() ? $challenge->entries()->where('user_id', $request->user()->id)->with('publication:id,uuid,slug,title')->first() : null;
        $eligibility = $request->user() && $challenge->phase() === 'open' && ! $myEntry ? $service->eligibility($request->user(), $challenge) : collect();
        $winners = $challenge->results_published_at
            ? $challenge->entries()->whereNotNull('award')->with(['publication:id,uuid,slug,title', 'user:id,first_name,last_name,university_id', 'user.university:id,short_name'])->orderByRaw('`rank` IS NULL')->orderBy('rank')->get()
            : collect();
        $finalists = $challenge->results_published_at
            ? $challenge->entries()->where('status', 'finalist')->whereNull('award')->with(['publication:id,uuid,slug,title', 'user:id,first_name,last_name'])->orderBy('rank')->get()
            : collect();
        $entryCount = $challenge->entries()->where('status', '!=', 'disqualified')->count();
        $paid = KnowledgeRewardPayout::query()->where('scope_key', $challenge->scopeKey())->where('status', 'paid')->exists();

        return view('knowledge.writing.show', compact('challenge', 'board', 'tracks', 'myEntry', 'eligibility', 'winners', 'finalists', 'entryCount', 'paid'));
    }

    public function enter(Request $request, KnowledgeWritingChallenge $challenge, WritingChallengeService $service): RedirectResponse
    {
        $data = $request->validate(['publication_id' => ['required', 'integer']]);
        $service->enter($request->user(), $challenge, (int) $data['publication_id']);

        return back()->with('success', 'You\'re in! Share your guide: every reader who passes its quiz moves you up the Readers\' favourite board.');
    }

    public function withdraw(Request $request, KnowledgeWritingEntry $entry, WritingChallengeService $service): RedirectResponse
    {
        $service->withdraw($request->user(), $entry);

        return back()->with('success', 'Your entry was withdrawn. You can enter a different piece before the deadline.');
    }
}
