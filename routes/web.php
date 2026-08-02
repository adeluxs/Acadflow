<?php

use App\Http\Controllers\Admin\NotificationManagementController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionTaskController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LecturerAssignmentController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\AiController;
use Illuminate\Support\Facades\Route;

// PWA manifest (dynamic)
Route::get('/manifest.webmanifest', [SettingsController::class, 'pwaManifest'])->name('pwa.manifest');

// Public routes
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'))->name('home');
Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);
Route::get('/register', [WebAuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])->name('store-register');
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
Route::get('/two-factor-challenge', [TwoFactorAuthenticationController::class, 'showChallengeForm'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Admin dashboard (redirects to role-specific admin page)
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::redirect('/admin/dashboard', '/admin');

    // Student routes
    Route::middleware('role:student')->group(function () {
        // Course list for students
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');

        // Course assignments list
        Route::get('/courses/{course}/assignments/stdview', [SubmissionTaskController::class, 'availableForStudent'])->name('courses.assignments');

        // Course join via link/code
        Route::get('/courses/join/{uuid}', [CourseController::class, 'joinViaLink'])->name('courses.join.link');
        Route::post('/courses/join/{uuid}', [CourseController::class, 'processJoinLink'])->name('courses.join.link.process');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::delete('/notifications', [NotificationController::class, 'clearAll'])->name('notifications.clear');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::get('/notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');
        Route::put('/notifications/settings', [NotificationController::class, 'updateSettings'])->name('notifications.settings.update');

        // Settings (view own)
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'updateMultiple'])->name('settings.update');

        // Subscription management
        Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::get('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
        Route::post('/subscription/checkout/{plan}', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::post('/subscription/initiate-payment/{plan}', [SubscriptionController::class, 'initiatePayment'])->name('subscription.initiate-payment');
        Route::get('/subscription/payment/callback/{transaction}', [SubscriptionController::class, 'paymentCallback'])->name('subscription.payment.callback');
        Route::post('/subscription/upgrade', [SubscriptionController::class, 'processUpgrade'])->name('subscription.process-upgrade');
        Route::post('/subscription/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

        // Payment webhooks
        Route::post('/webhook/payment/{gateway}', [SubscriptionController::class, 'webhook'])
            ->middleware('webhook.verify')
            ->name('webhook.payment');

        // Attendance routes
        Route::get('/attendance', [AttendanceController::class, 'myAttendance'])->name('attendance.my');
        Route::get('/attendance/records', [AttendanceController::class, 'myAttendanceRecords'])->name('attendance.records');
        Route::get('/attendance/records/export', [AttendanceController::class, 'exportMyAttendanceRecords'])->name('attendance.records.export');
        Route::post('/attendance/start', [AttendanceController::class, 'startSession'])->name('attendance.start');
        Route::get('/attendance/session/{session}', [AttendanceController::class, 'showSession'])->name('attendance.session');
        Route::post('/attendance/session/{session}/close', [AttendanceController::class, 'closeSession'])->name('attendance.close');
        Route::post('/attendance/checkin', [AttendanceController::class, 'checkIn'])->name('attendance.checkin');
        Route::get('/attendance/active', [AttendanceController::class, 'activeSession'])->name('attendance.active');
        Route::post('/attendance/session/{session}/qr-refresh', [AttendanceController::class, 'refreshQr'])->name('attendance.qr.refresh');
        Route::get('/lecturer/attendance', [AttendanceController::class, 'lecturerSessions'])->name('attendance.lecturer');

        // Groups
        Route::resource('groups', GroupController::class);

        Route::post('groups/{group}/join', [GroupController::class, 'join'])
           ->name('groups.join');

        Route::post('groups/{group}/leave', [GroupController::class, 'leave'])
           ->name('groups.leave');

        Route::post('groups/{group}/remove-member', [GroupController::class, 'removeMember'])
           ->name('groups.remove-member');

        Route::post('groups/{group}/transfer-leadership', [GroupController::class, 'transferLeadership'])
           ->name('groups.transfer-leadership');

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

    // AI Academic Assistant routes (shared student & lecturer)
    Route::middleware('role:student,lecturer')->group(function () {
        Route::get('/submissions/{submission}/ai-analysis', [AiController::class, 'submissionAnalysis'])
            ->name('ai.submission.analysis');
        Route::post('/submissions/{submission}/reanalyze', [AiController::class, 'reanalyze'])
            ->name('ai.submission.reanalyze');
    });

    Route::post('/ai/writing', [AiController::class, 'writingAssistant'])->name('ai.writing');
    Route::post('/ai/citation', [AiController::class, 'citationAssistant'])->name('ai.citation');

    // Admin routes (department_admin, university_admin, super_admin)
    Route::middleware('role:department_admin,university_admin,super_admin')->group(function () {
        Route::get('/admin/ai/settings', [AiController::class, 'settings'])->name('ai.settings');
        Route::post('/admin/ai/settings', [AiController::class, 'updateSettings'])->name('ai.settings.update');
        Route::get('/admin/ai/analytics', [AiController::class, 'analytics'])->name('ai.analytics');
    });

    // Lecturer routes
    Route::middleware('role:lecturer')->group(function () {
        Route::get('/lecturer/courses', [CourseController::class, 'myCourses'])->name('lecturer.courses');

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
        Route::get('/admin/reports', [AdminController::class, 'reports'])->name('admin.reports');
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
    Route::middleware('role:university_admin')->group(function () {
        Route::get('/admin/faculties', [AdminController::class, 'faculties'])->name('admin.faculties');

        // Institutional subscriptions
        Route::get('/admin/subscriptions', [BillingController::class, 'subscriptions'])->name('admin.subscriptions');

        // Student billing
        Route::get('/billing/invoices', [BillingController::class, 'myInvoices'])
            ->name('billing.my');

        Route::get('/billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])
            ->name('billing.show');

        Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])
            ->name('billing.pay');

        // Admin billing
        Route::get('/admin/billing/invoices', [BillingController::class, 'adminIndex'])
            ->name('admin.billing');

        Route::post('/admin/billing/invoices/{invoice}/verify', [BillingController::class, 'verify'])
            ->name('billing.verify');

        Route::post('/admin/billing/invoices/{invoice}/waive', [BillingController::class, 'waive'])
            ->name('billing.waive');

        // Generate invoices for a semester
        Route::post('/admin/billing/semesters/{semester}/generate-invoices', [BillingController::class, 'generateInvoices'])
            ->name('billing.generate-invoices');
    });

    // Super Admin routes
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/universities', [AdminController::class, 'universities'])->name('admin.universities');
        Route::post('/admin/universities', [AdminController::class, 'createUniversity'])->name('admin.universities.create');
        Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings');
        Route::post('/admin/settings', [SettingsController::class, 'updateMultiple'])->name('admin.settings.update');
        Route::post('/admin/settings/toggle-flag/{featureFlag}', [SettingsController::class, 'toggleFeatureFlag'])->name('admin.settings.toggle-flag');
        Route::put('/admin/settings/{key}', [SettingsController::class, 'update'])->name('admin.settings.update-key');
        Route::get('/admin/settings/permissions', [SettingsController::class, 'permissions'])->name('admin.settings.permissions');
        Route::get('/admin/settings/audit-logs', [SettingsController::class, 'auditLogs'])->name('admin.settings.audit-logs');

        // Subscription Plans Management
        Route::get('/admin/subscription-plans', [SubscriptionPlanController::class, 'index'])->name('admin.subscription-plans');
        Route::get('/admin/subscription-plans/create', [SubscriptionPlanController::class, 'create'])->name('admin.subscription-plans.create');
        Route::post('/admin/subscription-plans', [SubscriptionPlanController::class, 'store'])->name('admin.subscription-plans.store');
        Route::get('/admin/subscription-plans/{subscriptionPlan}/edit', [SubscriptionPlanController::class, 'edit'])->name('admin.subscription-plans.edit');
        Route::put('/admin/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->name('admin.subscription-plans.update');
        Route::delete('/admin/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy'])->name('admin.subscription-plans.destroy');

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
