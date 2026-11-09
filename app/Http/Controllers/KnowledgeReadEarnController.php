<?php

namespace App\Http\Controllers;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizQuestion;
use App\Models\KnowledgeReaderView;
use App\Models\KnowledgeReadingChallenge;
use App\Models\KnowledgeRewardPayout;
use App\Models\User;
use App\Services\Knowledge\ReaderPassService;
use App\Services\Knowledge\ReadEarnSettings;
use App\Services\Knowledge\ReadToEarnService;
use App\Services\Knowledge\RewardPoolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KnowledgeReadEarnController extends Controller
{
    public function index(Request $request, ReadToEarnService $earn, ReaderPassService $passes, ReadEarnSettings $settings, RewardPoolService $pools): View
    {
        $user = $request->user();
        $earn->releaseHeld($user);
        $summary = $earn->summary($user);
        $hasPass = $passes->hasPass($user);
        $prices = $passes->prices();
        $rules = $settings->all();
        $payouts = KnowledgeRewardPayout::query()->where('user_id', $user->id)->where('status', 'paid')->latest('paid_at')->limit(10)->get();
        $challenges = KnowledgeReadingChallenge::query()->where('status', 'active')->where('ends_at', '>=', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('university_id')->orWhere('university_id', $user->university_id))
            ->orderBy('ends_at')->limit(5)->get();
        $myRanks = [];
        foreach ($challenges as $challenge) {
            $position = $pools->leaderboard($challenge, 200)->search(fn ($row) => (int) $row->user_id === $user->id);
            $myRanks[$challenge->id] = $position === false ? null : $position + 1;
        }

        $writingChallenges = \App\Models\KnowledgeWritingChallenge::query()->where('status', 'published')->whereNull('results_published_at')
            ->where('submission_deadline', '>=', now())->orderBy('submission_deadline')->limit(3)->get();

        return view('knowledge.rewards.index', compact('summary', 'hasPass', 'prices', 'rules', 'payouts', 'challenges', 'myRanks', 'writingChallenges'));
    }

    public function submitQuiz(Request $request, KnowledgePublication $publication, ReadToEarnService $earn, ReaderPassService $passes, ReadEarnSettings $settings): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('view', $publication);
        $this->assertCanRead($user, $publication, $passes, $settings);
        $data = $request->validate([
            'answers' => ['required', 'array', 'max:'.ReadToEarnService::MAX_QUESTIONS],
            'answers.*' => ['required', 'integer', 'min:0', 'max:9'],
            'reflection' => ['nullable', 'string', 'max:3000'],
        ]);
        $attempt = $earn->submit($user, $publication, $data['answers'], $data['reflection'] ?? null, $request->ip());

        $message = match (true) {
            $attempt->status === 'counted' => 'You scored '.$attempt->score_percent.'% and earned '.$attempt->points.' points'.($attempt->credit_minor > 0 ? ' plus NGN '.number_format($attempt->credit_minor / 100, 2).' study credit' : '').'.',
            $attempt->status === 'flagged' => 'You scored '.$attempt->score_percent.'%. Your points are held for a quick review.',
            $attempt->status === 'held' => 'You scored '.$attempt->score_percent.'% and earned '.$attempt->points.' points! They\'re saved for you: get a Reader Pass to collect them.',
            default => 'You scored '.$attempt->score_percent.'%. '.(ReadToEarnService::REASONS[$attempt->reason] ?? 'No points this time.'),
        };

        return redirect()->to(route('knowledge.show', $publication).'#read-earn')->with(in_array($attempt->status, ['counted', 'held'], true) ? 'success' : 'info', $message);
    }

    public function challenge(Request $request, KnowledgeReadingChallenge $challenge, RewardPoolService $pools): View
    {
        abort_unless(in_array($challenge->status, ['active', 'completed'], true) || $request->user()->isSuperAdmin(), 404);
        if ($challenge->university_id && ! $request->user()->isSuperAdmin()) {
            abort_unless($challenge->university_id === $request->user()->university_id, 404);
        }
        $board = $pools->leaderboard($challenge, 50);
        $names = User::withoutGlobalScopes()->whereIn('id', $board->pluck('user_id'))->with('university:id,name,short_name')->get(['id', 'first_name', 'last_name', 'university_id'])->keyBy('id');
        $winners = KnowledgeRewardPayout::query()->where('scope_key', $challenge->scopeKey())->where('status', 'paid')->orderBy('rank')->get();

        return view('knowledge.rewards.challenge', compact('challenge', 'board', 'names', 'winners'));
    }

    public function editQuestions(Request $request, KnowledgePublication $publication, ReadEarnSettings $settings, \App\Services\Knowledge\QuizBankGenerator $generator): View
    {
        $this->assertCanManageQuiz($request->user(), $publication);
        $questions = $publication->quizQuestions()->whereNull('retired_at')->get();
        $retired = $publication->quizQuestions()->whereNotNull('retired_at')->count();
        $minQuestions = $settings->int('knowledge_read_earn_min_questions');
        $aiEnabled = $generator->enabled();
        $perReader = $settings->int('knowledge_quiz_per_reader');

        return view('knowledge.rewards.questions', compact('publication', 'questions', 'retired', 'minQuestions', 'aiEnabled', 'perReader'));
    }

    public function reportQuestion(Request $request, \App\Models\KnowledgeQuizQuestion $question, ReadToEarnService $earn): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'in:wrong_answer,unclear,not_in_guide']]);
        $earn->report($request->user(), $question, $data['reason'] ?? null);
        $request->session()->push('reported_questions', $question->id);

        return back()->with('success', 'Thanks for reporting that question. We\'ll check it.');
    }

    public function regenerateQuestions(Request $request, KnowledgePublication $publication, \App\Services\Knowledge\QuizBankGenerator $generator): RedirectResponse
    {
        $this->assertCanManageQuiz($request->user(), $publication);
        abort_unless($generator->enabled(), 403, 'AI questions are switched off.');
        abort_unless($publication->status === 'published', 422, 'Publish the guide first; AI writes its questions once it is live.');
        DB::table('knowledge_publications')->where('id', $publication->id)->update(['quiz_status' => 'pending']);
        \App\Jobs\GenerateKnowledgeQuiz::dispatch($publication->id, true);

        return back()->with('success', 'AI is writing new questions. Your own questions stay. Refresh in a minute.');
    }

    public function updateQuestions(Request $request, KnowledgePublication $publication): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageQuiz($user, $publication);
        $data = $request->validate([
            'questions' => ['array', 'max:'.ReadToEarnService::MAX_QUESTIONS],
            'questions.*.question' => ['nullable', 'string', 'max:500'],
            'questions.*.options' => ['array', 'max:5'],
            'questions.*.options.*' => ['nullable', 'string', 'max:300'],
            'questions.*.correct' => ['nullable', 'integer', 'min:0', 'max:4'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
            'questions.*.source' => ['nullable', 'in:creator,ai'],
        ]);

        $clean = [];
        foreach ($data['questions'] ?? [] as $index => $row) {
            $question = trim((string) ($row['question'] ?? ''));
            $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), (array) ($row['options'] ?? [])), fn ($o) => $o !== ''));
            if ($question === '' && $options === []) {
                continue;
            }
            $correct = (int) ($row['correct'] ?? -1);
            // The correct answer index refers to the original option boxes; map it to the cleaned list.
            $rawOptions = array_map(fn ($o) => trim((string) $o), (array) ($row['options'] ?? []));
            $correctText = $rawOptions[$correct] ?? '';
            $mapped = array_search($correctText, $options, true);
            if ($question === '' || count($options) < 2 || $correctText === '' || $mapped === false) {
                throw ValidationException::withMessages(['questions.'.$index.'.question' => 'Question '.($index + 1).' needs the question, at least two options and the correct answer ticked.']);
            }
            $clean[] = ['question' => $question, 'options' => $options, 'correct_option' => (int) $mapped, 'explanation' => trim((string) ($row['explanation'] ?? '')) ?: null,
                'source' => ($row['source'] ?? 'creator') === 'ai' ? \App\Models\KnowledgeQuizQuestion::SOURCE_AI : \App\Models\KnowledgeQuizQuestion::SOURCE_CREATOR];
        }

        DB::transaction(function () use ($publication, $clean, $user): void {
            // Questions pulled by reader reports stay out unless an admin restores them.
            $publication->quizQuestions()->whereNull('retired_at')->delete();
            foreach ($clean as $position => $row) {
                KnowledgeQuizQuestion::create($row + ['knowledge_publication_id' => $publication->id, 'sort_order' => $position, 'is_active' => true, 'created_by' => $user->id]);
            }
            if ($publication->quiz_status === 'awaiting_approval') {
                DB::table('knowledge_publications')->where('id', $publication->id)->update(['quiz_status' => 'ready']);
            }
        });

        return back()->with('success', count($clean).' Read & Earn question'.(count($clean) === 1 ? '' : 's').' saved.');
    }

    private function assertCanManageQuiz(User $user, KnowledgePublication $publication): void
    {
        abort_unless($publication->creator_id === $user->id || $user->can('moderate', $publication), 403);
    }

    /** Mirrors the reading page: a quiz is only for content the reader was allowed to open. */
    private function assertCanRead(User $user, KnowledgePublication $publication, ReaderPassService $passes, ReadEarnSettings $settings): void
    {
        $access = $publication->access_type === 'free'
            || ($publication->access_type === 'institution' && $publication->university_id === $user->university_id)
            || $publication->creator_id === $user->id || $user->isAdmin() || $user->hasEntitlement($publication);
        abort_unless($access, 403, 'Unlock this publication before taking its quiz.');

        if ($publication->access_type === 'free' && $settings->bool('knowledge_reader_pass_enabled') && ! $user->isAdmin() && ! $passes->hasPass($user)) {
            $opened = KnowledgeReaderView::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)
                ->where('period', $passes->period())->where('metered', true)->exists();
            abort_unless($opened, 403, 'Get a Reader Pass to keep reading and earning.');
        }
    }
}
