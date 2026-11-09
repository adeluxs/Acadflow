<?php

use App\Http\Controllers\Admin\ReadEarnManagementController;
use App\Http\Controllers\KnowledgeCreatorFollowController;
use App\Http\Controllers\KnowledgeReadEarnController;
use App\Http\Controllers\KnowledgeWritingChallengeController;
use App\Http\Controllers\Admin\WritingChallengeAdminController;
use Illuminate\Support\Facades\Route;

// Included inside the verified, onboarding-complete application route group.
// Reader and creator routes follow the "knowledge_read_to_earn" feature flag;
// Super Admin management stays available while the feature is switched off.
Route::middleware('feature.flag:knowledge_hub')->group(function (): void {
    Route::post('/knowledge-hub/creators/{creator:uuid}/follow', [KnowledgeCreatorFollowController::class, 'toggle'])->middleware('throttle:30,1')->name('knowledge.creators.follow');
    Route::post('/knowledge-hub/writing-challenges/{challenge}/enter', [KnowledgeWritingChallengeController::class, 'enter'])->middleware('throttle:20,1')->name('knowledge.writing.enter');
    Route::delete('/knowledge-hub/writing-entries/{entry}', [KnowledgeWritingChallengeController::class, 'withdraw'])->name('knowledge.writing.withdraw');
    Route::post('/knowledge-hub/challenges/{challenge}/prizes/fund', [\App\Http\Controllers\CommunityChallengePrizeController::class, 'fund'])->middleware('throttle:10,1')->name('knowledge.challenges.prizes.fund');
    Route::post('/knowledge-hub/challenges/{challenge}/prizes/pay', [\App\Http\Controllers\CommunityChallengePrizeController::class, 'pay'])->middleware('throttle:10,1')->name('knowledge.challenges.prizes.pay');
    Route::get('/knowledge-hub/rewards', [KnowledgeReadEarnController::class, 'index'])->name('knowledge.rewards.index');
    Route::post('/knowledge-hub/challenge-popup/seen', function (\Illuminate\Http\Request $request, \App\Services\Knowledge\ChallengePopupService $popup) {
        $popup->markSeen($request);

        return response()->noContent();
    })->middleware('throttle:30,1')->name('knowledge.challenge-popup.seen');
    Route::get('/knowledge-hub/reading-challenges/{challenge}', [KnowledgeReadEarnController::class, 'challenge'])->name('knowledge.rewards.challenges.show');
    Route::post('/knowledge-hub/{publication}/read-earn', [KnowledgeReadEarnController::class, 'submitQuiz'])->middleware('throttle:20,1')->name('knowledge.rewards.quiz.submit');
    Route::post('/knowledge-hub/read-earn/questions/{question}/report', [KnowledgeReadEarnController::class, 'reportQuestion'])->middleware('throttle:20,1')->name('knowledge.rewards.quiz.report');
    Route::post('/knowledge-hub/{publication}/read-earn/questions/regenerate', [KnowledgeReadEarnController::class, 'regenerateQuestions'])->middleware('throttle:5,10')->name('knowledge.rewards.questions.regenerate');
    Route::get('/knowledge/manage/{publication}/read-earn-questions', [KnowledgeReadEarnController::class, 'editQuestions'])->name('knowledge.rewards.questions.edit');
    Route::put('/knowledge/manage/{publication}/read-earn-questions', [KnowledgeReadEarnController::class, 'updateQuestions'])->name('knowledge.rewards.questions.update');
});

Route::prefix('admin/knowledge-hub/read-earn')->name('admin.knowledge.read-earn.')->middleware('role:super_admin')->group(function (): void {
    Route::get('/', [ReadEarnManagementController::class, 'index'])->name('index');
    Route::put('/settings', [ReadEarnManagementController::class, 'updateSettings'])->name('settings');
    Route::post('/pools/{kind}/calculate', [ReadEarnManagementController::class, 'calculatePool'])->whereIn('kind', ['reader_pool', 'creator_pool'])->middleware('throttle:30,1')->name('pools.calculate');
    Route::post('/pay', [ReadEarnManagementController::class, 'pay'])->middleware('throttle:30,1')->name('pay');
    Route::patch('/payouts/{payout}', [ReadEarnManagementController::class, 'exclude'])->name('payouts.exclude');
    Route::patch('/attempts/{attempt}', [ReadEarnManagementController::class, 'reviewAttempt'])->name('attempts.review');
    Route::post('/writing-challenges', [WritingChallengeAdminController::class, 'store'])->name('writing.store');
    Route::get('/writing-challenges/{challenge}', [WritingChallengeAdminController::class, 'show'])->name('writing.show');
    Route::put('/writing-challenges/{challenge}', [WritingChallengeAdminController::class, 'update'])->name('writing.update');
    Route::post('/writing-challenges/{challenge}/publish-results', [WritingChallengeAdminController::class, 'publish'])->name('writing.publish');
    Route::post('/writing-challenges/{challenge}/announce', [WritingChallengeAdminController::class, 'announce'])->middleware('throttle:5,1')->name('writing.announce');
    Route::post('/writing-entries/{entry}/score', [WritingChallengeAdminController::class, 'score'])->name('writing.score');
    Route::patch('/writing-entries/{entry}', [WritingChallengeAdminController::class, 'disqualify'])->name('writing.disqualify');
    Route::post('/escrow/{fund}/settle', [ReadEarnManagementController::class, 'settleEscrow'])->name('escrow.settle');
    Route::post('/quiz-questions/{question}/review', [ReadEarnManagementController::class, 'reviewQuestion'])->name('questions.review');
    Route::post('/quiz-banks/{publication}/approve', [ReadEarnManagementController::class, 'approveQuizBank'])->name('questions.approve');
    Route::post('/reading-challenges', [ReadEarnManagementController::class, 'storeChallenge'])->name('challenges.store');
    Route::put('/reading-challenges/{challenge}', [ReadEarnManagementController::class, 'updateChallenge'])->name('challenges.update');
    Route::post('/reading-challenges/{challenge}/calculate', [ReadEarnManagementController::class, 'calculateReadingChallenge'])->name('challenges.calculate');
});
