# AcadFlow API v1

**Current source snapshot:** 2026-08-15  
**Base path:** `/api/v1`  
**Auth:** Laravel Sanctum

This file summarizes routes currently declared in `routes/api.php`. The source route file remains the final endpoint authority.

## Public endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/register` | Register |
| POST | `/api/v1/auth/password/reset` | Password reset request/action handled by API controller |
| GET | `/api/v1/settings/public` | Public/bootstrap settings |

## Authenticated onboarding/bootstrap

Requires `auth:sanctum` but is available before the full account-ready middleware:

- `GET /auth/account-status`
- `POST /auth/email/verification-notification`
- `GET /onboarding`
- `PUT /onboarding/{step}`
- `POST /onboarding/{step}/skip`
- `POST /onboarding/complete`

## Fully protected application API

Protected by:

```text
auth:sanctum
feature.access
api.account.ready
subscription.feature:allow_api_access
```

### User/auth

- `GET /user`
- `PUT /user`
- `PUT /user/password`
- `POST /auth/logout`

### Courses

- list/show/create/update/delete courses
- enrol in a course
- list course enrolments

### Submissions

- list/create/show/update/delete
- upload and submit
- versions/comments/grade
- lecturer/admin approve/reject/request-correction

### Research Studio

- list/create/show/update research projects
- update sections
- transition workflow
- validate project
- publish to Knowledge Hub

### Knowledge Hub

- list/create/show/update publication
- submit publication
- comments/reactions/follow
- Grounded Companion request

### Attendance

- sessions CRUD-ish operations
- close session / QR
- active session / student check-in
- own attendance / records / export

### Billing

- invoices/show
- payments list/initiate/verify
- subscriptions list

### Documents

- templates
- generated documents list/create/show/download

### Reports

- submissions, attendance, billing, courses
- export and analytics

### Notifications

- list
- mark one read
- mark all read

### Push

- subscribe/unsubscribe/list subscriptions
- VAPID public key
- test push

### Offline sync

- `POST /sync/process`

## Response/security rules for developers

- Never trust client-side role/feature checks.
- Authorize model resources server-side.
- Use tenant scope.
- Preserve existing JSON status/error conventions.
- Feature middleware can return `FEATURE_MAINTENANCE` or `FEATURE_DISABLED`.
- AI endpoints should use central AI feature/routing architecture.
- Do not expose provider/payment credentials in responses.

When adding/changing an endpoint, update this file, `DEVELOPER_GUIDE.md`, `USER_GUIDE.md` when user-visible, and `CHANGELOG.md`.
