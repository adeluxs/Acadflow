# Laravel Codebase File Structure

---

## 1. Root Directory Structure

```
uni-academic/
├── app/
│   ├── Actions/                    # Laravel Actions
│   ├── Console/                  # Console commands
│   ├── DTOs/                   # Data Transfer Objects
│   ├── Enums/                   # PHP Enums
│   ├── Events/                  # Domain events
│   ├── Exceptions/              # Custom exceptions
│   ├── Http/                  # HTTP layer
│   │   ├── Controllers/         # Controllers
│   │   ├── Middleware/         # Middleware
│   │   ├── Requests/         # Form requests
│   │   └── Resources/       # API Resources
│   ├── Jobs/                     # Queue jobs
│   ├── Listeners/               # Event listeners
│   ├── Mail/                   # Mailable classes
│   ├── Models/                 # Eloquent models
│   ├── Notifications/          # Notifications
│   ├── Observers/              # Model observers
│   ├── Policies/              # Authorization policies
│   ├── Providers/              # Service providers
│   ├── Services/              # Business services
│   ├── Support/               # Support helpers
│   ├── Traits/                 # Reusable traits
│   └── Modules/                # Feature modules
│
├── bootstrap/
│   ├── app.php
│   └── cache/
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── mail.php
│   ├── queue.php
│   └── ...
│
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│
├── resources/
│   ├── css/
│   ├── js/
│   ├── views/
│   └── lang/
│
├── routes/
│   ├── api.php
│   ├── channels.php
│   ├── console.php
│   └── web.php
│
├── storage/
│   ├── app/
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   └── views/
│   └── logs/
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
├── public/
│   ├── index.php
│   └── assets/
│
├── .env
├── .env.example
├── artisan
├── composer.json
├── phpunit.xml
├── README.md
└── docker-compose.yml
```

---

## 2. App Directory Structure

### 2.1 Actions (`app/Actions/`)

```
Actions/
├── Auth/
│   ├── LoginAction.php
│   ├── RegisterAction.php
│   └── ResetPasswordAction.php
│
├── Submission/
│   ├── CreateSubmissionAction.php
│   ├── UploadFileAction.php
│   ├── SubmitAction.php
│   └── GradeSubmissionAction.php
│
├── Attendance/
│   ├── StartSessionAction.php
│   ├── CheckInAction.php
│   └── CloseSessionAction.php
│
├── Billing/
│   ├── GenerateInvoiceAction.php
│   ├── VerifyPaymentAction.php
│   └── GenerateReceiptAction.php
│
└── Document/
    └── GeneratePdfAction.php
```

### 2.2 Models (`app/Models/`)

```
Models/
├── User.php
├── University.php
├── Faculty.php
├── Department.php
├── Course.php
├── Session.php
├── Semester.php
├── Enrollment.php
├── LecturerCourseAssignment.php
├── Submission.php
├── SubmissionVersion.php
├── SubmissionComment.php
├── SubmissionGrade.php
├── SubmissionRubric.php
├── Group.php
├── GroupMember.php
├── GroupContribution.php
├── AttendanceSession.php
├── AttendanceRecord.php
├── AttendanceRule.php
├── Subscription.php
├── Invoice.php
├── Payment.php
├── DocumentTemplate.php
├── GeneratedDocument.php
├── Notification.php
├── AuditLog.php
└── ApprovalFlow.php
```

### 2.3 Enums (`app/Enums/`)

```
Enums/
├── UserRole.php
├── SubmissionStatus.php
├── SubmissionType.php
├── SubmissionCommentType.php
├── AttendanceStatus.php
├── AttendanceSessionStatus.php
├── GroupStatus.php
├── InvoiceStatus.php
├── PaymentStatus.php
├── PaymentMethod.php
├── BillingModel.php
├── EnrollmentStatus.php
└── NotificationType.php
```

### 2.4 Services (`app/Services/`)

```
Services/
├── AuthService.php
├── FileUploadService.php
├── PdfGenerationService.php
├── EmailService.php
├── NotificationService.php
├── AttendanceService.php
├── PaymentService.php
├── SmsService.php
└── AnalyticsService.php
```

### 2.5 Controllers (`app/Http/Controllers/`)

