<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizQuestion;
use App\Models\KnowledgeReadAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who reads a publication.
 *  - Creators get numbers only: reads over time, verified reads, quiz results, and where
 *    readers come from, with any university or department under MIN_GROUP readers folded
 *    into "Other" so no single reader can be picked out.
 *  - Admins also get the reader-by-reader list (for fraud checks and challenge judging).
 */
class ReaderAnalyticsService
{
    /** Smallest group shown by name to creators. */
    public const MIN_GROUP = 3;

    /** @return array<string,mixed> */
    public function summary(KnowledgePublication $publication, int $days = 30): array
    {
        $reads = DB::table('knowledge_publication_reads')->where('knowledge_publication_id', $publication->id);
        $signedIn = (clone $reads)->whereNotNull('user_id')->count();
        $visitors = (clone $reads)->whereNull('user_id')->count();

        $attempts = DB::table('knowledge_read_attempts')->where('knowledge_publication_id', $publication->id);
        $quiz = (clone $attempts)->selectRaw("COUNT(*) AS taken, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS passed_count, SUM(CASE WHEN status = 'counted' THEN 1 ELSE 0 END) AS verified, AVG(score_percent) AS average_score")->first();

        $since = now()->subDays($days - 1)->startOfDay();
        $daily = (clone $reads)->where('created_at', '>=', $since)->selectRaw('DATE(created_at) AS read_day, COUNT(*) AS total')
            ->groupBy('read_day')->pluck('total', 'read_day');
        $series = collect(range(0, $days - 1))->map(function ($offset) use ($since, $daily) {
            $day = $since->copy()->addDays($offset)->toDateString();

            return ['date' => $day, 'reads' => (int) ($daily[$day] ?? 0)];
        });

        return [
            'total_reads' => (int) $publication->view_count,
            'signed_in' => $signedIn,
            'visitors' => $visitors,
            'quiz_taken' => (int) ($quiz->taken ?? 0),
            'quiz_passed' => (int) ($quiz->passed_count ?? 0),
            'verified' => (int) ($quiz->verified ?? 0),
            'pass_rate' => ($quiz->taken ?? 0) > 0 ? (int) round(100 * $quiz->passed_count / $quiz->taken) : null,
            'average_score' => $quiz->average_score !== null ? (int) round((float) $quiz->average_score) : null,
            'daily' => $series,
            'last_7' => (int) $series->slice(-7)->sum('reads'),
        ];
    }

    /**
     * Readers grouped by university or department. Groups smaller than MIN_GROUP are folded
     * into "Other" unless $exact (admins).
     *
     * @return Collection<int,array{name:string,readers:int}>
     */
    public function breakdown(KnowledgePublication $publication, string $by, bool $exact = false): Collection
    {
        [$table, $column] = $by === 'department' ? ['departments', 'department_id'] : ['universities', 'university_id'];
        $rows = DB::table('knowledge_publication_reads as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin($table.' as g', 'g.id', '=', 'u.'.$column)
            ->where('r.knowledge_publication_id', $publication->id)
            ->groupBy('g.id', 'g.name')
            ->selectRaw('g.name AS group_name, COUNT(*) AS readers')
            ->orderByDesc('readers')->limit(50)->get();

        $named = collect();
        $other = 0;
        foreach ($rows as $row) {
            if ($row->group_name === null || (! $exact && $row->readers < self::MIN_GROUP)) {
                $other += (int) $row->readers;

                continue;
            }
            $named->push(['name' => (string) $row->group_name, 'readers' => (int) $row->readers]);
        }
        if ($other > 0) {
            $named->push(['name' => $exact ? 'Not set' : 'Other or not set', 'readers' => $other]);
        }

        return $named->take(11)->values();
    }

    /**
     * How readers did on each question: the ones most often missed show which parts of
     * the guide may be unclear. Looks at the latest 2,000 attempts.
     *
     * @return Collection<int,array{question:string,answered:int,correct_rate:int,source:string}>
     */
    public function questionStats(KnowledgePublication $publication): Collection
    {
        $questions = KnowledgeQuizQuestion::query()->where('knowledge_publication_id', $publication->id)->get(['id', 'question', 'correct_option', 'source'])->keyBy('id');
        if ($questions->isEmpty()) {
            return collect();
        }
        $tally = [];
        KnowledgeReadAttempt::query()->where('knowledge_publication_id', $publication->id)->latest('id')->limit(2000)->pluck('answers')
            ->each(function ($answers) use ($questions, &$tally) {
                foreach ((array) $answers as $id => $pick) {
                    $question = $questions->get((int) $id);
                    if (! $question) {
                        continue;
                    }
                    $tally[$question->id]['answered'] = ($tally[$question->id]['answered'] ?? 0) + 1;
                    $tally[$question->id]['correct'] = ($tally[$question->id]['correct'] ?? 0) + ((int) $pick === $question->correct_option ? 1 : 0);
                }
            });

        return collect($tally)->map(fn ($row, $id) => [
            'question' => (string) $questions[$id]->question,
            'source' => (string) $questions[$id]->source,
            'answered' => $row['answered'],
            'correct_rate' => (int) round(100 * $row['correct'] / max(1, $row['answered'])),
        ])->sortBy('correct_rate')->values();
    }

    /** Admins only: each signed-in reader with their quiz result. */
    public function readers(KnowledgePublication $publication, ?string $search = null, int $perPage = 50): LengthAwarePaginator
    {
        return DB::table('knowledge_publication_reads as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('universities as un', 'un.id', '=', 'u.university_id')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->leftJoin('knowledge_read_attempts as a', function ($join) {
                $join->on('a.knowledge_publication_id', '=', 'r.knowledge_publication_id')->on('a.user_id', '=', 'r.user_id');
            })
            ->where('r.knowledge_publication_id', $publication->id)
            ->when($search, function ($query, $search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(fn ($q) => $q->where('u.first_name', 'like', $like)->orWhere('u.last_name', 'like', $like)->orWhere('u.email', 'like', $like));
            })
            ->orderByDesc('r.created_at')
            ->select([
                'u.id as user_id', 'u.first_name', 'u.last_name', 'u.email', 'u.created_at as joined_at', 'un.name as university', 'd.name as department',
                'r.created_at as read_at', 'a.score_percent', 'a.passed', 'a.status as attempt_status', 'a.reason as attempt_reason', 'a.points', 'a.read_seconds', 'a.created_at as quiz_at',
            ])
            ->paginate($perPage)->withQueryString();
    }
}
