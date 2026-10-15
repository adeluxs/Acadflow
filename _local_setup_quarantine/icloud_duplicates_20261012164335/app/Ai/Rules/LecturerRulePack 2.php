<?php

namespace App\Ai\Rules;

class LecturerRulePack extends BaseRulePack
{
    public function key(): string { return 'lecturer'; }
    public function label(): string { return 'Lecturer and Supervisor Assistance'; }

    public function analyze(array $context): array
    {
        $submissions = collect($context['submissions'] ?? []);
        $errors = $submissions->flatMap(fn ($submission) => $submission['issues'] ?? [])->countBy('code')->sortDesc();

        return $this->result([], [
            'submission_count' => $submissions->count(),
            'common_errors' => $errors->take(10)->map(fn ($count, $code) => ['code' => $code, 'count' => $count])->values()->all(),
            'at_risk_count' => $submissions->filter(fn ($submission) => ($submission['score'] ?? 100) < 50)->count(),
            'human_decision_required' => true,
        ]);
    }
}
