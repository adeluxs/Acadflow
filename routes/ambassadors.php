<?php

use App\Http\Controllers\AmbassadorController;
use App\Http\Controllers\Admin\AmbassadorManagementController;
use Illuminate\Support\Facades\Route;

// Included inside the verified, onboarding-complete application route group.
// The "campus_ambassadors" feature setting gates every ambassadors.* route;
// Super Admin management (admin.ambassadors.*) stays available when it is off.
Route::get('/ambassador', [AmbassadorController::class, 'index'])->name('ambassadors.index');
Route::post('/ambassador/verify-email', [AmbassadorController::class, 'verifyReferralEmail'])->middleware('throttle:3,60')->name('ambassadors.verify-email');
Route::post('/ambassador/apply', [AmbassadorController::class, 'apply'])->middleware('throttle:5,60')->name('ambassadors.apply');
Route::post('/ambassador/withdraw', [AmbassadorController::class, 'withdraw'])->middleware('throttle:5,60')->name('ambassadors.withdraw');
Route::post('/ambassador/reports', [AmbassadorController::class, 'report'])->middleware('throttle:10,60')->name('ambassadors.reports.store');
Route::get('/ambassador/reports/{report}/evidence', [AmbassadorController::class, 'evidence'])->middleware('throttle:60,1')->name('ambassadors.reports.evidence');

Route::prefix('admin/ambassadors')->name('admin.ambassadors.')->middleware('role:super_admin')->group(function (): void {
    Route::get('/', [AmbassadorManagementController::class, 'index'])->name('index');
    Route::get('/activities', [AmbassadorManagementController::class, 'tasks'])->name('tasks.index');
    Route::post('/activities', [AmbassadorManagementController::class, 'storeTask'])->middleware('throttle:30,1')->name('tasks.store');
    Route::patch('/activities/{activity}', [AmbassadorManagementController::class, 'updateTask'])->middleware('throttle:30,1')->name('tasks.update');
    Route::get('/reports/{report}/evidence', [AmbassadorController::class, 'evidence'])->name('reports.evidence');
    Route::get('/{application}', [AmbassadorManagementController::class, 'show'])->name('show');
    Route::patch('/{application}/review', [AmbassadorManagementController::class, 'review'])->name('review');
    Route::post('/{application}/rewards', [AmbassadorManagementController::class, 'award'])->middleware('throttle:30,1')->name('rewards.store');
    Route::patch('/referrals/{referral}', [AmbassadorManagementController::class, 'reviewReferral'])->middleware('throttle:30,1')->name('referrals.review');
    Route::patch('/reports/{report}', [AmbassadorManagementController::class, 'reviewReport'])->name('reports.review');
    Route::post('/rewards/{reward}/fulfil', [AmbassadorManagementController::class, 'fulfil'])->middleware('throttle:30,1')->name('rewards.fulfil');
    Route::post('/rewards/{reward}/cancel', [AmbassadorManagementController::class, 'cancel'])->name('rewards.cancel');
});
