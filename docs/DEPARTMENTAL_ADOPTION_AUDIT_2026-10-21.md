# Departmental adoption and institution integration audit

Date: 21 October 2026.

Status: this is the pre-implementation audit. The complete requirements have
since been received and implemented; see [Departmental adoption](DEPARTMENTAL_ADOPTION.md)
for workflows, policy, billing, deployment and verification details.

## Existing architecture to extend

| Area | Existing implementation | Implication |
| --- | --- | --- |
| Academic hierarchy | `University` → `Faculty` → `Department` | `University` already represents universities, polytechnics and colleges; retain it rather than adding a competing institution model. |
| Independent registration | `OnboardingService`, `WebAuthController`, API authentication, `IndependentFirstLaunchTest` | Students and lecturers can register without institution or department adoption. Keep this path. |
| Institution adoption | `InstitutionOnboardingRequest`, `InstitutionOnboardingController`, `InstitutionAdoptionService` | Existing institution search, claims, missing-institution requests, Platform Admin review, setup and activation should remain the authoritative workflow. |
| Department adoption | `DepartmentAdoptionRequest`, `createDepartmentAdoption()`, `approveDepartmentAdoption()` | Independent department activation already exists; improve its validation, UX, metadata, verification and commercial integration. |
| Account connections | `InstitutionAccessRequest`, `UserInstitutionAffiliation`, web/API adoption controllers | Existing user IDs survive connection and approval. Extend these records for membership lifecycle rather than creating replacement users. |
| Permissions | `User::role`, `Permission`, Laravel policies, role middleware | Roles are currently global to the user; membership records do not independently carry effective permissions. Multiple concurrent scopes need an explicit design. |
| Department dashboard | `AdminController::department()`, `admin.department` view and route | Department-scoped counts and course/user/report routes exist. Enhance this dashboard instead of introducing another admin area. |
| Billing | `CommercialAccount`, `CommercialAccountRequest`, Commerce services, pricing rules, wallets, ledger and feature entitlements | Recurring subscription sales were retired. Adoption plans must use the current commercial/entitlement system and preserve historical subscriptions. |
| Course membership | `CourseMembershipScope`, enrollments, lecturer assignments, `CourseInvitation` | Independent classrooms already use explicit participation. Reuse course invitation security patterns when adding adoption invitations. |
| Research Studio / final-year projects | `ResearchProject`, `ResearchProjectPolicy`, workspace modes, project memberships and invitations | Preserve owner IDs, workspace mode, sections, references, resources, documents and project relationships when accounts connect. |
| Assignments / submissions | Course relationships, `SubmissionTaskPolicy`, `ScopesTenantData` | Access derives from course participation and academic scope; extend central scope checks instead of patching each screen independently. |
| Notifications / audit | `SocialNotificationService`, `NotificationService`, `AuditLog` | Existing channels can deliver availability/review notices and record transitions. |

The core adoption tables and status fields were introduced in
`database/migrations/2026_09_03_000001_add_progressive_institution_adoption.php`.

## Findings affecting the requested behavior

### Department applications and academic hierarchy

- The department application and membership forms ask users to enter internal
  institution/faculty/department IDs. Institution search exists but the department
  form does not use it. Department/faculty selection needs server-scoped discovery.
- `requestForm()` admits representatives and administrators, while
  `submitDepartmentRequest()` also admits lecturers/academic staff. A lecturer can
  be eligible to submit but unable to open the application page.
- The department representative onboarding path requires an existing institution,
  faculty and department before onboarding can complete. That blocks a representative
  whose institution or department is missing from the catalogue.
- Department applications store a representative title and a free-text evidence
  note. Dedicated official contacts, classification, private verification uploads
  and commercial-plan selection are absent from that workflow.
- `createDepartmentAdoption()` checks a supplied department's institution but does
  not check that a separately supplied faculty belongs to the chosen institution,
  or that the faculty matches the supplied department. With no department supplied,
  its pending-request check also spans all pending departments in the institution.
- Approval can choose the institution's first faculty when none was specified.
  That silently assigns academic structure rather than requesting a valid school.
- Approval checks occur before the transaction and do not lock the request row.
  Concurrent applications/reviews need transactional state guards and duplicate
  handling before this flow is expanded.

### Membership lifecycle and privileges

- Access requests currently use `pending`, `in_review`, `approved` and `rejected`.
  Affiliations separately use `self_declared`, `pending_verification`, `verified`
  and `rejected`. There is no complete revocation workflow that also removes
  effective privileges and sponsorship.
- A new access request is deduplicated by user + institution only, ignoring the
  requested department and role. Different applications can return the wrong
  existing request.
- Applying again for an already verified affiliation changes that affiliation
  back to `pending_verification`. `recordSelfDeclared()` also updates an existing
  matching record without guarding its verified status.
- The affiliation unique key is user + institution + affiliation type. It cannot
  represent two departments for the same role within one institution. Whether
  concurrent department memberships are required needs an explicit decision.
- `verifyAffiliation()` updates the user's global role and profile affiliation.
  Linking one membership can change the user's existing administrative or academic
  context. Personal research remains participant-scoped, but role/context changes
  need regression coverage for owned courses and documents too.
- `User::hasVerifiedInstitutionAffiliation()` checks for any verified affiliation
  at the institution, without considering role or department. That is too broad
  for departmental funding and scoped privilege checks.
