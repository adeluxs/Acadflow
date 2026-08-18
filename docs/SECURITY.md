# AcadFlow Security Architecture

**Current source snapshot:** 2026-08-15  
**Canonical engineering guide:** `DEVELOPER_GUIDE.md`

## Authentication

- Laravel web auth and Sanctum API auth
- email verification before protected application routes
- password reset flow
- optional/required two-factor authentication through account security/challenge middleware
- session timeout and concurrent-session middleware
- CSRF protection on web routes, with explicit verified webhook exclusions

Current named rate limits include:

- login: 10/minute by email+IP
- registration: 5/hour by IP
- password reset: 5/minute by email+IP
- verification: 6/minute by user/IP
- two-factor: 5/minute by user/IP
- AI: runtime-configurable per-minute limit
- challenge votes: 20/minute
- secure downloads: 30/minute
- commerce webhooks: 120/minute by IP

## Authorization

AcadFlow uses layered authorization:

1. role middleware;
2. permission enum mapping;
3. model policies;
4. tenant/department/course membership queries;
5. feature/module availability;
6. subscription feature gates;
7. AI feature gates.

Never use UI visibility as the only authorization control.

## Tenant isolation

Institutional data is scoped by university and often department/course. AI context, research records, student submissions, settings/credentials and Knowledge permissions must preserve tenant boundaries.

## Feature management

Feature status is centrally enforced for web/API. Admins can preview restricted features; normal users cannot. API unavailable responses include controlled feature status codes.

## AI security

- provider credentials are centralized and must be masked/encrypted where stored;
- provider secrets must never be logged or returned to clients;
- retrieved documents are treated as untrusted data;
- prompt injection patterns are sanitized/guarded;
- Grounded Companion requires evidence/source validation;
- contextual assistants authorize the model before building context;
- gibberish can be rejected before provider calls;
- Provider AI mode cannot silently pretend Rule Engine output came from the selected provider.

## File/media security

- secure media services and expiring tokens
- configurable malware scanning
- server-side MIME/size validation
- private academic files should not be exposed by raw public URLs
- safe streaming/missing-file handling

## Webhooks/payments

Payment/commerce webhooks are intentionally outside signed-in browser auth and use webhook verification middleware. Keep them CSRF-exempt only where verified by gateway signature logic.

## Database/seeding safety

- production-safe migrations
- explicit short MySQL index names
- idempotent seeders
- no production `migrate:fresh`
- no truncation/reset of administrator settings/credentials during upgrades

## Logging/privacy

Do not log passwords, recovery codes, API keys, access tokens or payment secrets. AI usage logs should store routing/usage metadata without leaking provider credentials. Be conservative when storing prompts/responses containing student/research content.

## Reporting vulnerabilities

Use the organization/platform security contact configured for the deployment. Do not use the default Laravel framework security-contact wording from old boilerplate README files.
