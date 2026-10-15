# AcadFlow Codebase Structure

**Current source snapshot:** 2026-08-15

This is a map of directories actually present in the source. For architectural meaning, see `DEVELOPER_GUIDE.md`.

```text
app/
├── Actions/
├── Ai/
│   ├── Contracts/
│   ├── Features/
│   ├── Providers/
│   ├── Rules/
│   └── Support/
├── Console/
├── Contracts/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   ├── Api/
│   │   ├── Auth/
│   │   └── Concerns/
│   └── Middleware/
├── Jobs/
├── Listeners/
├── Models/
├── Notifications/
├── Observers/
├── Policies/
├── Providers/
├── Services/
│   ├── AcademicIntegrity/
│   ├── Ai/
│   ├── Commerce/
│   ├── Discovery/
│   ├── Knowledge/
│   ├── Media/
│   ├── PaymentGateway/
│   ├── Reputation/
│   └── Scholarly/
└── Support/
    └── Database/

bootstrap/
config/
database/
├── data/
├── migrations/
└── seeders/
docs/
public/
resources/
├── css/
├── js/
└── views/
routes/
scripts/
tests/
```

## Key entry files

- `bootstrap/app.php` — Laravel 12 middleware/routing bootstrap
- `routes/web.php` — web routes
- `routes/api.php` — API v1
- `routes/console.php` — schedules and Artisan commands
- `config/features.php` — feature registry metadata
- `config/ai.php` — AI bootstrap defaults/active feature registry/provider metadata
- `.env.example` — complete environment template

## Current view domains

`resources/views` contains account/admin/ai/attendance/auth/billing/commerce/courses/dashboard/defenses/discussions/documents/enrollments/groups/knowledge/materials/moderation/notifications/onboarding/research/settings/students/submission-tasks/submissions/subscription and shared layouts/partials.

## Important note

The current source does **not** use an `app/Modules/` directory or the DTO/Traits structure previously described in older planning files. Do not create those folders merely to match old documentation.