```
Controllers/
├── Auth/
│   ├── AuthController.php
│   └── PasswordController.php
│
├── Api/
│   ├── ApiController.php
│   └── V1/
│       ├── SubmissionController.php
│       ├── AttendanceController.php
│       ├── BillingController.php
│       └── DocumentController.php
│
├── Web/
│   ├── DashboardController.php
│   ├── CourseController.php
│   ├── SubmissionController.php
│   ├── AttendanceController.php
│   ├── GroupController.php
│   ├── BillingController.php
│   └── DocumentController.php
│
├── Admin/
│   ├── AdminController.php
│   ├── UserController.php
│   ├── DepartmentController.php
│   ├── CourseAdminController.php
│   └── ReportController.php
│
└── Controller.php (base)
```

---

## 3. Feature Modules Structure

### 3.1 Optional Module Structure

When using feature modules for larger separation:

```
app/Modules/
├── Academics/
│   ├── Models/
│   │   ├── Submission.php
│   │   ├── Course.php
│   │   └── ...
│   ├── Services/
│   │   └── SubmissionService.php
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Routes/
│   │       └── web.php
│   │       api.php
│   └── database/
│       └── migrations/
│
├── Attendance/
│   ├── Models/
│   ├── Services/
│   ├── Http/
│   └── database/
│       └── migrations/
│
├── Billing/
│   ├── Models/
│   ├── Services/
│   ├── Http/
│   └── database/
│       └── migrations/
│
├── Documents/
│   ├── Models/
│   ├── Services/
│   ├── Http/
│   └── database/
│       └── migrations/
│
└── Users/
    ├── Models/
    ├── Services/
    ├── Http/
    └── database/
        └── migrations/
```

---

## 4. Database Migrations

### 4.1 Migration Naming Convention

```
database/migrations/
├── 2024_01_01_000001_create_users_table.php
├── 2024_01_01_000002_create_universities_table.php
├── 2024_01_01_000003_create_faculties_table.php
├── 2024_01_01_000004_create_departments_table.php
├── 2024_01_01_000005_create_courses_table.php
├── 2024_01_01_000006_create_sessions_table.php
├── 2024_01_01_000007_create_semesters_table.php
├── 2024_01_01_000008_create_enrollments_table.php
├── 2024_01_01_000009_create_lecturer_course_assignments_table.php
├── 2024_01_01_000010_create_submissions_table.php
├── 2024_01_01_000011_create_submission_versions_table.php
├── 2024_01_01_000012_create_submission_comments_table.php
├── 2024_01_01_000013_create_submission_grades_table.php
├── 2024_01_01_000014_create_submission_rubrics_table.php
├── 2024_01_01_000015_create_groups_table.php
├── 2024_01_01_000016_create_group_members_table.php
├── 2024_01_01_000017_create_group_contributions_table.php
├── 2024_01_01_000018_create_attendance_sessions_table.php
├── 2024_01_01_000019_create_attendance_records_table.php
├── 2024_01_01_000020_create_attendance_rules_table.php
├── 2024_01_01_000021_create_subscriptions_table.php
├── 2024_01_01_000022_create_invoices_table.php
├── 2024_01_01_000023_create_payments_table.php
├── 2024_01_01_000024_create_document_templates_table.php
├── 2024_01_01_000025_create_generated_documents_table.php
├── 2024_01_01_000026_create_notifications_table.php
├── 2024_01_01_000027_create_audit_logs_table.php
└── 2024_01_01_000028_add_foreign_keys.php
```

---

## 5. Routes Files

### 5.1 Web Routes (`routes/web.php`)

```php
<?php

// Public routes
Route::get('/', HomeController::class)->name('home');
Route::get('/features', FeatureController::class)->name('features');

// Authentication routes
require __DIR__ . '/auth.php';

// Dashboard routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    
    // Role-based routes
    Route::middleware(['role:student'])->group(base_path('routes/student.php'));
    Route::middleware(['role:lecturer'])->group(base_path('routes/lecturer.php'));
    Route::middleware(['role:department_admin'])->group(base_path('routes/department_admin.php'));
    Route::middleware(['role:university_admin'])->group(base_path('routes/university_admin.php'));
    Route::middleware(['role:super_admin'])->group(base_path('routes/super_admin.php'));
});
```

