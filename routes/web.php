<?php

use App\Http\Controllers\Admin\NotificationManagementController;
use App\Http\Controllers\Admin\MonetizationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMembershipController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EngagementModerationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionTaskController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LecturerAssignmentController;
use App\Http\Controllers\KnowledgeHubController;
use App\Http\Controllers\KnowledgePublicationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\CommerceController;
use App\Http\Controllers\KnowledgeEcosystemController;
use App\Http\Controllers\ResearchProjectController;
use App\Http\Controllers\ResearchConfigurationController;
use App\Http\Controllers\ResearchSectionController;
use App\Http\Controllers\ResearchWorkspaceController;
use App\Http\Controllers\ResearchSpecializedController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\ContextualAiController;
use App\Http\Controllers\AiPromptController;
use Illuminate\Support\Facades\Route;

// PWA manifest (dynamic)
Route::get('/manifest.webmanifest', [SettingsController::class, 'pwaManifest'])->middleware('feature.flag:pwa_enabled')->name('pwa.manifest');

// Public routes
Route::get('/information/{page}', [PublicPageController::class, 'show'])->whereIn('page', ['help','documentation','status','about','careers','api','source','changelog','security','terms','privacy','cookies','licenses'])->name('public.page');
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'))->name('home');
Route::get('/knowledge-hub', [KnowledgeHubController::class, 'index'])
    ->middleware('feature.flag:knowledge_hub')
    ->name('knowledge.index');
Route::middleware('feature.flag:knowledge_hub')->group(function () {
    Route::get('/knowledge-hub/search', [KnowledgeEcosystemController::class, 'search'])->name('knowledge.search');
    Route::get('/knowledge-hub/creators/{creator:uuid}', [KnowledgeEcosystemController::class, 'creator'])->name('knowledge.creator');
    Route::get('/knowledge-hub/leaderboard', [KnowledgeEcosystemController::class, 'leaderboard'])->name('knowledge.leaderboard');
    Route::get('/knowledge-hub/communities', [KnowledgeEcosystemController::class, 'communities'])->name('knowledge.communities.index');
    Route::get('/knowledge-hub/communities/{community}', [KnowledgeEcosystemController::class, 'community'])->name('knowledge.communities.show');
    Route::get('/knowledge-hub/learning-paths', [KnowledgeEcosystemController::class, 'learningPaths'])->name('knowledge.learning.index');
    Route::get('/knowledge-hub/learning-paths/{path}', [KnowledgeEcosystemController::class, 'learningPath'])->name('knowledge.learning.show');
    Route::get('/knowledge-hub/reading-lists', [KnowledgeEcosystemController::class, 'readingLists'])->name('knowledge.reading.index');
    Route::get('/knowledge-hub/reading-lists/{list}', [KnowledgeEcosystemController::class, 'readingList'])->name('knowledge.reading.show');
    Route::get('/knowledge-hub/events', [KnowledgeEcosystemController::class, 'events'])->name('knowledge.events.index');
    Route::get('/knowledge-hub/events/{event}', [KnowledgeEcosystemController::class, 'event'])->name('knowledge.events.show');
    Route::get('/knowledge-hub/challenges', [KnowledgeEcosystemController::class, 'challenges'])->name('knowledge.challenges.index');
    Route::get('/knowledge-hub/challenges/{challenge}', [KnowledgeEcosystemController::class, 'challenge'])->name('knowledge.challenges.show');
    Route::get('/knowledge-hub/certificates/verify/{code}', [KnowledgeEcosystemController::class, 'verifyCertificate'])->name('knowledge.certificates.verify');
});
Route::get('/media/{asset}/preview', [MediaController::class, 'preview'])->middleware('throttle:secure-downloads')->name('media.preview');
Route::get('/media/download/{token}', [MediaController::class, 'download'])->middleware('throttle:secure-downloads')->name('media.download');
Route::post('/commerce/webhook/{gateway}', [CommerceController::class, 'webhook'])
    ->middleware(['throttle:commerce-webhooks','webhook.verify'])
    ->name('commerce.webhook');
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [WebAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register'])->middleware('throttle:register')->name('store-register');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:verification')
        ->name('verification.send');
    Route::get('/two-factor-challenge', [TwoFactorAuthenticationController::class, 'showChallengeForm'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorAuthenticationController::class, 'confirm'])->middleware('throttle:two-factor')->name('two-factor.confirm');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'verified'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'show'])->name('show');
    Route::post('/back', [OnboardingController::class, 'back'])->name('back');
    Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    Route::get('/recommendations', [OnboardingController::class, 'recommendations'])->name('recommendations');
    Route::post('/{step}/skip', [OnboardingController::class, 'skip'])->name('skip');
    Route::post('/{step}', [OnboardingController::class, 'save'])->name('save');
});

// Payment gateway webhooks must be reachable without a signed-in browser session.
Route::post('/webhook/payment/{gateway}', [SubscriptionController::class, 'webhook'])
    ->middleware(['throttle:commerce-webhooks','webhook.verify'])
    ->name('webhook.payment');

