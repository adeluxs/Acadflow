<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\ResearchController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes for the AcadFlow platform
| Version: v1
|
*/

// Public API Routes
Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::get('/settings/public', [\App\Http\Controllers\SettingsController::class, 'publicSettings'])->name('api.settings.public');
});

// Account bootstrap routes are available to limited onboarding tokens.
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/auth/account-status', [AuthController::class, 'accountStatus'])->middleware('throttle:api');
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:verification');
    Route::get('/onboarding', [OnboardingController::class, 'show'])->middleware('throttle:api');
    Route::put('/onboarding/{step}', [OnboardingController::class, 'save'])->middleware('throttle:api');
    Route::post('/onboarding/{step}/skip', [OnboardingController::class, 'skip'])->middleware('throttle:api');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->middleware('throttle:api');
});

// Protected API Routes
Route::prefix('v1')->middleware(['auth:sanctum', 'feature.access', 'api.account.ready'])->group(function () {
    // User
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Courses
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    Route::post('/courses/{course}/enroll', [CourseController::class, 'enroll']);
    Route::get('/courses/{course}/enrollments', [CourseController::class, 'enrollments']);

    // Submissions
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::post('/submissions', [SubmissionController::class, 'store']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::put('/submissions/{submission}', [SubmissionController::class, 'update']);
    Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy']);
    Route::post('/submissions/{submission}/upload', [SubmissionController::class, 'upload']);
    Route::post('/submissions/{submission}/submit', [SubmissionController::class, 'submit']);
    Route::get('/submissions/{submission}/versions', [SubmissionController::class, 'versions']);
    Route::get('/submissions/{submission}/comments', [SubmissionController::class, 'comments']);
    Route::post('/submissions/{submission}/comments', [SubmissionController::class, 'addComment']);
    Route::get('/submissions/{submission}/grade', [SubmissionController::class, 'grade']);
    Route::post('/submissions/{submission}/grade', [SubmissionController::class, 'submitGrade']);
    Route::post('/submissions/{submission}/approve', [SubmissionController::class, 'approve']);
    Route::post('/submissions/{submission}/reject', [SubmissionController::class, 'reject']);
    Route::post('/submissions/{submission}/request-correction', [SubmissionController::class, 'requestCorrection']);

    // Research Studio (same services and policies as the web workspace)
    Route::get('/research-projects', [ResearchController::class, 'index']);
    Route::post('/research-projects', [ResearchController::class, 'store']);
    Route::get('/research-projects/{research}', [ResearchController::class, 'show']);
    Route::put('/research-projects/{research}', [ResearchController::class, 'update']);
    Route::put('/research-projects/{research}/sections/{section}', [ResearchController::class, 'updateSection']);
    Route::post('/research-projects/{research}/transition', [ResearchController::class, 'transition']);
    Route::post('/research-projects/{research}/validate', [ResearchController::class, 'validateProject']);
    Route::post('/research-projects/{research}/publish', [ResearchController::class, 'publish']);

    // Knowledge Hub
    Route::get('/knowledge', [KnowledgeController::class, 'index']);
    Route::post('/knowledge', [KnowledgeController::class, 'store']);
    Route::get('/knowledge/{publication}', [KnowledgeController::class, 'show']);
    Route::put('/knowledge/{publication}', [KnowledgeController::class, 'update']);
    Route::post('/knowledge/{publication}/submit', [KnowledgeController::class, 'submit']);
    Route::get('/knowledge/{publication}/comments', [KnowledgeController::class, 'comments']);
    Route::post('/knowledge/{publication}/comments', [KnowledgeController::class, 'comment']);
    Route::post('/knowledge/{publication}/reactions', [KnowledgeController::class, 'react']);
    Route::post('/knowledge/{publication}/follow', [KnowledgeController::class, 'follow']);
    Route::post('/knowledge/{publication}/companion', [KnowledgeController::class, 'companion']);

    // Attendance
    Route::get('/attendance/sessions', [AttendanceController::class, 'index']);
    Route::post('/attendance/sessions', [AttendanceController::class, 'store']);
    Route::get('/attendance/sessions/{session}', [AttendanceController::class, 'show']);
    Route::put('/attendance/sessions/{session}', [AttendanceController::class, 'update']);
    Route::post('/attendance/sessions/{session}/close', [AttendanceController::class, 'closeSession']);
    Route::get('/attendance/sessions/{session}/qr', [AttendanceController::class, 'qrCode']);
    Route::get('/attendance/active', [AttendanceController::class, 'activeSession']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::get('/attendance/my', [AttendanceController::class, 'myAttendance']);
    Route::get('/attendance/records', [AttendanceController::class, 'records']);
    Route::get('/attendance/records/export', [AttendanceController::class, 'exportRecords']);

    // Billing
    Route::get('/invoices', [BillingController::class, 'index']);
    Route::get('/invoices/{invoice}', [BillingController::class, 'show']);
    Route::get('/payments', [BillingController::class, 'payments']);
    Route::post('/payments/verify', [BillingController::class, 'verifyPayment']);
    Route::post('/payments/initiate', [BillingController::class, 'initiatePayment']);

    // Documents
    Route::get('/documents/templates', [DocumentController::class, 'templates']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents/generate', [DocumentController::class, 'generate']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

    // Reports (Admin/ Lecturer)
    Route::get('/reports/submissions', [SubmissionController::class, 'report']);
    Route::get('/reports/attendance', [AttendanceController::class, 'report']);
    Route::get('/reports/billing', [BillingController::class, 'report']);
    Route::get('/reports/courses', [CourseController::class, 'report']);
    Route::get('/reports/export', [SubmissionController::class, 'export']);
    Route::get('/reports/analytics', [SubmissionController::class, 'analytics']);

    // Notifications
    Route::get('/notifications', [AuthController::class, 'notifications']);
    Route::put('/notifications/{notification}/read', [AuthController::class, 'markNotificationRead']);
    Route::put('/notifications/read-all', [AuthController::class, 'markAllNotificationsRead']);

    // Push Notifications
    Route::post('/push/subscribe', [PushNotificationController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushNotificationController::class, 'unsubscribe']);
    Route::get('/push/subscriptions', [PushNotificationController::class, 'subscriptions']);
    Route::get('/push/vapid-public-key', [PushNotificationController::class, 'vapidPublicKey']);
    Route::post('/push/test', [PushNotificationController::class, 'test']);

    // Offline Sync
    Route::post('/sync/process', [\App\Http\Controllers\Api\SyncController::class, 'process'])->name('api.sync.process');
});
