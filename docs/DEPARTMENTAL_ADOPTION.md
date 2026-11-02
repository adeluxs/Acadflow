# Departmental adoption and academic affiliations

AcadSyntra uses its existing institution directory, adoption requests,
`UserInstitutionAffiliation`, role policies, feature registry and Commerce engine.
An institution's directory identity is separate from platform adoption. Independent
users and independent departments can start before their parent institution adopts.

## Getting started

Public registration and account onboarding offer Student, Lecturer/Supervisor,
Researcher, Register My Department and Register My Institution paths. Choosing a
representative path never grants administrator permissions. Representatives can
complete personal onboarding before requesting a missing institution.

- **Register my department:** `/department/onboarding`. Four steps cover academic
  identity, official representation/evidence, adoption arrangement and review.
  Text fields and the current step autosave to the applicant's own draft. A file
  is uploaded only at submission, stored privately on the local disk and reviewed
  through an owner/Platform Admin protected evidence endpoint.
- **Missing institution:** the same wizard can submit a directory-only request
  through the existing institution review queue. Approval adds the identity;
  it does not subscribe the institution or appoint an institution administrator.
- **Platform review:** existing **Admin → Institution onboarding** approves the
  department, links or creates the validated faculty/department and records a
  verified department-administrator membership. Approval is transactional and
  cannot be replayed. New codes are generated when no code was supplied.
- **Administration:** switch to the approved membership in **My Academic
  Affiliations**, then open **Department**. Manage approved members, invitations,
  access requests, courses, academic periods, reports, modules and commercial terms.
  A new department creates its academic period before enrolling/assigning people.

The existing institution-claim, setup and activation workflow remains available.
Verified institution administrators can access guided setup before activation.
Institution activation identifies independent departments and sends an availability
notice, while leaving their authority, records and commercial balances intact.

## Existing accounts, invitations and context

**My Academic Affiliations** (`/academic/affiliations`) lists memberships and lets
users switch to an approved organisation or their personal workspace. Discovery
and access requests are at `/institution/access`. Search uses the existing directory
and names, never manually entered internal IDs.

Membership approval extends the original account. Further memberships do not replace
its primary profile, personal research owner IDs, course ownership or documents.
User roles are evaluated in the selected approved organisation for each request.
The context projection is transient: updating a password or profile does not persist
another organisation's selected role or IDs.

Ordinary memberships are reviewed by an authorised administrator in that scope.
Administrator appointments require Platform Admin verification. Invitations use
hashed random tokens, an email-bound recipient, verified account email, expiration,
revocation and single-use acceptance. An existing user accepts with their existing
account. A recipient without an account registers and verifies that invited email
first, then opens the link. The UI provides the secure link for sharing, and existing
users receive an in-app notification. Legacy user-invitation actions delegate to
this workflow. An institution administrator's department-admin nomination remains
pending until Platform Admin approves it.

Access requests expose pending/approved/rejected outcomes. Membership records retain
self-declared, pending-verification, verified, rejected, revoked, graduated or
transferred history; **Verified** is displayed as **Approved**. Expired or revoked
memberships cannot become the active context or sponsor access. Removing the final
organisation administrator requires a replacement or Platform Admin action.
Graduation and transfers end a membership without deleting its account or history;
joining the new organisation uses a separate reviewed membership.

An email domain or self-declared directory affiliation is never an approval.
Profiles can still display personal academic identity independently of permission.
Independent research remains restricted to explicit project participants, even
when the owner's department joins an institution. Existing explicit classroom
participation remains available; revoked memberships do not reopen organisational
course access merely because an old enrolment row still exists.

## Two-party department/institution affiliation

An active institution administrator discovers independent departments attached to
that institution's directory identity under **My Academic Affiliations**.

1. The institution proposes affiliation with explicit academic-data access,
   institution-feature inheritance and billing preference.
2. A current department administrator approves the proposed terms or declines.
   Platform Admin cannot supply consent on the department's behalf.
3. Platform Admin verifies both current administrator approvals. The department
   becomes `institution_affiliated`; its original adoption date and all IDs remain.
4. Either organisation can end an active affiliation. The department returns to
   `independent_active`. The agreement and audit trail remain in history.

