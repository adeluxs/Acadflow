# AcadSyntra API v1

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


## Payment verification and browser return

`POST /api/v1/payments/initiate` accepts a payable `invoice_id`, optional configured active `gateway`, and optional `payment_method` (`card` or `bank_transfer`). It returns `payment_url` and `verification_reference`; repeating a pending checkout reuses its URL/reference. If the provider result is unknown, the payment remains pending and another initialize request is blocked until its status is checked. Manual bank-transfer references are verified by authorized administrators, not through the gateway verify endpoint.

`POST /api/v1/payments/verify` accepts `reference`, enforces invoice ownership/tenant access, and verifies the stored gateway/environment, reference, amount and currency. Pending provider status returns 202; unavailable verification returns 503 when retryable; mismatches return 422. A verified payment can safely be checked again.

`GET /billing/payment-return?reference=...` is an authenticated browser route for the payment owner. It performs server-side invoice verification and returns to the wallet with confirmation or a pending message. Public signature-verified `POST /commerce/webhook/{gateway}` and the compatibility URL `POST /webhook/payment/{gateway}` settle supported payment types even if the payer never returns to the browser. Non-payment event types are ignored; temporary settlement failures return 503 for provider redelivery.


## Verification setting and browser push

Public settings expose `email_verification_enabled`. Auth registration/login/account-status `next_action` and readiness follow this runtime setting while `email_verified_at` continues to report actual verification. Account readiness still requires an active account and completed onboarding; disabling email verification does not bypass those checks or two-factor authentication.

Browser session endpoints: `GET /browser-push/configuration` returns availability and the public key only; `POST /browser-push/subscribe` accepts a browser subscription (`endpoint`, `keys.p256dh`, `keys.auth`, optional `expirationTime` in milliseconds); `POST /browser-push/unsubscribe` removes the authenticated user's matching device; `POST /browser-push/test` reports accepted delivery count or an actionable failure. Mutations require CSRF. `enablePreference=true` is reserved for explicit enrollment, which also opts the user into push delivery; automatic subscription reconciliation does not change preferences.

Existing `/api/v1/push/*` routes support Sanctum clients. `POST` and `DELETE /api/v1/push/unsubscribe` remain available with an authenticated account even when push is disabled. Subscription/list/test routes retain protected-account checks and module controls. Supported HTTPS browser push-service endpoints and valid P-256/auth keys are required.

### Research setup and save concurrency (2026-10-20)

`POST /api/v1/research-projects` additionally accepts optional `methodology`, `research_level`, `discipline`, `objectives`, `institution_name`, `studio_mode` (`research`/`final_year`), `report_structure` (`methodology`/`five_chapter`/`institutional`), `citation_style`, `citation_instructions` and optional `citation_template` (supported bibliography placeholders only). Values are validated against `config/research_methodologies.php`. Omit methodology to retain the research-type template contract. Selected setup is snapshotted in project metadata; existing projects are not recreated.

The existing section update endpoint accepts `expected_version`. A mismatched version returns 409 and preserves both the current document and its history. Locked sections return 423. API and web section saves share sanitisation, authorship and progress handling.

The session-authenticated Research Studio UI also provides project-scoped `/research-studio/{research}/plan`, `/resources`, `/analysis/{describe,sample-size,coding}` and `/export/{html,pdf,docx}` routes, declared in `routes/web.php`. They require authentication, applicable verification/2FA/onboarding checks, the Research Studio feature and model permissions. Mutation forms require CSRF. Dataset tools require the separately managed `research_datasets` feature; these session routes are not new public Sanctum API endpoints. See [Research Studio](RESEARCH_STUDIO.md) for resource versioning, private delivery, analysis limits and deployment.
