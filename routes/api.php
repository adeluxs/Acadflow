<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes for the UniAcademic platform
| Version: v1
|
*/

// Public API Routes
Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);
});

// Protected API Routes
Route::prefix('v1')->middleware(['auth:sanctum', 'subscription.feature:allow_api_access'])->group(function () {
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
    Route::get('/subscriptions', [BillingController::class, 'subscriptions']);

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
});