States are `pending_department → pending_verification → affiliated`, with
`declined`, `withdrawn` and `ended` alternatives. Request/department rows are locked
for review. Cross-institution claims, replayed approvals and revoked representatives'
consent are rejected. Affiliation never deletes, copies, reparents or recreates
students, lecturers, courses, research or membership records.

A department can retain academic-data control after affiliation. Institution
administrators only gain department management when the approved agreement allows
it. Independently owned research is outside this organisational management grant.
The academic hierarchy remains Institution → Faculty/School → Department; operational
affiliation is a separate agreement rather than a second directory hierarchy.

## Plans, features and billing ownership

Recurring subscriptions were retired by the existing monetization rebuild; these
changes do not restart them. Historical subscription/payment records remain intact.
`config/academic_adoption.php` defines Core and Sponsored adoption arrangements for
both departments and institutions. Platform Admin approves actual commercial terms
through **Admin → Monetization** using existing account requests, pricing rules,
minor-unit balances, double-entry journals, idempotency and reconciliation.

Department approval opens a commercial **request**, not an automatic charge or
subscription. A department account has a unique `department_id` and null
`university_id`; institution accounts retain their existing unique institution ID.
The department's directory parent is resolved through faculty, never by sharing
an institution's prepaid account. Optional `access_ends_at` ends sponsorship without
deleting the account, balance or memberships.

One sponsorship resolver serves fixed features, pay-per-use features and AI:

- An approved membership and active organisation are required.
- An independent department can sponsor its members before institution adoption.
- The agreed billing preference selects department or eligible institution
  sponsorship first. Only one account funds a reservation.
- An expired/paused account can fall back to another eligible agreed account.
  If no sponsor covers the feature, normal personal entitlement/wallet rules apply.
- Affiliation, expiry, upgrades and withdrawal never move prepaid balances, cancel
  personal purchases or create another recurring subscription.
- Release/refund uses the original reservation's commercial account ID even if
  the user's context or organisation agreement changes afterwards.
- Dynamic sponsored grants do not become permanent personal paid entitlements.

Department module preferences extend the existing feature registry. They can pause
or disable department access, but cannot enable a platform-disabled feature or bypass
maintenance/dependencies. Existing administrator preview behavior is retained.
Institution provider credentials and organisation-wide notification channel settings
remain under institution/platform authority; department administrators control their
own modules rather than changing sibling departments' settings.

Department academic periods and invoices are separately scoped. Approved commercial
terms can set a department semester fee. Invoice generation targets approved students
in that department and retains the existing user/semester idempotency rule. New
invoices store their department and original commercial-account identity. Existing
invoices, enrolments and payments are retained.

## API

Protected `/api/v1` routes use the same services and context resolver as the web UI.

- `GET /academic/directory?q=...` or `?university_id=...`
- `GET /academic/affiliations`
- `POST /academic/context` with `affiliation_id` (`0` selects personal context)
- `POST /institution-access` uses the existing reviewed access-request service
- `POST /academic/invitations`; `POST /academic/invitations/{token}/accept`
- `POST /academic/invitations/{invitation}/revoke`
- `POST /academic/memberships/{membership}/end`
- `POST /academic/department-affiliations`
- `POST /academic/department-affiliations/{affiliation}` with an allowed action

Stateless clients send `X-Academic-Affiliation: <approved membership ID>` on academic
requests. Foreign, revoked and expired IDs return 403. `0` selects personal context.
The server never accepts an institution/department header as authority by itself.

Affiliation history responses retain the `memberships`, `affiliations`,
`managedMembers` and `invitations` arrays, now limited to 25 entries each.
The corresponding `pagination` entry provides `current_page`, `has_more_pages`,
`next_page_url` and `previous_page_url`. Follow those URLs to retrieve subsequent
pages. Page parameters are `memberships_page`, `agreements_page`, `members_page`
and `invitations_page`; each list can be navigated independently.

## Performance

The affiliations dashboard paginates histories rather than loading whole tables
or silently dropping older members and invitations. Affiliation candidates use
20 entries per page; invitation department options use 50, with a scoped,
case-insensitive department-name search. API history requests skip these HTML-only
catalogues. Simple pagination avoids total-count scans and uses stable ID ordering
for histories; department options sort by name with an ID tie-breaker.