// Protected application routes require verified identity and completed onboarding.
Route::middleware(['auth', 'verified', 'two-factor.authenticated', 'onboarding.complete'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/subscription', fn () => redirect()->route('commerce.wallet')->with('info', 'Subscriptions have been retired. AcadFlow now uses wallet, usage and entitlement based access.'))->name('subscription-retired');
    Route::get('/account/security', [AccountSecurityController::class, 'show'])->name('security.show');
    Route::post('/account/security/two-factor', [AccountSecurityController::class, 'begin'])->name('security.two-factor.begin');
    Route::post('/account/security/two-factor/confirm', [AccountSecurityController::class, 'confirm'])->name('security.two-factor.confirm');
    Route::post('/account/security/two-factor/recovery-codes', [AccountSecurityController::class, 'regenerateRecoveryCodes'])->name('security.two-factor.recovery');
    Route::delete('/account/security/two-factor', [AccountSecurityController::class, 'disable'])->name('security.two-factor.disable');

    // Notifications and personal notification preferences are shared by every authenticated role.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    // Keep static paths before dynamic {notification} routes so values such as
    // "read-all" and "settings" can never be interpreted as notification IDs.
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications', [NotificationController::class, 'clearAll'])->name('notifications.clear');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');
    Route::put('/notifications/settings', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Research Studio: shared formal research workspace and workflow
    Route::prefix('research-studio')->name('research.')->middleware('feature.flag:research_studio')->group(function () {
        Route::get('/', [ResearchProjectController::class, 'index'])->name('index');
        Route::get('/configuration', [ResearchConfigurationController::class, 'index'])->name('configuration.index');
        Route::post('/configuration/workflows', [ResearchConfigurationController::class, 'storeWorkflow'])->name('configuration.workflows.store');
        Route::put('/configuration/workflows/{workflow}', [ResearchConfigurationController::class, 'updateWorkflow'])->name('configuration.workflows.update');
        Route::post('/configuration/types', [ResearchConfigurationController::class, 'storeType'])->name('configuration.types.store');
        Route::put('/configuration/types/{type}', [ResearchConfigurationController::class, 'updateType'])->name('configuration.types.update');
        Route::get('/templates/manage', [ResearchWorkspaceController::class, 'templates'])->name('templates.index');
        Route::post('/templates/{type}', [ResearchWorkspaceController::class, 'storeTemplate'])->name('templates.store');
        Route::post('/templates/versions/{template}/activate', [ResearchWorkspaceController::class, 'activateTemplate'])->name('templates.activate');
        Route::get('/create', [ResearchProjectController::class, 'create'])->name('create');
        Route::post('/', [ResearchProjectController::class, 'store'])->name('store');
        Route::get('/{research}', [ResearchProjectController::class, 'show'])->name('show');
        Route::put('/{research}', [ResearchProjectController::class, 'update'])->name('update');
        Route::post('/{research}/transition', [ResearchProjectController::class, 'transition'])->name('transition');
        Route::post('/{research}/validate', [ResearchProjectController::class, 'validateProject'])->name('validate');
        Route::post('/{research}/publish', [ResearchProjectController::class, 'publish'])
            ->middleware('feature.flag:research_to_knowledge_hub')
            ->name('publish');
        Route::put('/{research}/sections/{section}', [ResearchSectionController::class, 'save'])->name('sections.save');
        Route::post('/{research}/sections/{section}/comments', [ResearchSectionController::class, 'comment'])->name('sections.comment');
        Route::post('/{research}/sections/{section}/approve', [ResearchSectionController::class, 'approve'])->name('sections.approve');
        Route::post('/{research}/sections/{section}/corrections', [ResearchSectionController::class, 'requestCorrection'])->name('sections.corrections');
        Route::get('/{research}/workspace', [ResearchWorkspaceController::class, 'controlCenter'])->name('workspace');
        Route::post('/{research}/meetings', [ResearchWorkspaceController::class, 'scheduleMeeting'])->name('meetings.store');
        Route::post('/{research}/meetings/{meeting}/complete', [ResearchWorkspaceController::class, 'completeMeeting'])->name('meetings.complete');
        Route::get('/{research}/meetings/{meeting}/calendar', [ResearchWorkspaceController::class, 'calendar'])->name('meetings.calendar');
        Route::post('/{research}/tasks', [ResearchWorkspaceController::class, 'storeTask'])->name('tasks.store');
        Route::patch('/{research}/tasks/{task}', [ResearchWorkspaceController::class, 'updateTask'])->name('tasks.update');
        Route::patch('/{research}/action-items/{item}', [ResearchWorkspaceController::class, 'completeActionItem'])->name('action-items.complete');
        Route::patch('/{research}/milestones/{milestone}', [ResearchWorkspaceController::class, 'updateMilestone'])->name('milestones.update');
        Route::post('/{research}/members', [ResearchWorkspaceController::class, 'syncMember'])->name('members.store');
        Route::get('/{research}/literature', [ResearchWorkspaceController::class, 'searchLiterature'])->name('literature.search');
        Route::post('/{research}/literature/{record}', [ResearchWorkspaceController::class, 'saveReference'])->name('literature.save');
        Route::put('/{research}/references/{reference}/note', [ResearchWorkspaceController::class, 'literatureNote'])->name('literature.note');
        Route::post('/{research}/archives', [ResearchWorkspaceController::class, 'seal'])->name('archives.seal');
        Route::get('/{research}/archives/{archive}', [ResearchWorkspaceController::class, 'downloadArchive'])->name('archives.download');
        Route::post('/{research}/amendments', [ResearchWorkspaceController::class, 'requestAmendment'])->name('amendments.store');
        Route::post('/{research}/amendments/{amendment}/review', [ResearchWorkspaceController::class, 'reviewAmendment'])->name('amendments.review');
        Route::get('/{research}/export/html', [ResearchWorkspaceController::class, 'exportHtml'])->name('export.html');
        Route::get('/{research}/export/pdf', [ResearchWorkspaceController::class, 'exportPdf'])->name('export.pdf');
        Route::post('/{research}/sections', [ResearchWorkspaceController::class, 'storeSection'])->name('sections.store');
        Route::patch('/{research}/sections/reorder', [ResearchWorkspaceController::class, 'reorderSections'])->name('sections.reorder');
        Route::delete('/{research}/sections/{section}', [ResearchWorkspaceController::class, 'destroySection'])->name('sections.destroy');
        Route::post('/{research}/sections/{section}/versions/{version}/restore', [ResearchWorkspaceController::class, 'restoreSectionVersion'])->name('sections.versions.restore');
        Route::patch('/{research}/corrections/{correction}/resolve', [ResearchWorkspaceController::class, 'resolveCorrection'])->name('corrections.resolve');
        Route::post('/{research}/datasets', [ResearchWorkspaceController::class, 'storeDataset'])->name('datasets.store');
        Route::delete('/{research}/datasets/{dataset}', [ResearchWorkspaceController::class, 'destroyDataset'])->name('datasets.destroy');
        Route::post('/{research}/specialized-links', [ResearchWorkspaceController::class, 'linkSpecializedWorkspace'])->name('specialized-links.store');
        Route::get('/{research}/specialized', [ResearchSpecializedController::class, 'show'])->name('specialized.show');
        Route::post('/{research}/siwes/placements', [ResearchSpecializedController::class, 'storePlacement'])->name('siwes.placements.store');
        Route::post('/{research}/siwes/placements/{placement}/logs', [ResearchSpecializedController::class, 'storeLog'])->name('siwes.logs.store');
        Route::post('/{research}/siwes/logs/{log}/review', [ResearchSpecializedController::class, 'reviewLog'])->name('siwes.logs.review');
        Route::post('/{research}/siwes/placements/{placement}/attendance', [ResearchSpecializedController::class, 'attendance'])->name('siwes.attendance.store');
        Route::post('/{research}/siwes/placements/{placement}/evaluations', [ResearchSpecializedController::class, 'evaluate'])->name('siwes.evaluations.store');
        Route::post('/{research}/seminar', [ResearchSpecializedController::class, 'storeSeminar'])->name('seminar.store');
        Route::post('/{research}/seminar/{seminar}/questions', [ResearchSpecializedController::class, 'askQuestion'])->name('seminar.questions.store');
        Route::post('/{research}/seminar/questions/{question}/answer', [ResearchSpecializedController::class, 'answerQuestion'])->name('seminar.questions.answer');
        Route::post('/{research}/seminar/{seminar}/panel/{panel}/score', [ResearchSpecializedController::class, 'scorePanel'])->name('seminar.panel.score');
        Route::post('/{research}/seminar/{seminar}/complete', [ResearchSpecializedController::class, 'completeSeminar'])->name('seminar.complete');
        Route::post('/templates/versions/{template}/retire', [ResearchWorkspaceController::class, 'retireTemplate'])->name('templates.retire');
    });

    // Knowledge Hub authoring, moderation and engagement
    Route::prefix('knowledge/manage')->name('knowledge.manage')->middleware('feature.flag:knowledge_hub')->group(function () {
        Route::get('/', [KnowledgePublicationController::class, 'manage'])->name('');
        Route::get('/create', [KnowledgePublicationController::class, 'create'])->name('.create');
        Route::post('/', [KnowledgePublicationController::class, 'store'])->name('.store');
        Route::get('/{publication}/edit', [KnowledgePublicationController::class, 'edit'])->name('.edit');
        Route::get('/{publication}', [KnowledgePublicationController::class, 'showManage'])->name('.show');
        Route::put('/{publication}', [KnowledgePublicationController::class, 'update'])->name('.update');
        Route::post('/{publication}/submit', [KnowledgePublicationController::class, 'submit'])->name('.submit');
        Route::post('/{publication}/moderate', [KnowledgePublicationController::class, 'moderate'])->name('.moderate');
        Route::post('/{publication}/bookmark', [KnowledgePublicationController::class, 'bookmark'])->name('.bookmark');
        Route::get('/{publication}/preview', [KnowledgePublicationController::class, 'preview'])->name('.preview');
        Route::post('/{publication}/duplicate', [KnowledgePublicationController::class, 'duplicate'])->name('.duplicate');
        Route::post('/{publication}/versions/{version}/restore', [KnowledgePublicationController::class, 'restoreVersion'])->name('.versions.restore');
        Route::post('/{publication}/placement', [KnowledgePublicationController::class, 'feature'])->name('.placement');
        Route::delete('/{publication}', [KnowledgePublicationController::class, 'destroy'])->name('.destroy');
        Route::delete('/archive/{publication}/permanent', [KnowledgePublicationController::class, 'forceDestroy'])->name('.force-destroy');
    });

    // Shared Knowledge Hub ecosystem, engagement, learning, events and creator tools.
    Route::middleware('feature.flag:knowledge_hub')->group(function () {
        Route::get('/knowledge-hub/creator/edit', [KnowledgeEcosystemController::class, 'editCreator'])->name('knowledge.creator.edit');
        Route::put('/knowledge-hub/creator', [KnowledgeEcosystemController::class, 'updateCreator'])->name('knowledge.creator.update');
        Route::post('/knowledge-hub/creator/orcid/sync', [KnowledgeEcosystemController::class, 'syncOrcid'])->name('knowledge.creator.orcid.sync');
        Route::post('/knowledge-hub/creator/verification', [KnowledgeEcosystemController::class, 'requestVerification'])->name('knowledge.verification.store');
        Route::post('/knowledge-hub/verifications/{verification}/review', [KnowledgeEcosystemController::class, 'reviewVerification'])->name('knowledge.verification.review');
        Route::get('/knowledge-hub/create/community', [KnowledgeEcosystemController::class, 'createCommunity'])->name('knowledge.communities.create');
        Route::post('/knowledge-hub/communities', [KnowledgeEcosystemController::class, 'storeCommunity'])->name('knowledge.communities.store');
        Route::get('/knowledge-hub/communities/{community}/edit', [KnowledgeEcosystemController::class, 'editCommunity'])->name('knowledge.communities.edit');
        Route::put('/knowledge-hub/communities/{community}', [KnowledgeEcosystemController::class, 'updateCommunity'])->name('knowledge.communities.update');
        Route::delete('/knowledge-hub/communities/{community}', [KnowledgeEcosystemController::class, 'archiveCommunity'])->name('knowledge.communities.destroy');
        Route::post('/knowledge-hub/communities/{community}/join', [KnowledgeEcosystemController::class, 'joinCommunity'])->name('knowledge.communities.join');
        Route::delete('/knowledge-hub/communities/{community}/leave', [KnowledgeEcosystemController::class, 'leaveCommunity'])->name('knowledge.communities.leave');
        Route::post('/knowledge-hub/communities/{community}/invitations', [KnowledgeEcosystemController::class, 'inviteCommunityMember'])->name('knowledge.communities.invitations.store');
        Route::post('/knowledge-hub/community-invitations/{invitation}/respond', [KnowledgeEcosystemController::class, 'respondCommunityInvitation'])->name('knowledge.communities.invitations.respond');
        Route::patch('/knowledge-hub/communities/{community}/members/{member:uuid}', [KnowledgeEcosystemController::class, 'moderateCommunityMember'])->name('knowledge.communities.members.update');
        Route::post('/knowledge-hub/communities/{community}/posts', [KnowledgeEcosystemController::class, 'postCommunity'])->name('knowledge.communities.posts.store');
        Route::post('/knowledge-hub/communities/{community}/reports', [KnowledgeEcosystemController::class, 'reportCommunity'])->name('knowledge.communities.report');
        Route::patch('/knowledge-hub/community-posts/{post}/moderate', [KnowledgeEcosystemController::class, 'moderateCommunityPost'])->name('knowledge.communities.posts.moderate');
        Route::post('/knowledge-hub/community-posts/{post}/comments', [KnowledgeEcosystemController::class, 'commentCommunityPost'])->name('knowledge.community-posts.comments');
        Route::post('/knowledge-hub/poll-options/{option}/vote', [KnowledgeEcosystemController::class, 'votePoll'])->name('knowledge.polls.vote');
        Route::post('/knowledge-hub/learning-paths', [KnowledgeEcosystemController::class, 'storeLearningPath'])->name('knowledge.learning.store');
        Route::post('/knowledge-hub/learning-paths/{path}/items', [KnowledgeEcosystemController::class, 'addLearningItem'])->name('knowledge.learning.items.store');
        Route::post('/knowledge-hub/learning-paths/{path}/enroll', [KnowledgeEcosystemController::class, 'enrollLearning'])->name('knowledge.learning.enroll');
        Route::patch('/knowledge-hub/learning-enrollments/{enrollment}/items/{item}', [KnowledgeEcosystemController::class, 'updateLearningProgress'])->name('knowledge.learning.progress');
        Route::post('/knowledge-hub/reading-lists', [KnowledgeEcosystemController::class, 'storeReadingList'])->name('knowledge.reading.store');
        Route::post('/knowledge-hub/reading-lists/{list}/items', [KnowledgeEcosystemController::class, 'addReadingItem'])->name('knowledge.reading.items.store');
        Route::patch('/knowledge-hub/reading-lists/{list}/items/{item}', [KnowledgeEcosystemController::class, 'updateReadingItem'])->name('knowledge.reading.items.update');
        Route::post('/knowledge-hub/reading-lists/{list}/members', [KnowledgeEcosystemController::class, 'syncReadingMember'])->name('knowledge.reading.members.store');
        Route::delete('/knowledge-hub/reading-lists/{list}/members/{member:uuid}', [KnowledgeEcosystemController::class, 'removeReadingMember'])->name('knowledge.reading.members.destroy');
        Route::get('/knowledge-hub/reading-lists/{list}/export', [KnowledgeEcosystemController::class, 'exportReadingList'])->name('knowledge.reading.export');
        Route::get('/knowledge-hub/create/event', [KnowledgeEcosystemController::class, 'createEventForm'])->name('knowledge.events.create');
        Route::post('/knowledge-hub/events', [KnowledgeEcosystemController::class, 'storeEvent'])->name('knowledge.events.store');
        Route::get('/knowledge-hub/events/{event}/edit', [KnowledgeEcosystemController::class, 'editEvent'])->name('knowledge.events.edit');
        Route::put('/knowledge-hub/events/{event}', [KnowledgeEcosystemController::class, 'updateEvent'])->name('knowledge.events.update');
        Route::patch('/knowledge-hub/events/{event}/status', [KnowledgeEcosystemController::class, 'changeEventStatus'])->name('knowledge.events.status');
        Route::delete('/knowledge-hub/events/{event}', [KnowledgeEcosystemController::class, 'deleteEvent'])->name('knowledge.events.destroy');
        Route::post('/knowledge-hub/events/{event}/register', [KnowledgeEcosystemController::class, 'registerEvent'])->name('knowledge.events.register');
        Route::delete('/knowledge-hub/events/{event}/register', [KnowledgeEcosystemController::class, 'unregisterEvent'])->name('knowledge.events.unregister');
        Route::post('/knowledge-hub/events/{event}/attendance/{attendee:uuid}', [KnowledgeEcosystemController::class, 'attendEvent'])->name('knowledge.events.attendance');
        Route::patch('/knowledge-hub/events/{event}/registrations/{registration}', [KnowledgeEcosystemController::class, 'reviewEventRegistration'])->name('knowledge.events.registrations.review');
        Route::post('/knowledge-hub/events/{event}/invitations', [KnowledgeEcosystemController::class, 'inviteEventAttendee'])->name('knowledge.events.invitations.store');
        Route::post('/knowledge-hub/event-invitations/{invitation}/respond', [KnowledgeEcosystemController::class, 'respondEventInvitation'])->name('knowledge.events.invitations.respond');
        Route::post('/knowledge-hub/events/{event}/comments', [KnowledgeEcosystemController::class, 'commentEvent'])->name('knowledge.events.comments.store');
        Route::post('/knowledge-hub/events/{event}/reports', [KnowledgeEcosystemController::class, 'reportEvent'])->name('knowledge.events.report');
        Route::get('/knowledge-hub/create/challenge', [KnowledgeEcosystemController::class, 'createChallengeForm'])->name('knowledge.challenges.create');
        Route::post('/knowledge-hub/challenges', [KnowledgeEcosystemController::class, 'storeChallenge'])->name('knowledge.challenges.store');
        Route::get('/knowledge-hub/challenges/{challenge}/edit', [KnowledgeEcosystemController::class, 'editChallenge'])->name('knowledge.challenges.edit');
        Route::put('/knowledge-hub/challenges/{challenge}', [KnowledgeEcosystemController::class, 'updateChallenge'])->name('knowledge.challenges.update');
        Route::patch('/knowledge-hub/challenges/{challenge}/status', [KnowledgeEcosystemController::class, 'changeChallengeStatus'])->name('knowledge.challenges.status');
        Route::delete('/knowledge-hub/challenges/{challenge}', [KnowledgeEcosystemController::class, 'deleteChallenge'])->name('knowledge.challenges.destroy');
        Route::post('/knowledge-hub/challenges/{challenge}/entries', [KnowledgeEcosystemController::class, 'submitChallenge'])->name('knowledge.challenges.entries.store');
        Route::post('/knowledge-hub/challenges/{challenge}/results', [KnowledgeEcosystemController::class, 'publishChallengeResults'])->name('knowledge.challenges.results.publish');
        Route::post('/knowledge-hub/challenge-entries/{entry}/judge', [KnowledgeEcosystemController::class, 'judgeChallenge'])->name('knowledge.challenges.judge');
        Route::post('/knowledge-hub/challenge-entries/{entry}/vote', [KnowledgeEcosystemController::class, 'voteChallenge'])->middleware('throttle:challenge-votes')->name('knowledge.challenges.vote');
        Route::post('/knowledge-hub/challenges/{challenge}/comments', [KnowledgeEcosystemController::class, 'commentChallenge'])->name('knowledge.challenges.comments.store');
        Route::post('/knowledge-hub/challenges/{challenge}/reports', [KnowledgeEcosystemController::class, 'reportChallenge'])->name('knowledge.challenges.report');
        Route::get('/knowledge-hub/certificates/{certificate}/download', [KnowledgeEcosystemController::class, 'certificate'])->name('knowledge.certificates.download');
        Route::get('/knowledge-hub/{publication}/citations', [KnowledgeEcosystemController::class, 'citationGraph'])->name('knowledge.citations.graph');
        Route::post('/knowledge-hub/{publication}/citations/rebuild', [KnowledgeEcosystemController::class, 'rebuildCitations'])->name('knowledge.citations.rebuild');
        Route::post('/knowledge-hub/{publication}/citations/external', [KnowledgeEcosystemController::class, 'syncExternalCitations'])->name('knowledge.citations.external');
        Route::get('/knowledge-hub/citation-rankings', [KnowledgeEcosystemController::class, 'citationRankings'])->name('knowledge.citations.rankings');
        Route::get('/knowledge-hub/companion/{session}', [KnowledgeEcosystemController::class, 'companion'])->name('knowledge.companion.show');
        Route::post('/knowledge-hub/companion/{session}/feedback', [KnowledgeEcosystemController::class, 'companionFeedback'])->middleware('throttle:30,1')->name('knowledge.companion.feedback');
        Route::post('/knowledge-hub/{publication}/companion', [KnowledgeEcosystemController::class, 'askCompanion'])->middleware('throttle:ai')->name('knowledge.companion.ask');
        Route::post('/knowledge-hub/{publication}/comments', [KnowledgeEcosystemController::class, 'commentPublication'])->name('knowledge.comments.store');
        Route::post('/knowledge-hub/{publication}/reactions', [KnowledgeEcosystemController::class, 'reactPublication'])->name('knowledge.reactions');
        Route::post('/knowledge-hub/{publication}/reports', [KnowledgeEcosystemController::class, 'reportPublication'])->name('knowledge.reports');
        Route::post('/knowledge-hub/{publication}/shares', [KnowledgeEcosystemController::class, 'sharePublication'])->name('knowledge.shares');
        Route::post('/knowledge-hub/{publication}/follow', [KnowledgeEcosystemController::class, 'followPublication'])->name('knowledge.follow');
    });

    // Human moderation for the shared engagement service.
    Route::get('/moderation/engagement', [EngagementModerationController::class, 'index'])->name('moderation.engagement.index');
    Route::post('/moderation/engagement/{report}/review', [EngagementModerationController::class, 'review'])->name('moderation.engagement.review');

    // Shared media security and secure delivery.
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/media/{asset}/token', [MediaController::class, 'token'])->middleware('throttle:secure-downloads')->name('media.token');

    // Knowledge marketplace, entitlements, refunds and creator payouts.
    Route::get('/commerce/orders', [CommerceController::class, 'orders'])->name('commerce.orders');
    Route::post('/commerce/publications/{publication}/purchase', [CommerceController::class, 'purchase'])->name('commerce.purchase');
    Route::post('/commerce/learning-paths/{path}/purchase', [CommerceController::class, 'purchaseLearningPath'])->name('commerce.learning.purchase');
    Route::get('/commerce/payment/callback/{transaction}', [CommerceController::class, 'callback'])->name('commerce.callback');
    Route::get('/commerce/wallet', [CommerceController::class, 'wallet'])->name('commerce.wallet');
    Route::post('/commerce/wallet/fund', [CommerceController::class, 'fundWallet'])->middleware('throttle:payments')->name('commerce.wallet.fund');
    Route::get('/commerce/wallet/funding/callback/{transaction}', [CommerceController::class, 'walletFundingCallback'])->name('commerce.wallet.callback');
    Route::post('/commerce/payout-accounts', [CommerceController::class, 'storePayout'])->name('commerce.payout-accounts.store');
    Route::post('/commerce/payout-accounts/{account}/verify', [CommerceController::class, 'verifyPayout'])->name('commerce.payout-accounts.verify');
    Route::post('/commerce/withdrawals', [CommerceController::class, 'requestWithdrawal'])->name('commerce.withdrawals.store');
    Route::post('/commerce/withdrawals/{withdrawal}/process', [CommerceController::class, 'processWithdrawal'])->name('commerce.withdrawals.process');
    Route::post('/commerce/orders/{order}/refunds', [CommerceController::class, 'requestRefund'])->name('commerce.refunds.store');
    Route::post('/commerce/refunds/{refund}/process', [CommerceController::class, 'processRefund'])->name('commerce.refunds.process');
    Route::post('/commerce/refunds/{refund}/reconcile', [CommerceController::class, 'reconcileRefund'])->name('commerce.refunds.reconcile');

    // Admin dashboard (redirects to role-specific admin page)
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/onboarding', [OnboardingController::class, 'adminIndex'])
        ->middleware('role:department_admin,university_admin,super_admin')
        ->name('admin.onboarding.index');
    Route::redirect('/admin/dashboard', '/admin')->name('admin.dashboard.legacy');

    // Collaboration groups are available to institutional and independent users.
    Route::resource('groups', GroupController::class);
    Route::post('groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::delete('groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');
    Route::post('groups/{group}/invitations', [GroupController::class, 'invite'])->name('groups.invitations.store');
    Route::post('group-invitations/{invitation}/respond', [GroupController::class, 'respondInvitation'])->name('groups.invitations.respond');
    Route::post('group-join-requests/{joinRequest}/review', [GroupController::class, 'reviewJoinRequest'])->name('groups.join-requests.review');
    Route::delete('groups/{group}/members/{member:uuid}', [GroupController::class, 'removeMember'])->name('groups.members.destroy');
    Route::post('groups/{group}/transfer-leadership', [GroupController::class, 'transferLeadership'])->name('groups.transfer-leadership');
    Route::post('groups/{group}/tasks', [GroupController::class, 'storeTask'])->name('groups.tasks.store');
    Route::patch('group-tasks/{task}', [GroupController::class, 'updateTask'])->name('groups.tasks.update');
    Route::post('groups/{group}/resources', [GroupController::class, 'storeResource'])->name('groups.resources.store');
    Route::post('groups/{group}/comments', [GroupController::class, 'comment'])->name('groups.comments.store');
    Route::post('groups/{group}/reports', [GroupController::class, 'report'])->name('groups.report');

    // Student routes
    Route::middleware('role:student')->group(function () {
        // Course list for students
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');

        // Course assignments list
        Route::get('/courses/{course}/assignments/stdview', [SubmissionTaskController::class, 'availableForStudent'])->name('courses.assignments');

        // Course join via link/code
        Route::get('/courses/join/{uuid}', [CourseController::class, 'joinViaLink'])->name('courses.join.link');
        Route::post('/courses/join/{uuid}', [CourseController::class, 'processJoinLink'])->name('courses.join.link.process');

        // Compatibility callback for payments initiated before subscription retirement.
        // No new subscription checkout/upgrade routes are exposed.
        Route::get('/subscription/payment/callback/{transaction}', [SubscriptionController::class, 'paymentCallback'])->name('subscription.payment.callback');

        // Student institutional billing
        Route::get('/billing/invoices', [BillingController::class, 'myInvoices'])
            ->name('billing.my');
        Route::get('/billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])
            ->name('billing.show');
        Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])
            ->name('billing.pay');

        // Student attendance routes
        Route::get('/attendance', [AttendanceController::class, 'myAttendance'])->name('attendance.my');
        Route::get('/attendance/records', [AttendanceController::class, 'myAttendanceRecords'])->name('attendance.records');
        Route::get('/attendance/records/export', [AttendanceController::class, 'exportMyAttendanceRecords'])->name('attendance.records.export');
        Route::post('/attendance/checkin', [AttendanceController::class, 'checkIn'])->name('attendance.checkin');
        Route::get('/attendance/active', [AttendanceController::class, 'activeSession'])->name('attendance.active');

        // Student submission routes
        Route::get('/submissions/dashboard', [SubmissionController::class, 'dashboard'])->name('submissions.dashboard');
        Route::post('submissions/{submission}/upload', [SubmissionController::class, 'upload'])
          ->name('submissions.upload');

        Route::resource('submissions', SubmissionController::class)->except(['show']);

        Route::post('submissions/{submission}/replace-files', [SubmissionController::class, 'replaceFiles'])
          ->name('submissions.replace-files');

        Route::get('submission-versions/{version}/download', [SubmissionController::class, 'download'])
           ->name('submission-versions.download');

        Route::post('submissions/{submission}/submit', [SubmissionController::class, 'submit'])
        ->name('submissions.submit');

        Route::get('/courses/{course}/assignments/{task}/showForStudent', [SubmissionTaskController::class, 'showForStudent'])
            ->name('submission-tasks.student.show');
    });

    // Attendance session details are shared; the policy enforces student enrollment and staff scope.
    Route::get('/attendance/session/{session}', [AttendanceController::class, 'showSession'])->name('attendance.session');

    // Submission show (accessible by all authenticated users with proper policy)
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');

    // Shared Student & Lecturer routes (materials, discussions, assignments view)
    Route::middleware('role:student,lecturer')->group(function () {
        // Course details
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

        // Course Materials
        Route::get('/courses/{course}/materials', [CourseMaterialController::class, 'index'])->name('materials.index');
        Route::get('/materials', [CourseMaterialController::class, 'all'])->name('materials.all');
        Route::get('/courses/{course}/materials/{material}', [CourseMaterialController::class, 'show'])->name('materials.show');
        Route::get('/courses/{course}/materials/{material}/download', [CourseMaterialController::class, 'download'])->name('materials.download');

        // Discussions
        Route::get('/courses/{course}/discussions', [DiscussionController::class, 'index'])->name('discussions.index');
        Route::get('/courses/{course}/discussions/create', [DiscussionController::class, 'create'])->name('discussions.create');
        Route::post('/courses/{course}/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
        Route::get('/courses/{course}/discussions/{discussion}', [DiscussionController::class, 'show'])->name('discussions.show');
        Route::post('/courses/{course}/discussions/{discussion}/reply', [DiscussionController::class, 'addReply'])->name('discussions.reply');
        Route::post('/courses/{course}/discussions/{discussion}/replies/{comment}/accept', [DiscussionController::class, 'acceptReply'])->name('discussions.replies.accept');
        Route::post('/courses/{course}/discussions/{discussion}/replies/{comment}/react', [DiscussionController::class, 'reactReply'])->name('discussions.replies.react');
        Route::post('/courses/{course}/discussions/{discussion}/report', [DiscussionController::class, 'report'])->name('discussions.report');
        Route::post('/courses/{course}/discussions/{discussion}/subscribe', [DiscussionController::class, 'subscribe'])->name('discussions.subscribe');
        Route::get('/courses/{course}/discussions/{discussion}/edit', [DiscussionController::class, 'edit'])->name('discussions.edit');
        Route::put('/courses/{course}/discussions/{discussion}', [DiscussionController::class, 'update'])->name('discussions.update');
        Route::post('/courses/{course}/discussions/{discussion}/close', [DiscussionController::class, 'close'])->name('discussions.close');
        Route::post('/courses/{course}/discussions/{discussion}/pin', [DiscussionController::class, 'pin'])->name('discussions.pin');

        // Materials export / documents / submissions
        Route::get('/courses/{course}/materials/export-pdf', [CourseMaterialController::class, 'exportPdf'])->name('materials.export-pdf');
        Route::get('/my/transcript', [ExportController::class, 'transcript'])->name('export.transcript');
        Route::get('/submissions/{submission}/grade-report', [ExportController::class, 'gradeReport'])->name('export.grade-report');
        Route::get('/submissions/{submission}/view/{version?}', [SubmissionController::class, 'viewFile'])->name('submissions.view');
        Route::post('/submissions/{submission}/generate-document', [SubmissionController::class, 'generateDocument'])->name('submissions.generate-document');
        Route::get('/my/documents', [ExportController::class, 'myDocuments'])->name('documents.index');
        Route::get('/documents/{generatedDocument}/download', [ExportController::class, 'downloadDocument'])->name('documents.download');
        Route::get('/documents/batch-transcripts', [ExportController::class, 'batchTranscripts'])->name('documents.batch-transcripts');
        Route::get('/documents/batch-grade-reports', [ExportController::class, 'batchGradeReports'])->name('documents.batch-grade-reports');
        Route::get('/submissions/{submission}/defense/schedule', [SubmissionController::class, 'scheduleDefense'])->name('defenses.schedule');
        Route::post('/submissions/{submission}/defense', [SubmissionController::class, 'storeDefense'])->name('defenses.store');
    });

    Route::get('/courses/invitations/{token}/accept', [CourseMembershipController::class, 'acceptInvitation'])
        ->middleware('throttle:20,1')->name('courses.invitation.accept');

    // AI Academic Assistant routes (shared student & lecturer)
    Route::middleware('role:student,lecturer')->group(function () {
        Route::get('/submissions/{submission}/ai-analysis', [AiController::class, 'submissionAnalysis'])
            ->name('ai.submission.analysis');
        Route::post('/submissions/{submission}/reanalyze', [AiController::class, 'reanalyze'])
            ->name('ai.submission.reanalyze');
    });

    Route::get('/ai-assistant', [AiController::class, 'assistant'])->name('ai.assistant');
    Route::post('/ai-assistant/ask', [AiController::class, 'askAssistant'])->middleware('throttle:ai')->name('ai.assistant.ask');

    Route::post('/ai/writing', [AiController::class, 'writingAssistant'])->middleware('throttle:ai')->name('ai.writing');
    Route::post('/ai/citation', [AiController::class, 'citationAssistant'])->middleware('throttle:ai')->name('ai.citation');

    // Specialized contextual assistants. Each endpoint performs model-level
    // authorization and delegates provider/model selection to the central AI
    // runtime. Module availability and AI Assistant availability are both
    // enforced before a provider request can be initialized.
    Route::prefix('ai/context')->name('ai.context.')->middleware('feature.flag:ai_assistant')->group(function () {
        Route::post('/research/{research}', [ContextualAiController::class, 'research'])
            ->middleware(['feature.flag:research_studio', 'ai.feature:research_assistant', 'throttle:ai'])->name('research');
        Route::post('/assignments/{course}/{task}', [ContextualAiController::class, 'assignment'])
            ->middleware(['feature.flag:assignments', 'ai.feature:assignment_assistant', 'throttle:ai'])->name('assignment');
        Route::post('/siwes/{research}', [ContextualAiController::class, 'siwes'])
            ->middleware(['feature.flag:siwes_module', 'ai.feature:siwes_assistant', 'throttle:ai'])->name('siwes');
        Route::post('/projects/{submission}', [ContextualAiController::class, 'project'])
            ->middleware(['feature.flag:submissions', 'feature.flag:final_year_project', 'ai.feature:project_assistant', 'throttle:ai'])->name('project');
        Route::post('/materials/{course}/{material}', [ContextualAiController::class, 'material'])
            ->middleware(['feature.flag:course_materials', 'ai.feature:material_assistant', 'throttle:ai'])->name('material');
        Route::post('/discussions/{course}/{discussion}', [ContextualAiController::class, 'discussion'])
            ->middleware(['feature.flag:course_discussions', 'ai.feature:discussion_assistant', 'throttle:ai'])->name('discussion');
    });

    // Admin routes (department_admin, university_admin, super_admin)
    Route::middleware('role:department_admin,university_admin,super_admin')->group(function () {
        Route::get('/admin/ai/settings', [AiController::class, 'settings'])->name('ai.settings');
        Route::post('/admin/ai/settings', [AiController::class, 'updateSettings'])->name('ai.settings.update');
        Route::post('/admin/ai/providers/{provider}/test', [AiController::class, 'testProvider'])->middleware('throttle:10,1')->name('ai.providers.test');
        Route::post('/admin/ai/providers/{provider}/discover-models', [AiController::class, 'discoverProviderModels'])->middleware('throttle:5,1')->name('ai.providers.discover-models');
        Route::get('/admin/ai/diagnostics', [AiController::class, 'diagnostics'])->name('ai.diagnostics');
        Route::get('/admin/ai/analytics', [AiController::class, 'analytics'])->name('ai.analytics');
        Route::post('/admin/ai/prompts', [AiPromptController::class, 'store'])->name('ai.prompts.store');
        Route::post('/admin/ai/prompts/{prompt}/activate', [AiPromptController::class, 'activate'])->name('ai.prompts.activate');
    });

    // Lecturer routes
    Route::middleware('role:lecturer')->group(function () {
        Route::get('/lecturer/courses', [CourseController::class, 'myCourses'])->name('lecturer.courses');
        Route::post('/lecturer/courses/{course}/self-assign', [CourseMembershipController::class, 'selfAssign'])
            ->middleware('throttle:20,1')->name('lecturer.courses.self-assign');
        Route::post('/lecturer/courses/{course}/students/enroll', [CourseMembershipController::class, 'enrollStudent'])
            ->middleware('throttle:30,1')->name('lecturer.courses.students.enroll');
        Route::post('/lecturer/courses/{course}/students/invite', [CourseMembershipController::class, 'inviteStudent'])
            ->middleware('throttle:20,1')->name('lecturer.courses.students.invite');

        // Attendance session management
        Route::get('/lecturer/attendance', [AttendanceController::class, 'lecturerSessions'])->name('attendance.lecturer');
        Route::post('/attendance/start', [AttendanceController::class, 'startSession'])->name('attendance.start');
        Route::post('/attendance/session/{session}/close', [AttendanceController::class, 'closeSession'])->name('attendance.close');
        Route::post('/attendance/session/{session}/qr-refresh', [AttendanceController::class, 'refreshQr'])->name('attendance.qr.refresh');

        // Course Materials Management
        Route::get('/lecturer/courses/{course}/materials/lectview', [CourseMaterialController::class, 'index'])->name('lecturer.materials.index');
        Route::get('/lecturer/courses/{course}/materials', [CourseMaterialController::class, 'create'])->name('lecturer.materials.create');
        Route::post('/lecturer/courses/{course}/materials/store', [CourseMaterialController::class, 'store'])->name('lecturer.materials.store');
        Route::get('/lecturer/courses/{course}/materials/{material}/edit', [CourseMaterialController::class, 'edit'])->name('lecturer.materials.edit');
        Route::put('/lecturer/courses/{course}/materials/{material}', [CourseMaterialController::class, 'update'])->name('lecturer.materials.update');
        Route::delete('/lecturer/courses/{course}/materials/{material}', [CourseMaterialController::class, 'destroy'])->name('lecturer.materials.destroy');

        // Assignment management
        Route::get('/courses/{course}/assignments', [SubmissionTaskController::class, 'indexForCourse'])
            ->name('submission-tasks.manage.index');

        Route::get('/courses/{course}/assignments/create', [SubmissionTaskController::class, 'create'])
            ->name('submission-tasks.create');

        Route::post('/courses/{course}/assignments/store', [SubmissionTaskController::class, 'store'])
            ->name('submission-tasks.store');

        Route::get('/lecturer/courses/{course}/assignments/{task}/showForLecturer', [SubmissionTaskController::class, 'showForLecturer'])
            ->name('submission-tasks.lecturer.show');

        Route::get('/courses/{course}/assignments/{task}/edit', [SubmissionTaskController::class, 'edit'])
            ->name('submission-tasks.edit');

        Route::put('/courses/{course}/assignments/{task}', [SubmissionTaskController::class, 'update'])
            ->name('submission-tasks.update');

        Route::post('/courses/{course}/assignments/{task}/publish', [SubmissionTaskController::class, 'publish'])
            ->name('submission-tasks.publish');

        Route::post('/courses/{course}/assignments/{task}/close', [SubmissionTaskController::class, 'close'])
            ->name('submission-tasks.close');

        Route::delete('/courses/{course}/assignments/{task}', [SubmissionTaskController::class, 'destroy'])
            ->name('submission-tasks.destroy');

        Route::post('/courses/{course}/assignments/{task}/attachments', [SubmissionTaskController::class, 'uploadAttachment'])
            ->name('submission-tasks.attachment.upload');

        Route::delete('/courses/{course}/assignments/{task}/attachments/{submission_task_attachment}', [SubmissionTaskController::class, 'deleteAttachment'])
            ->name('submission-tasks.attachment.delete');

        Route::get('/assignments/attachments/{submission_task_attachment}/download', [SubmissionTaskController::class, 'downloadAttachment'])
            ->name('submission-tasks.attachment.download');

        Route::get('/courses/{course}/assignments/{task}/extensions', [SubmissionTaskController::class, 'extensions'])
            ->name('submission-tasks.extensions');

        Route::post('/courses/{course}/assignments/{task}/extensions', [SubmissionTaskController::class, 'grantExtension'])
            ->name('submission-tasks.extension.grant');

        Route::delete('/courses/{course}/assignments/{task}/extensions/{submission_extension}', [SubmissionTaskController::class, 'revokeExtension'])
            ->name('submission-tasks.extension.revoke');

        // Lecturer submission review
        Route::get('lecturer/submissions', [SubmissionController::class, 'lecturerIndex'])->name('submissions.lecturer-index');
        Route::get('lecturer/submissions/{submission}/review', [SubmissionController::class, 'review'])->name('submissions.review');
        Route::post('lecturer/submissions/{submission}/grade', [SubmissionController::class, 'grade'])->name('submissions.grade');
        Route::post('lecturer/submissions/{submission}/comment', [SubmissionController::class, 'comment'])->name('submissions.comment');
        Route::post('lecturer/submissions/{submission}/approve', [SubmissionController::class, 'approve'])->name('submissions.approve');
        Route::post('lecturer/submissions/{submission}/reject', [SubmissionController::class, 'reject'])->name('submissions.reject');
        Route::post('lecturer/submissions/{submission}/request-correction', [SubmissionController::class, 'requestCorrection'])->name('submissions.request-correction');
        Route::get('lecturer/submissions/{submission}/compare', [SubmissionController::class, 'compare'])->name('submissions.compare');

        // Lecturer AI layout preferences
        Route::get('/lecturer/ai/layout-preferences', [AiController::class, 'lecturerLayoutPreferences'])->name('ai.lecturer.layout.preferences');
        Route::post('/lecturer/ai/layout-preferences', [AiController::class, 'saveLecturerLayoutPreferences'])->name('ai.lecturer.layout.preferences.update');
    });

    // Admin notification management (available to department_admin, university_admin, super_admin)
    Route::middleware('role:department_admin,university_admin,super_admin')->group(function () {
        Route::get('/admin/notifications', [NotificationManagementController::class, 'index'])->name('admin.notifications.index');
        Route::put('/admin/notifications/channels', [NotificationManagementController::class, 'updateChannels'])->name('admin.notifications.update-channels');
        Route::get('/admin/notifications/announce', [NotificationManagementController::class, 'announce'])->name('admin.notifications.announce');
        Route::post('/admin/notifications/announce', [NotificationManagementController::class, 'sendAnnouncement'])->name('admin.notifications.send-announcement');
        Route::post('/admin/notifications/retry-failed', [NotificationManagementController::class, 'retryFailed'])->name('admin.notifications.retry-failed');
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users/invite', [AdminController::class, 'inviteUser'])->name('admin.users.invite');
        Route::get('/admin/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::get('/admin/reports', [AdminController::class, 'reports'])->name('admin.reports');
        Route::get('/admin/reports/export/{type}', [AdminController::class, 'exportReports'])
            ->whereIn('type', ['submissions', 'attendance', 'billing'])
            ->name('admin.reports.export');

        // Shared institutional billing management with controller-level tenant scoping.
        Route::get('/admin/billing/invoices', [BillingController::class, 'adminIndex'])
            ->name('admin.billing');
        Route::post('/admin/billing/invoices/{invoice}/verify', [BillingController::class, 'verify'])
            ->name('billing.verify');
        Route::post('/admin/billing/invoices/{invoice}/waive', [BillingController::class, 'waive'])
            ->name('billing.waive');
    });

    // Department Admin routes
    Route::middleware('role:department_admin')->group(function () {
        // Department management
        Route::get('/admin/department', [AdminController::class, 'department'])->name('admin.department');
        Route::get('/admin/courses', [CourseController::class, 'adminIndex'])->name('admin.courses');
        Route::post('/admin/courses', [CourseController::class, 'store'])->name('admin.courses.store');
        Route::get('/admin/courses/{course}', [CourseController::class, 'adminShow'])->name('admin.courses.show');
        Route::put('/admin/courses/{course}', [CourseController::class, 'update'])->name('admin.courses.update');

        // Lecturer assignment management
        Route::post('/admin/courses/{course}/lecturers', [LecturerAssignmentController::class, 'store'])
            ->name('admin.courses.lecturers.store');
        Route::delete('/admin/courses/{course}/lecturers/{assignment}', [LecturerAssignmentController::class, 'destroy'])
            ->name('admin.courses.lecturers.destroy');
        Route::put('/admin/courses/{course}/lecturers/{assignment}/coordinator', [LecturerAssignmentController::class, 'updateCoordinator'])
            ->name('admin.courses.lecturers.coordinator');
    });

    // University Admin routes
    Route::middleware('role:university_admin,super_admin')->group(function () {
        // Institution-aware settings. Platform-only keys remain protected in the controller.
        Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings');
        Route::post('/admin/settings', [SettingsController::class, 'updateMultiple'])->name('admin.settings.update');
        Route::put('/admin/settings/{key}', [SettingsController::class, 'update'])->name('admin.settings.update-key');

        Route::get('/admin/monetization', [MonetizationController::class, 'index'])->name('admin.monetization');
        Route::put('/admin/monetization', [MonetizationController::class, 'update'])->name('admin.monetization.update');
        Route::post('/admin/monetization/pricing-rules', [MonetizationController::class, 'storePricingRule'])->name('admin.monetization.pricing-rules.store');
        Route::post('/admin/monetization/commercial-accounts', [MonetizationController::class, 'storeCommercialAccount'])->name('admin.monetization.commercial-accounts.store');

        Route::get('/admin/faculties', [AdminController::class, 'faculties'])->name('admin.faculties');
        Route::post('/admin/faculties', [AdminController::class, 'createFaculty'])->name('admin.faculties.create');
        Route::get('/admin/faculties/{faculty}/edit', [AdminController::class, 'editFaculty'])->name('admin.faculties.edit');
        Route::put('/admin/faculties/{faculty}', [AdminController::class, 'updateFaculty'])->name('admin.faculties.update');

        // Generate invoices for a semester
        Route::post('/admin/billing/semesters/{semester}/generate-invoices', [BillingController::class, 'generateInvoices'])
            ->name('billing.generate-invoices');
    });

    // Super Admin routes
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/universities', [AdminController::class, 'universities'])->name('admin.universities');
        Route::post('/admin/universities', [AdminController::class, 'createUniversity'])->name('admin.universities.create');
        Route::get('/admin/universities/{university}/edit', [AdminController::class, 'editUniversity'])->name('admin.universities.edit');
        Route::put('/admin/universities/{university}', [AdminController::class, 'updateUniversity'])->name('admin.universities.update');
        Route::get('/admin/settings/features', [SettingsController::class, 'features'])->name('admin.settings.features');
        Route::put('/admin/settings/features/{feature}', [SettingsController::class, 'updateFeature'])->name('admin.settings.features.update');
        // Legacy compatibility route; it delegates to the same centralized service.
        Route::post('/admin/settings/toggle-flag/{featureFlag}', [SettingsController::class, 'toggleFeatureFlag'])->name('admin.settings.toggle-flag');
        Route::get('/admin/settings/permissions', [SettingsController::class, 'permissions'])->name('admin.settings.permissions');
        Route::get('/admin/settings/audit-logs', [SettingsController::class, 'auditLogs'])->name('admin.settings.audit-logs');

        // Student import
        Route::get('/admin/students/import', [StudentImportController::class, 'showImportForm'])->name('admin.students.import');
        Route::post('/admin/students/import', [StudentImportController::class, 'import'])->name('admin.students.import.post');

        // Payment gateway setting route
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])
                ->name('payment-gateways.index');

            Route::get('/payment-gateways/create', [PaymentGatewayController::class, 'create'])
                ->name('payment-gateways.create');

            Route::post('/payment-gateways', [PaymentGatewayController::class, 'store'])
                ->name('payment-gateways.store');

            Route::get('/payment-gateways/{paymentGateway}/edit', [PaymentGatewayController::class, 'edit'])
                ->name('payment-gateways.edit');

            Route::put('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])
                ->name('payment-gateways.update');

            Route::delete('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])
                ->name('payment-gateways.destroy');

            Route::post('/payment-gateways/{paymentGateway}/test-connection', [PaymentGatewayController::class, 'testConnection'])
                ->name('payment-gateways.test-connection');
        });
    });
});

// Public publication route is intentionally last so it cannot shadow authoring routes.
Route::get('/knowledge-hub/{publication}', [KnowledgeHubController::class, 'show'])->name('knowledge.show');