- Existing commercial-benefit checks require verified affiliation and an active
  institution. A matching email domain alone does not enable that sponsorship.
  However, several academic visibility/join checks still rely on profile
  `university_id`/`department_id` rather than verified membership. Self-declared
  affiliation must be separated from institutional privileges centrally.
- Web/API validation differs: the web access handler requires a department for
  department-admin requests and validates an institution-email match; the API
  handler does not apply those same checks. Neither check by itself grants
  automatic approval, but both interfaces need the same service-level validation.
- `AdminController::inviteUser()` requires a previously unused email and immediately
  creates an account/role, then sends a password-reset setup link. It cannot connect
  an existing independently registered account through an adoption invitation.
  Course invitations already demonstrate hashed, expiring, email-bound tokens;
  adoption invitations should use the same security principles with scope and
  role verification, single use, revocation and replay protection.
- No department-availability notification flow was found. Existing notifications
  cover applicant approval/rejection, but do not alert matching self-declared
  students/lecturers that their department is now available to request access.

### Department administration, modules and commercial access

- The existing department dashboard shows student/lecturer/course counts. Its
  Manage Users link is empty; it does not surface pending access requests,
  departmental activation state, enabled modules or a departmental commercial
  account. Counts currently include profile affiliations rather than only approved
  memberships.
- Academic sessions and semesters are institution-scoped in
  `AcademicContextService`. Independent department operation must account for this
  existing dependency without giving a department control over other departments'
  academic calendars.
- Feature overrides currently take an institution ID. A department cannot have
  its own module configuration through the existing override schema. Extend the
  central feature registry and middleware so department settings cannot bypass
  platform maintenance/disable rules.
- `commercial_accounts.university_id` is unique and there is no department scope.
  Commercial requests and the commercial UI are institution-admin oriented.
  Existing AI/feature sponsorship resolvers require institution activation, so an
  independently adopted department cannot currently sponsor its verified members.
- Departmental funding must remain isolated from sibling departments, personal
  wallets and institution balances. Reuse minor-unit money, journals, idempotency,
  reserve/settle/release and reconciliation behavior from Commerce services.
- Student semester invoices are currently generated by institution/platform
  administrators using institution commercial terms. Department-specific billing
  and calendar authority must be scoped explicitly, not enabled by broadening an
  administrator role check.

### Department-to-institution transition

`InstitutionOnboardingController::activate()` currently marks an institution active
and immediately invokes `migrateIndependentDepartments()`. That method bulk-updates
all `independent_active` departments under that institution to `institution_managed`
and overwrites `adopted_at`. IDs and content remain in place, but there is no separate
integration decision, per-department transition record, commercial handover or
administrator consent step. The method itself also does not require the institution
to be active, although its current controller caller activates it first.

Do not expand this automatic transition until the rest of section 4 is available.
The missing scenario determines who may authorize integration, which governance
changes occur, whether departments retain autonomy and how commercial commitments
and historical records are handled. No answer to those points has been assumed.

## Validation completed before implementation

Tests use the repository's PHPUnit testing configuration, isolated in-memory
SQLite, array mail/cache/session drivers and fake Vite assets. The live application
database, adoption statuses and commercial balances were not changed.

Baseline command (PHP 8.5.2 from MAMP):

```sh
php vendor/bin/phpunit \
  tests/Feature/InstitutionProgressiveAdoptionTest.php \
  tests/Feature/InstitutionOnboardingSearchRegressionTest.php \
  tests/Feature/IndependentFirstLaunchTest.php \
  tests/Feature/Tenancy/AcademicContextTenantTest.php \
  tests/Architecture/SubscriptionRetirementArchitectureTest.php
```

Result: 17 tests, 82 assertions; 16 passed and one existing architecture assertion
failed. The failure expects the case-sensitive string `Compatibility` in
`SubscriptionController`, whose current comment uses lowercase `compatibility`.
This is a source-text assertion failure, not evidence of a new billing regression.

Additional baseline command:

```sh
php vendor/bin/phpunit \
  tests/Feature/ResearchOnboardingExperienceTest.php \
  tests/Feature/MonetizationRuntimeBillingTest.php \
  tests/Feature/Security/SubmissionTenantPolicyTest.php
```

Result: 11 tests, 66 assertions; all passed.

Temporary isolated audit probes additionally exercise the current faculty mismatch,
verified-affiliation downgrade and cross-department/role request-deduplication
behavior. These probes document existing gaps; they are not acceptance tests for
the requested enhancement. All three probes confirmed the behavior (3 tests,
7 assertions). Their source is kept outside the application repository at
`/private/tmp/DepartmentAdoptionAuditProbeTest.php`.

## Implementation sequence once the requirements are complete

1. Consolidate hierarchy, role, adoption-state and review-transition validation in
   the existing adoption service; add regression tests for the confirmed gaps.
2. Extend existing application records and forms with catalogue discovery,
   missing-institution requests, official contacts, classification and private
   verification evidence. Preserve public independent registration.
3. Extend account affiliations/access requests with a consistent membership
   lifecycle, secure invitation acceptance by existing accounts, availability
   notifications and revocation that is enforced at authorization boundaries.
4. Extend existing department administration, module resolution and commercial
   account/request scopes. Preserve institution and personal billing behavior.
5. Implement the specified institution-integration decision and history while
   retaining department/user/content IDs and existing academic relationships.
6. Verify sibling-department isolation, cross-institution denials, invitation
   expiry/replay/email binding, review races, revocation, retained personal work,
   commercial reservations/refunds and repeatable integration. Exercise both web
   and API entry points and all three onboarding paths.
