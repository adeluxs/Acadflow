<?php

use App\Http\Controllers\AcademicOrganisationController as Organisations;
use App\Http\Controllers\DepartmentOnboardingController as DepartmentOnboarding;
use Illuminate\Support\Facades\Route;

Route::get('/academic/directory', [Organisations::class, 'directory'])->name('academic.directory');
Route::put('/academic/departments/{department}/modules', [Organisations::class, 'modules'])->name('academic.departments.modules');
Route::post('/academic/departments/{department}/calendar', [Organisations::class, 'calendar'])->name('academic.departments.calendar');
Route::get('/academic/affiliations', [Organisations::class, 'index'])->name('academic.organisations');
Route::post('/academic/context', [Organisations::class, 'switchContext'])->name('academic.context.switch');
Route::post('/academic/memberships/{membership}/end', [Organisations::class, 'endMembership'])->middleware('throttle:30,1')->name('academic.memberships.end');
Route::post('/academic/invitations', [Organisations::class, 'invite'])->middleware('throttle:20,1')->name('academic.invitations.store');
Route::get('/academic/invitations/{token}', [Organisations::class, 'invitation'])->name('academic.invitations.accept');
Route::post('/academic/invitations/{token}', [Organisations::class, 'acceptInvitation'])->middleware('throttle:20,1')->name('academic.invitations.consume');
Route::post('/academic/invitations/{invitation}/revoke', [Organisations::class, 'revokeInvitation'])->name('academic.invitations.revoke');
Route::post('/academic/department-affiliations', [Organisations::class, 'requestAffiliation'])->middleware('throttle:20,1')->name('academic.department-affiliations.store');
Route::post('/academic/department-affiliations/{affiliation}', [Organisations::class, 'respondAffiliation'])->middleware('throttle:20,1')->name('academic.department-affiliations.respond');
Route::get('/department/onboarding', [DepartmentOnboarding::class, 'show'])->name('department.onboarding');
Route::put('/department/onboarding/draft', [DepartmentOnboarding::class, 'saveDraft'])->middleware('throttle:60,1')->name('department.onboarding.draft');
Route::post('/department/onboarding', [DepartmentOnboarding::class, 'submit'])->middleware('throttle:commercial-account-requests')->name('department.onboarding.submit');
Route::post('/department/onboarding/institution', [DepartmentOnboarding::class, 'requestInstitution'])->middleware('throttle:commercial-account-requests')->name('department.onboarding.institution');
Route::get('/department/onboarding/{departmentRequest}/evidence', [DepartmentOnboarding::class, 'evidence'])->name('department.onboarding.evidence');
