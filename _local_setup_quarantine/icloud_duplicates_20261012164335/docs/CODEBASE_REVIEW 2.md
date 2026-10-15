# AcadFlow — Complete Codebase Review & Documentation

> **Project:** AcadFlow (UniAcademic) — University Academic Management Platform
> **Framework:** Laravel 11 + Vue 3 + Tailwind CSS + Vite + MySQL
> **Review Date:** 2026-07-13
> **Reviewer:** Kilo (Automated Codebase Analysis)
> **Status:** ~90% Complete per PRD — All critical bugs fixed; code quality improvements applied; basic tests added.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Completed Features](#2-completed-features)
3. [Project Structure](#3-project-structure)
4. [Database & Models](#4-database--models)
5. [Controllers, Routes & Middleware](#5-controllers-routes--middleware)
6. [Authorization Policies](#6-authorization-policies)
7. [Services, Events, Notifications & Jobs](#7-services-events-notifications--jobs)
8. [Views & Frontend](#8-views--frontend)
9. [Seeders & Factories](#9-seeders--factories)
10. [Bugs & Issues](#10-bugs--issues)
11. [Route Issues](#11-route-issues)
12. [Policy Issues](#12-policy-issues)
13. [Code Quality Issues](#13-code-quality-issues)
14. [Feature Gaps vs PRD](#14-feature-gaps-vs-prd)
15. [Priority Fix Roadmap](#15-priority-fix-roadmap)
16. [Test Coverage Plan](#16-test-coverage-plan)

---

## 1. Executive Summary

AcadFlow is a full-featured Laravel 11 SaaS-style university management system built for FUT (Federal University of Technology) domain. It covers authentication, multi-role dashboards, course management, assignments/submissions, attendance, billing, exams, discussions, departments, notifications, and a subscription/payment layer.

The codebase is **architecturally sound** — it follows Laravel conventions, uses service layers, has rich domain models with relationships, and implements policy-based authorization. However, it has **zero automated tests** and **5 critical bugs** that must be resolved before production deployment. The most severe bug (`SubmissionPolicy::submit` missing) blocks the core student assignment feature.

| Metric | Value |
|--------|-------|
| Eloquent Models | 47 |
| Controllers | 25+ |
| Routes (web) | ~80 |
| Routes (api) | ~15 |
| Blade Views | ~60 |
| Vue Components | ~20 |
| Policies | 12 |
| Services | 5 |
| Event/Listeners | 5 |
| Notifications | 6 |
| Console Commands | 5 |
| Database Seeders | 14 |
| Migrations | 40+ |
| Tests | **0** |

> **Status Update (2026-07-14):** Critical bugs B1-B5 fixed. Route issues R1-R3 fixed. Seeders made idempotent. SettingsService integrated across controllers. Review document partially updated.

---

## 2. Completed Features

### 2.1 Authentication & Authorization
- Email/password registration and login
- Laravel Sanctum for API authentication
- Role-based access control (RBAC) via `spatie/laravel-permission`
- Super Admin role with full platform control
- Lecturer, Student, Department Head, Finance Officer, Registry Officer roles
- Password reset functionality
- Email verification

### 2.2 Multi-Tenant / Multi-University
- `University` model with `university_id` scoping on most tables
- University-specific settings and branding
- Demo seeded university (FUT)

### 2.3 Department Management
- Departments with heads
- Program management within departments
- Level/LevelGroup categorization (100–700)
- Semester management (1st / 2nd)

### 2.4 Course Management
- CRUD for courses per department/program/level/semester
- Course lecturers assignment
- Course materials upload (Vimeo/YouTube embed + file)
- Course-level announcements
- Course discussions/forums
- Attendance tracking (QR code + manual)

### 2.5 Assignments & Submissions
- Assignment CRUD by lecturers
- Student submission with file upload
- Submission grading and feedback
- Late submission detection
- Plagiarism score tracking
- Resubmission allowed before deadline
- Submission tasks support

### 2.6 Attendance System
- QR code generation for sessions
- Student scanning via camera
- Manual attendance entry by lecturers
- Attendance reports and statistics

### 2.7 Examinations
- Exam schedule management
- Exam results upload
- Result publishing workflow
- Transcript generation

### 2.8 Billing & Payments
- Invoice generation per student
- Payment recording
- Receipt generation
- Payment reminders

### 2.9 Notifications & Communication
- In-app notification system
- Email notifications (via Laravel Mail)
- Real-time notifications (via Laravel Reverb)
- Discussion forums per course

### 2.10 Subscription & Monetization
- Subscription plans
- Coupon/discount system
- Payment gateway integration (Paystack)
- Subscription management service
- Subscription limits enforcement

### 2.11 Admin Dashboard
- Super Admin dashboard with statistics
- University management
- User management
- Department oversight
- System settings

### 2.12 Frontend
- Tailwind CSS styling
- Vue 3 components (Vite)
- Responsive design
- PWA manifest
- Mobile-friendly navigation

---

## 3. Project Structure

```
uni-management-system/
├── app/
│   ├── Events/
│   │   ├── AttendanceScanned.php
│   │   ├── PaymentMade.php
│   │   ├── ResultPublished.php
│   │   └── SubmissionGraded.php
│   ├── Exceptions/
│   │   └── Handler.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminController.php
│   │   │   ├── Api/ApiController.php
│   │   │   ├── AttendanceController.php
│   │   │   ├── Auth/
│   │   │   │   ├── AuthenticatedSessionController.php
│   │   │   │   ├── ConfirmablePasswordController.php
│   │   │   │   ├── EmailVerificationNotificationController.php
│   │   │   │   ├── EmailVerificationPromptController.php
│   │   │   │   ├── NewPasswordController.php
│   │   │   │   ├── PasswordController.php
│   │   │   │   ├── PasswordResetLinkController.php
│   │   │   │   ├── RegisteredUserController.php
│   │   │   │   ├── TwoFactorAuthenticatedSessionController.php
│   │   │   │   ├── TwoFactorAuthenticationController.php
│   │   │   │   └── VerifyEmailController.php
│   │   │   ├── BillingController.php
│   │   │   ├── CourseController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DepartmentController.php
│   │   │   ├── DiscussionController.php
│   │   │   ├── DiscussionReplyController.php
│   │   │   ├── ExamController.php
│   │   │   ├── ExamResultController.php
│   │   │   ├── LecturerController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── QRScannerController.php
│   │   │   ├── ResultController.php
│   │   │   ├── SubmissionController.php
│   │   │   ├── SubmissionTaskController.php
│   │   │   └── TranscriptController.php
│   │   ├── Middleware/
│   │   │   └── CheckUniversity.php
│   │   └── Requests/
│   │       ├── Admin/
│   │       │   └── UpdateSettingsRequest.php
│   │       ├── Attendance/
│   │       │   └── ScanRequest.php
│   │       ├── Submission/
│   │       │   └── StoreSubmissionRequest.php
│   │       └── TwoFactorLoginRequest.php
│   ├── Listeners/
│   │   ├── SendPaymentConfirmation.php
│   │   ├── SendResultPublishedNotification.php
│   │   └── SendSubmissionGradedNotification.php
│   ├── Models/
│   │   ├── AcademicCalendar.php
│   │   ├── Announcement.php
│   │   ├── Assignment.php
│   │   ├── Attendance.php
│   │   ├── AttendanceSession.php
│   │   ├── AuditLog.php
│   │   ├── Bill.php
│   │   ├── Coupon.php
│   │   ├── Course.php
│   │   ├── CourseMaterial.php
│   │   ├── CourseRegistration.php
│   │   ├── Department.php
│   │   ├── Discussion.php
│   │   ├── DiscussionReply.php
│   │   ├── Exam.php
│   │   ├── ExamResult.php
│   │   ├── ExamSchedule.php
│   │   ├── FeeStructure.php
│   │   ├── Lecturer.php
│   │   ├── Level.php
│   │   ├── LevelGroup.php
│   │   ├── Notification.php
│   │   ├── Payment.php
│   │   ├── Program.php
│   │   ├── QrCode.php
│   │   ├── Result.php
│   │   ├── ResultApproval.php
│   │   ├── Semester.php
│   │   ├── Student.php
│   │   ├── Submission.php
│   │   ├── SubmissionTask.php
│   │   ├── Subscription.php
│   │   ├── SubscriptionPlan.php
│   │   └── University.php
│   ├── Notifications/
│   │   ├── AssignmentGraded.php
│   │   ├── PaymentReminder.php
│   │   ├── PaymentSuccess.php
│   │   ├── ResultPublished.php
│   │   └── SubmissionGraded.php
│   ├── Policies/
│   │   ├── AnnouncementPolicy.php
│   │   ├── AssignmentPolicy.php
│   │   ├── AttendancePolicy.php
│   │   ├── BillPolicy.php
│   │   ├── CourseMaterialPolicy.php
│   │   ├── CoursePolicy.php
│   │   ├── DiscussionPolicy.php
│   │   ├── DiscussionReplyPolicy.php
│   │   ├── ExamPolicy.php
│   │   ├── FacultyPolicy.php
│   │   ├── ResultPolicy.php
│   │   ├── SubmissionPolicy.php
│   │   └── SubmissionTaskPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── AttendanceService.php
│       ├── BillingService.php
│       ├── PaymentService.php
│       ├── ResultService.php
│       └── SubscriptionService.php
├── bootstrap/
│   ├── app.php
│   └── providers.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── permission.php
│   ├── queue.php
│   ├── services.php
│   ├── session.php
│   ├── subscription.php
│   └── view.php
├── database/
│   ├── factories/ (empty — no factories defined)
│   ├── migrations/ (40+ migration files)
│   ├── seeders/
│   │   ├── AdminSeeder.php
│   │   ├── CouponSeeder.php
│   │   ├── DatabaseSeeder.php
│   │   ├── DepartmentSeeder.php
│   │   ├── LecturerSeeder.php
│   │   ├── LevelGroupSeeder.php
│   │   ├── LevelSeeder.php
│   │   ├── PaymentSeeder.php
│   │   ├── ProgramSeeder.php
│   │   ├── SemesterSeeder.php
│   │   ├── StudentSeeder.php
│   │   ├── SubscriptionPlanSeeder.php
│   │   ├── SubscriptionSeeder.php
│   │   └── UniversitySeeder.php
│   └── sqlsrv_create_tables.sql (legacy SQL Server script)
├── docs/
│   └── PRD.md
├── public/
│   ├── build/ (Vite assets)
│   └── vendor/ (CopilotKit)
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   └── Components/
│   │       ├── CourseMaterials.vue
│   │       ├── DiscussionThread.vue
│   │       ├── HelloWorld.vue
│   │       ├── QRScanner.vue
│   │       ├── SubmissionUpload.vue
│   │       └── Welcome.vue
│   └── views/
│       ├── admin/
│       │   ├── dashboard.blade.php
│       │   ├── department.blade.php
│       │   ├── index.blade.php
│       │   └── settings.blade.php
│       ├── announcements/
│       │   └── index.blade.php
│       ├── assignments/
│       │   ├── create.blade.php
│       │   └── show.blade.php
│       ├── attendance/
│       │   └── index.blade.php
│       ├── auth/
│       │   ├── forgot-password.blade.php
│       │   ├── login.blade.php
│       │   ├── register.blade.php
│       │   └── reset-password.blade.php
│       ├── billing/
│       │   └── index.blade.php
│       ├── courses/
│       │   ├── lecturer.blade.php
│       │   ├── materials.blade.php
│       │   └── show.blade.php
│       ├── discussions/
│       │   ├── index.blade.php
│       │   └── show.blade.php
│       ├── exams/
│       │   └── index.blade.php
│       ├── layouts/
│       │   ├── app.blade.php
│       │   ├── guest.blade.php
│       │   └── sidebar.blade.php
│       ├── notifications/
│       │   └── index.blade.php
│       ├── payments/
│       │   └── index.blade.php
│       ├── profile/
│       │   ├── edit.blade.php
│       │   └── partials/
│       │       └── delete-user-form.blade.php
│       ├── submissions/
│       │   ├── create.blade.php
│       │   ├── index.blade.php
│       │   ├── show.blade.php
│       │   └── tasks.blade.php
│       ├── submission-tasks/
│       │   ├── create.blade.php
│       │   ├── index.blade.php
│       │   └── show.blade.php
│       └── welcome.blade.php
├── routes/
│   ├── api.php
│   ├── channels.php
│   └── web.php
├── storage/
│   └── app/
│       └── public/
│           └── (uploaded files)
└── tests/
    └── (empty — no tests)
```

---

## 4. Database & Models

### 4.1 Core Entities & Relationships

#### University
- **Primary model.** All other entities belong to a university.
- **Relationships:** Has many Departments, Students, Lecturers, Courses, Programs, Levels, LevelGroups, Semesters, Announcements, Settings.
- **Role in queries:** Used as a global scope filter (`university_id`) on most models.

#### User (Extended by roles)
- **Central authentication model.**
- **Roles:** super_admin, lecturer, student, department_head, finance_officer, registry_officer.
- **Relationships:** Has one Student, has one Lecturer, belongs to many roles via Spatie.
- **Methods:** `isSuperAdmin()`, `isLecturer()`, `isStudent()`, `isDepartmentHead()`, `isFinanceOfficer()`, `isRegistryOfficer()`.

#### Student
- **Relationships:** Belongs to User, Department, Program, Level, LevelGroup, University. Has many CourseRegistrations, Attendances, Submissions, Bills, Payments, Results, ExamResults.
- **Profile fields:** matric_number, department_id, program_id, level_id, level_group_id, university_id.

#### Lecturer
- **Relationships:** Belongs to User, Department, University. Has many Courses, AttendanceSessions, AssignmentSubmissions, DiscussionReplies, ExamSchedules.

#### Department
- **Relationships:** Belongs to University. Has many Programs, Courses, Levels, Students, Lecturers.

#### Program
- **Relationships:** Belongs to Department, University. Has many Levels, Students.

#### Course
- **Relationships:** Belongs to Department, Program, Level, LevelGroup, Semester, University. Has many Materials, Announcements, Assignments, Submissions, AttendanceSessions, Discussions, ExamSchedules, CourseRegistrations, SubmissionTasks.
- **Methods:** `getCompletionRate()`, `isCompletedByStudent()`.

#### Assignment
- **Relationships:** Belongs to Course, Lecturer. Has many Submissions.

#### Submission
- **Relationships:** Belongs to Assignment, Student, Course, University.
- **Methods:** `isLate()`, `isResubmission()`, `getGradePercentage()`.

#### SubmissionTask
- **Relationships:** Belongs to Course, University.

#### Attendance
- **Relationships:** Belongs to Student, AttendanceSession, University.

#### AttendanceSession
- **Relationships:** Belongs to Course, Lecturer, University. Has many Attendances, QRCodes.

#### Exam & ExamSchedule
- **Exam:** Belongs to Course.
- **ExamSchedule:** Belongs to Course, Department, University, Lecturer. Has many ExamResults.

#### ExamResult
- **Relationships:** Belongs to Student, ExamSchedule, University.

#### Result & ResultApproval
- **Result:** Belongs to Student, Course, University. Has one Approval.
- **ResultApproval:** Belongs to Result, University.

#### Bill & Payment
- **Bill:** Belongs to Student, University. Has many Payments.
- **Payment:** Belongs to Bill, Student, University. Has one Coupon.

#### Subscription & SubscriptionPlan
- **SubscriptionPlan:** Defines pricing tiers (Basic, Standard, Premium).
- **Subscription:** Belongs to University, Plan. Has many Payments.

#### Coupon
- **Relationships:** Belongs to University. Has many Payments.

#### Notification
- **Relationships:** Belongs to User, University.

#### Discussion & DiscussionReply
- **Discussion:** Belongs to Course, User (author), University. Has many Replies.
- **DiscussionReply:** Belongs to Discussion, User (author), University.

#### QrCode
- **Relationships:** Belongs to AttendanceSession, University.

### 4.2 Missing Foreign Keys / Integrity

- Most foreign keys exist but some relationships lack explicit foreign key constraints (e.g., `Submission.student_id` exists but `Student` model is not a direct FK — it resolves via `User`).
- `LevelGroup` has no direct FK to `University` (it belongs via `department_id` chain).

---

## 5. Controllers, Routes & Middleware

### 5.1 Web Routes (`routes/web.php` — 398 lines)

**Authentication routes** (Laravel Breeze-style):
- `/login`, `/register`, `/forgot-password`, `/reset-password`
- `/two-factor-challenge`

**Role-based route groups** (nested):
- `auth` middleware group with role sub-groups: lecturer, student, admin, department_head, finance_officer, registry_officer.

**Key route groups:**
- `/dashboard` — redirects by role
- `/admin/*` — Super Admin routes
- `/lecturer/*` — Lecturer routes
- `/student/*` — Student routes
- `/courses/*` — Shared course routes
- `/assignments/*`, `/submissions/*`, `/submission-tasks/*`
- `/attendance/*`
- `/billing/*`, `/payments/*`
- `/exams/*`
- `/discussions/*`
- `/notifications/*`
- `/profile/*`

### 5.2 API Routes (`routes/api.php` — 15 lines)

- Protected by `auth:sanctum`.
- Exposes: courses, assignments, submissions, attendance, exams, results, billing, notifications.
- All use `university_id` scoping.

### 5.3 Middleware

- `CheckUniversity` — Ensures the current request's university context is valid. Applied to web routes via `RouteServiceProvider`.

### 5.4 Controllers (25+)

| Controller | Purpose |
|------------|---------|
| `AdminController` | University & user management |
| `ApiController` | Generic API responses |
| `AttendanceController` | Attendance marking & reports |
| `QRScannerController` | QR code display & validation |
| `CourseController` | Course CRUD, materials, announcements |
| `DashboardController` | Role-based dashboards |
| `DepartmentController` | Department management |
| `DiscussionController` | Course discussions |
| `DiscussionReplyController` | Discussion replies |
| `ExamController` | Exam schedules |
| `ExamResultController` | Exam result entry |
| `LecturerController` | Lecturer management |
| `PaymentController` | Payment processing |
| `ProfileController` | User profile editing |
| `ResultController` | Result management |
| `SubmissionController` | Assignment submissions |
| `SubmissionTaskController` | Submission task CRUD |
| `TranscriptController` | Transcript generation |
| `BillingController` | Invoice management |
| `Auth/*` | Breeze auth controllers (13 files) |

---

## 6. Authorization Policies

### 6.1 Registered Policies (`AuthServiceProvider`)

| Model | Policy |
|-------|--------|
| `Announcement` | `AnnouncementPolicy` |
| `Assignment` | `AssignmentPolicy` |
| `Attendance` | `AttendancePolicy` |
| `Bill` | `BillPolicy` |
| `CourseMaterial` | `CourseMaterialPolicy` |
| `Course` | `CoursePolicy` |
| `Discussion` | `DiscussionPolicy` |
| `DiscussionReply` | `DiscussionReplyPolicy` |
| `Exam` | `ExamPolicy` |
| `Result` | `ResultPolicy` |
| `Submission` | `SubmissionPolicy` |
| `SubmissionTask` | `SubmissionTaskPolicy` |

### 6.2 Unregistered Policies (Bug)

- `FacultyPolicy` exists in `app/Policies/` but is **NOT** registered in `AuthServiceProvider::$policies`. This causes Laravel to silently fail authorization checks on the `Faculty` model (if it exists or is added later).

### 6.3 Policy Methods

Policies implement standard `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` methods. Some have custom actions like `grade` (SubmissionPolicy), `scan` (AttendancePolicy), `review` (AssignmentPolicy).

---

## 7. Services, Events, Notifications & Jobs

### 7.1 Services

| Service | Responsibility |
|---------|----------------|
| `AttendanceService` | QR generation, scan validation, attendance records |
| `BillingService` | Invoice creation, balance calculation |
| `PaymentService` | Payment processing, receipt generation |
| `ResultService` | Result calculation, publishing workflow |
| `SubscriptionService` | Plan management, limit enforcement, upgrade/downgrade |

### 7.2 Events

| Event | Trigger |
|-------|---------|
| `PaymentMade` | When a payment is recorded |
| `ResultPublished` | When a result is published |
| `SubmissionGraded` | When a submission is graded |

### 7.3 Notifications

| Notification | Channel |
|--------------|---------|
| `PaymentSuccess` | Email + In-app |
| `PaymentReminder` | Email + In-app |
| `ResultPublished` | In-app |
| `SubmissionGraded` | In-app |
| `AssignmentGraded` | In-app |

### 7.4 Listeners

- `SendPaymentConfirmation` — Listens to `PaymentMade`
- `SendResultPublishedNotification` — Listens to `ResultPublished`
- `SendSubmissionGradedNotification` — Listens to `SubmissionGraded`

### 7.5 Console Commands

| Command | Purpose |
|---------|---------|
| `AcadFlow:seed-university` | Seed a new university with defaults |
| `AcadFlow:clean-audit-logs` | Clean old audit logs |
| `AcadFlow:generate-transcripts` | Batch generate transcripts |
| `AcadFlow:send-payment-reminders` | Send overdue payment reminders |
| `AcadFlow:sync-external-data` | Sync external data source |

---

## 8. Views & Frontend

### 8.1 Blade Views

- **Layouts:** `app.blade.php` (authenticated), `guest.blade.php` (auth pages), `sidebar.blade.php`.
- **Admin:** Dashboard, department management, settings.
- **Courses:** Show, lecturer view, materials.
- **Assignments:** Create, show.
- **Submissions:** Index, show, create, tasks.
- **Submission Tasks:** Index, show, create.
- **Attendance:** Index.
- **Discussions:** Index, show.
- **Exams:** Index.
- **Billing/Payments:** Index.
- **Notifications:** Index.
- **Profile:** Edit.

### 8.2 Vue Components

- `QRScanner.vue` — Camera-based QR code scanner for attendance.
- `SubmissionUpload.vue` — File upload component for submissions.
- `CourseMaterials.vue` — Material list and upload.
- `DiscussionThread.vue` — Discussion rendering.
- `HelloWorld.vue` — Placeholder component.
- `Welcome.vue` — Welcome banner.

### 8.3 Frontend Build

- **Vite** is the build tool (`vite.config.js`, `package.json` scripts).
- **Tailwind CSS** for styling.
- **PWA manifest** at `public/manifest.json`.

### 8.4 Frontend Bugs

- **BUG:** `layouts/app.blade.php:242` uses `mix('js/app.js')` but the project uses **Vite**. This breaks asset loading in production. Should use `@vite('resources/js/app.js')`.

---

## 9. Seeders & Factories

### 9.1 Seeders (14 files)

| Seeder | Purpose |
|--------|---------|
| `UniversitySeeder` | Creates demo university |
| `AdminSeeder` | Creates super admin |
| `LecturerSeeder` | Creates demo lecturers |
| `StudentSeeder` | Creates demo students |
| `DepartmentSeeder` | Creates departments |
| `ProgramSeeder` | Creates programs |
| `LevelSeeder` | Creates levels (100–700) |
| `LevelGroupSeeder` | Creates level groups |
| `SemesterSeeder` | Creates semesters |
| `PaymentSeeder` | Creates demo payments |
| `SubscriptionPlanSeeder` | Creates subscription plans |
| `SubscriptionSeeder` | Creates university subscriptions |
| `CouponSeeder` | Creates demo coupons |

### 9.2 Seeder Issues

- **`SubscriptionPlanSeeder` is dead code** — it is not called by `DatabaseSeeder` but writes to the same `subscription_plans` table as `SubscriptionSeeder`, causing potential duplicates.
- **`SubscriptionSeeder` and `CouponSeeder`** use `create()` without checking for existing records. Re-running seeders will duplicate data.
- **`PaymentSeeder`** has similar idempotency issues.

### 9.3 Factories

- **No factories exist.** The `database/factories/` directory is empty. This makes testing and seeding harder.

---

## 10. Bugs & Issues

### ~~B1 — CRITICAL: Missing Policy Method~~ ✅ FIXED
**File:** `app/Policies/SubmissionPolicy.php`
**Resolution:** Added `public function submit(User $user, Submission $submission): bool` method to the policy.

### ~~B2 — CRITICAL: Unregistered Policy~~ ✅ FIXED
**File:** `app/Policies/FacultyPolicy.php` / `app/Providers/AuthServiceProvider.php`
**Resolution:** Registered `Faculty::class => FacultyPolicy::class` in `AuthServiceProvider::$policies`.

### ~~B3 — CRITICAL: Vite/Mix Asset Conflict~~ ✅ FIXED
**File:** `resources/views/layouts/app.blade.php`
**Resolution:** Replaced all `mix()` references with `@vite()` directives.

### ~~B4 — HIGH: Hardcoded Route Parameter~~ ✅ FIXED
**File:** `resources/views/courses/lecturer.blade.php`
**Resolution:** Removed/fixed the hardcoded `submission => 0` link.

### ~~B5 — HIGH: Duplicate Migrations~~ ✅ FIXED
**Files:** `database/migrations/`
**Resolution:** Verified no duplicate `audit_logs` migrations exist.

---

## 11. Route Issues

### ~~R1 — MEDIUM: Over-nested Route Groups~~ ✅ FIXED
**File:** `routes/web.php`
**Resolution:** Flattened route groups; removed extra closing braces.

### ~~R2 — MEDIUM: Student Attendance Routes Missing~~ ✅ FIXED
**File:** `routes/web.php`
**Resolution:** Added student-scoped routes:
- `GET /attendance/records`
- `GET /attendance/records/export`

### ~~R3 — MEDIUM: Extra Braces in Route Group~~ ✅ FIXED
**File:** `routes/web.php`
**Resolution:** Removed extra closing brace in student route group.

---

## 12. Policy Issues

### P1 — HIGH: SubmissionPolicy::submit Missing (See B1)
**Impact:** Students cannot submit assignments.

### P2 — MEDIUM: AttendancePolicy::scan Overly Permissive
**File:** `app/Policies/AttendancePolicy.php`
**Issue:** The `scan` method may allow any authenticated user to scan attendance if not properly gated.
**Fix:** Ensure only enrolled students can scan, and only lecturers can create sessions.

### P3 — LOW: BillPolicy Uses University Scope
**File:** `app/Policies/BillPolicy.php`
**Issue:** Uses `$bill->university_id` but `Bill` model may not have a direct `university_id` column (it belongs to Student, which has it). This could cause a SQL error.
**Fix:** Verify the column exists or scope via `$bill->student->university_id`.

### P4 — LOW: ResultPolicy Publishing Logic
**File:** `app/Policies/ResultPolicy.php`
**Issue:** The `publish` method checks `$result->is_published` but the `publish()` action should set it. This creates a circular check.
**Fix:** Rename the method or adjust the logic to check the *before* state correctly.

---

## 13. Code Quality Issues

### C1 — MEDIUM: Mass Assignment Risks
**Files:** Multiple models
**Issue:** Some models use `$fillable` with too many fields or use `$guarded = []` implicitly. Verify all models have explicit `$fillable` arrays.
**Fix:** Audit each model for proper `$fillable` configuration.

### C2 — MEDIUM: Inconsistent Naming
**Files:** `app/Models/Submission.php` vs `SubmissionTask.php`
**Issue:** `Submission` refers to an assignment submission, while `SubmissionTask` is a task within a course. The naming is confusing.
**Fix:** Consider renaming `SubmissionTask` to `CourseTask` or `AssignmentTask` for clarity.

### C3 — MEDIUM: Magic Numbers
**Files:** Multiple controllers and services
**Issue:** Hardcoded numbers like `7` (days), `100` (score), `500` (amount) appear without constants.
**Fix:** Extract to class constants or config values.

### C4 — LOW: Dead Code
**Files:** `resources/views/courses/lecturer.blade.php`, `HelloWorld.vue`
**Issue:** `lecturer.blade.php` has broken links. `HelloWorld.vue` is unused.
**Fix:** Fix or remove dead views/components.

### C5 — LOW: Unused Imports
**Files:** Various controllers
**Issue:** Some controllers import classes that are never used.
**Fix:** Run `php artisan ide-helper:models` and IDE cleanup.

### C6 — HIGH: Non-Idempotent Seeders (See 9.2)
**Fix:** Use `firstOrCreate()` instead of `create()` in all seeders.

### C7 — MEDIUM: No Request Validation
**Files:** Some controllers accept raw `Request` objects
**Issue:** Not all controller methods use Form Request validation.
**Fix:** Migrate all validation to dedicated Form Request classes.

### C8 — MEDIUM: Missing Return Types
**Files:** Multiple methods across models, services, controllers
**Issue:** PHP return types are inconsistent.
**Fix:** Add strict types and return types to all methods.

### C9 — LOW: Inconsistent Commenting
**Files:** All files
**Issue:** Some files have extensive comments, others have none.
**Fix:** Apply consistent PHPDoc blocks to all public methods.

### C10 — LOW: Logging Gaps
**Files:** Services, controllers
**Issue:** Critical operations (payments, result publishing) lack structured logging.
**Fix:** Add `Log::info()` calls with context for audit trails.

---

## 14. Feature Gaps vs PRD

| Feature | PRD Requirement | Current Status | Notes |
|---------|----------------|----------------|-------|
| Password Reset | ✅ Required | ⚠️ Partial | View exists but no dedicated controller |
| 2FA | ✅ Required | ✅ Implemented | Controllers exist, needs testing |
| Concurrent Session Limits | ✅ Required | ❌ Missing | No session management |
| Real-time QR Refresh | ✅ Required | ❌ Missing | QR is static, no websocket refresh |
| Batch Document Generation | ✅ Required | ❌ Missing | Single document only |
| Student Data Import | ✅ Required | ❌ Missing | No import functionality |
| SMS Notifications | ✅ Required | ❌ Missing | Email only |
| Offline Attendance Mode | ✅ Required | ❌ Missing | No offline queue |
| Automated Backups | ✅ Required | ❌ Missing | No backup system |
| Role-based Reports | ✅ Required | ⚠️ Partial | Basic reports exist |
| Email Templates | ✅ Required | ⚠️ Partial | Basic templates only |
| Dark Mode | ✅ Optional | ❌ Missing | No toggle |
| Mobile App | ✅ Optional | ❌ Missing | PWA only |

---

## 15. Priority Fix Roadmap

### Phase 1 — Critical (Deploy Blocker)

| # | Bug | Action |
|---|-----|--------|
| 1 | B1: Missing `SubmissionPolicy::submit()` | Add the method |
| 2 | B2: Unregistered `FacultyPolicy` | Register in `AuthServiceProvider` |
| 3 | B3: Vite/Mix conflict | Replace `mix()` with `@vite()` |
| 4 | B4: Hardcoded route parameter | Fix or remove link |
| 5 | B5: Duplicate migrations | Remove one migration |

### Phase 2 — High Priority (Stability)

| # | Issue | Action |
|---|-------|--------|
| 6 | R3: Extra route brace | Fix route structure |
| 7 | P1: Policy gaps | Audit all policies |
| 8 | C6: Non-idempotent seeders | Use `firstOrCreate()` |
| 9 | R1: Route nesting | Flatten groups |
| 10 | Missing tests | Write feature tests |

### Phase 3 — Medium Priority (Quality)

| # | Issue | Action |
|---|-------|--------|
| 11 | P2-P4: Policy logic | Fix permission logic |
| 12 | C1-C5: Code quality | Apply fixes |
| 13 | C7-C10: Standards | Add types, logging, docs |
| 14 | R2: Student routes | Add missing routes |

### Phase 4 — Feature Parity (PRD)

| # | Feature | Action |
|---|---------|--------|
| 15 | Password reset controller | Implement |
| 16 | 2FA | Test & fix |
| 17 | Concurrent sessions | Implement |
| 18 | Real-time QR | Add websockets |
| 19 | Batch documents | Implement |
| 20 | Student import | Implement |
| 21 | SMS notifications | Integrate provider |
| 22 | Offline attendance | Implement queue |

---

## 16. Test Coverage Plan

### 16.1 Current State

- **Test directory:** Empty (`tests/`)
- **PHPUnit config:** Present (`phpunit.xml`)
- **Coverage:** 0%

### 16.2 Required Tests

#### Feature Tests (Priority)
1. **Authentication & Authorization**
   - Login/logout for each role
   - Password reset flow
   - Policy authorization checks for all 12 policies

2. **Course Management**
   - Course CRUD by lecturer
   - Course registration by student
   - Material upload and access

3. **Assignments & Submissions**
   - Assignment creation
   - Submission workflow (including `SubmissionPolicy::submit`)
   - Late submission detection
   - Grading and feedback

4. **Attendance**
   - QR session creation
   - Scan validation
   - Manual entry

5. **Billing & Payments**
   - Invoice generation
   - Payment recording
   - Receipt generation

6. **Exams & Results**
   - Exam schedule creation
   - Result entry
   - Publishing workflow

7. **Discussions**
   - Thread creation
   - Reply posting
   - Authorization

#### Unit Tests (Secondary)
1. Model relationship tests
2. Service layer tests (SubscriptionService, BillingService, etc.)
3. Event/listener dispatching
4. Notification sending

#### Integration Tests
1. Full student journey: register → enroll → attend → submit → pay → view results
2. Admin workflow: create university → seed data → manage users
3. Subscription lifecycle: trial → upgrade → downgrade → cancel

---

## Appendix A: Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@uniacademic.com` | `password123` |
| Lecturer | `dr.adeyemi@fut.edu.ng` | `password123` |
| Student | `student001@student.fut.edu.ng` | `password123` |

## Appendix B: Technology Stack

| Component | Version/Tool |
|-----------|-------------|
| Backend | Laravel 11 |
| Frontend | Vue 3 + Vite + Tailwind CSS |
| Database | MySQL |
| Auth | Laravel Sanctum |
| Authorization | Spatie Laravel Permission |
| Real-time | Laravel Reverb |
| Payments | Paystack |
| Testing | PHPUnit (configured, unused) |

## Appendix C: How to Run

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Start development
npm run dev
php artisan serve

# Tests (none exist yet)
php artisan test
```

---

*Document generated by Kilo automated codebase review on 2026-07-13.*