### 5.2 API Routes (`routes/api.php`)

```php
<?php

Route::prefix('v1')->group(function () {
    // Auth routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [UserController::class, 'me']);
        
        Route::apiResource('courses', CourseController::class);
        Route::apiResource('submissions', SubmissionController::class);
        Route::apiResource('attendance', AttendanceController::class);
    });
});
```

---

## 6. HTTP Layer

### 6.1 Form Requests (`app/Http/Requests/`)

```
Requests/
├── Auth/
│   ├── LoginRequest.php
│   ├── RegisterRequest.php
│   └── ResetPasswordRequest.php
│
├── Submission/
│   ├── CreateSubmissionRequest.php
│   ├── UploadFileRequest.php
│   └── SubmitRequest.php
│
├── Attendance/
│   ├── StartSessionRequest.php
│   └── CheckInRequest.php
│
├── Billing/
│   ├── CreateInvoiceRequest.php
│   └── VerifyPaymentRequest.php
│
└── Request.php (base)
```

### 6.2 API Resources (`app/Http/Resources/`)

```
Resources/
├── UserResource.php
├── CourseResource.php
├── SubmissionResource.php
├── SubmissionVersionResource.php
├── AttendanceSessionResource.php
├── InvoiceResource.php
├── PaymentResource.php
├── DocumentResource.php
└── Collection/
    ├── UserCollection.php
    ├── SubmissionCollection.php
    └── ...
```

---

## 7. Testing Structure

### 7.1 Feature Tests

```
tests/Feature/
├── Auth/
│   ├── LoginTest.php
│   ├── RegistrationTest.php
│   └── PasswordResetTest.php
│
├── Submission/
│   ├── CreateSubmissionTest.php
│   ├── UploadFileTest.php
│   ├── SubmitTest.php
│   └── GradingTest.php
│
├── Attendance/
│   ├── StartSessionTest.php
│   ├── CheckInTest.php
│   └── CloseSessionTest.php
│
├── Billing/
│   ├── PaymentTest.php
│   └── InvoiceTest.php
│
└── API/
    └── ApiTest.php
```

### 7.2 Unit Tests

```
tests/Unit/
├── Actions/
│   ├── CreateSubmissionActionTest.php
│   └── ...
│
├── Services/
│   ├── FileUploadServiceTest.php
│   ├── AttendanceServiceTest.php
│   └── ...
│
└── Models/
    ├── SubmissionTest.php
    └── ...
```

---

## 8. Naming Conventions

### 8.1 File Naming

| Component | Pattern | Example |
|-----------|--------|---------|
| Controller | `{Name}Controller.php` | `SubmissionController.php` |
| Model | `{Name}.php` | `Submission.php` |
| Service | `{Name}Service.php` | `FileUploadService.php` |
| Action | `{Action}Action.php` | `CreateSubmissionAction.php` |
| Request | `{Name}Request.php` | `CreateSubmissionRequest.php` |
| Policy | `{Name}Policy.php` | `SubmissionPolicy.php` |
| Migration | `{timestamp}_{action}_{table}.php` | `2024_01_01_000001_create_users_table.php` |
| Factory | `{Name}Factory.php` | `UserFactory.php` |
| Seeder | `{Name}Seeder.php` | `UserSeeder.php` |
| Test | `{Name}Test.php` | `SubmissionTest.php` |

### 8.2 Route Naming

| Pattern | Example |
|---------|---------|
| `controller.method` | `submission.index` |
| `controller.store` | `submission.store` |
| `controller.show` | `submission.show` |
| `controller.update` | `submission.update` |
| `controller.destroy` | `submission.destroy` |

---

## 9. Code Organization Guidelines

### 9.1 Controller Guidelines
- Keep controllers thin
- Use form requests for validation
- Delegate business logic to services/actions
- Return resources for API responses

### 9.2 Model Guidelines
- Define relationships
- Define scopes
- Define accessors/mutators
- Keep query logic in scopes or repositories

### 9.3 Service Guidelines
- One service per domain concept
- Use dependency injection
- Keep services testable

### 9.4 Testing Guidelines
- Test at unit level for logic
- Test at feature level for workflows
- Use factories for test data

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*