Directory search and draft recovery share a selector query containing only public
institution, faculty and department fields. Department settings and commercial
terms are excluded from the wizard's embedded directory data. Inactive faculties
and departments are omitted consistently when selecting or resuming an application.

Availability notifications filter approved members and previously notified users
in SQL, then process recipients in batches of 200. Institution activation also
batches administrator lookups and sends one institution notice to representatives
who administer multiple departments. Notifications remain advisory; they never
grant access or change governance. The additive index migration
`2026_10_21_180000_index_academic_adoption_lookups.php` supports recipient/type and
department/status/role lookups without changing existing records.

`AcademicAdoptionPerformanceTest` verifies that 205 new department recipients
need **two recipient reads**, and that nine departments need **three institution
recipient lookups** (excluding existing audit-schema checks). It also checks
pagination reachability, department isolation, private directory fields, fresh
revocation enforcement and index rollback preserving records. Ordinary course
checks avoid an approval query when no revoked/transferred membership exists.
Permissions and financial balances are not cached across requests.

## Deployment and migration safety

The additive migration is
`2026_10_21_140000_extend_academic_adoption_ecosystem.php`. Back up the database and
private evidence storage before deploying; use the normal migration workflow:

```sh
php artisan config:clear
php artisan route:clear
php artisan migrate --force
php artisan view:clear
php artisan queue:restart
```

No frontend build is required for these Blade and server changes. New configuration
and routes must be present before rebuilding deployment caches. Do not run
`migrate:fresh` on application data. Rollback must use a reviewed forward fix or the
pre-deployment backup: the migration refuses a destructive schema collapse that
would discard multi-organisation membership or financial history.

Lookup indexes are created before legacy unique keys are removed, preserving MySQL foreign-key support and efficient membership/calendar queries.

Existing installations should also apply the subsequent lookup-index migration
through `php artisan migrate --force`. It adds indexes only; its rollback removes
only those indexes. No frontend build is required for these optimizations.

The upgrade backfills only existing trusted administrator appointments that lack a
membership record, preserving existing reviewed/revoked records. It never verifies
students, lecturers or matching email domains automatically. The legacy adoption
classification command now uses additive `firstOrCreate` scoped by department;
rerunning it cannot downgrade an approved membership.

The local development SQLite database was backed up under the private,
git-ignored `storage/app/private/migration-backups/` directory before applying the
migration. External/production databases are not touched by local fixture tests.

## Verification

Automated coverage is in `AcademicAdoptionEcosystemTest`,
`Database/AcademicAdoptionUpgradeTest` and the existing onboarding, course,
submission, profile, research, payment and commercial regression suites.

Queued course AI jobs resolve and revalidate the applicable student/lecturer
membership from the course context before generation. Revoked contexts fail before
paid generation, and another primary organisation is never selected as payer.

The browser lifecycle test is `tests/Browser/academic-adoption.test.mjs`. It checks
real wizard autosave/recovery, a 390 px mobile layout, department approval, existing
student invitation acceptance, both-party affiliation, platform verification and
withdrawal. It requires the guarded `tests/Fixtures/academic-adoption.php` fixture
on a local testing server. That script refuses to seed/reset anything except an
explicit `/private/tmp/acadsyntra-adoption-*.sqlite` database in the testing environment.
Set `ACADEMIC_ADOPTION_BROWSER_URL` to that isolated server when running the test.
Browser screenshots go to `/private/tmp/acadsyntra-adoption-browser-shots/`.

Verified on 21 October 2026:

- Consolidated PHPUnit suite: **468 tests, 2,628 assertions passed**.
- Latest membership, directory, course and tenant checks: **52 tests, 205 assertions passed**, including the additional case-insensitive directory-reuse and administrator context-before-binding regressions.
- Browser lifecycle: **passed**, including mobile fit, autosave recovery, approval,
  existing-account invitation acceptance, affiliation/verification and withdrawal;
  **zero JavaScript errors**.
- Private verification-document authorisation and isolated upgrade preservation:
  passed.
- MySQL identifier-length preflight, PHP syntax and source whitespace checks:
  passed. Generated Laravel cache formatting is excluded from source whitespace checks.

Organisation context is resolved before route-model binding so an administrator
with approved memberships in two departments can switch and open the selected
department's courses. Typed school/department names are matched case-insensitively
against the existing directory; approval reuses canonical IDs instead of creating
another record.
