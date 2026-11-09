<?php

namespace App\Http\Controllers;

use App\Models\AcademicChallenge;
use App\Models\ChallengePrizeFund;
use App\Services\Knowledge\ChallengePrizeService;
use App\Services\Knowledge\EventChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Creator actions for funded community challenge prizes. */
class CommunityChallengePrizeController extends Controller
{
    public function fund(Request $request, AcademicChallenge $challenge, ChallengePrizeService $prizes, EventChallengeService $challenges): RedirectResponse
    {
        $prizes->fund($challenge, $request->user());
        if ($request->boolean('publish') && in_array($challenge->status, ['draft', 'pending_review'], true)) {
            $challenges->changeChallengeStatus($challenge->fresh(), $request->user(), 'published');

            return back()->with('success', 'Prizes funded and challenge published. Entrants now see "Prize guaranteed by AcadSyntra".');
        }

        return back()->with('success', 'Prizes funded and held safely by AcadSyntra.');
    }

    public function pay(Request $request, AcademicChallenge $challenge, ChallengePrizeService $prizes): RedirectResponse
    {
        $fund = ChallengePrizeFund::query()->where('academic_challenge_id', $challenge->id)->firstOrFail();
        $fund = $prizes->disburse($fund, $request->user());

        return back()->with('success', $fund->awards()->count().' winner'.($fund->awards()->count() === 1 ? '' : 's').' paid.'.($fund->refunded_minor > 0 ? ' Unused NGN '.number_format($fund->refunded_minor / 100).' went back to your wallet.' : ''));
    }
}
