# Campus ambassador programme

The programme supports course reps, student leaders and student volunteers through applications, invitations, activity reports and reviewed rewards. It does not grant academic or institution administration privileges.

## Where to start

- Public programme page: `/ambassadors`, linked from the public main menu (desktop and mobile), the footer and a homepage promotion section. All three disappear when the **Campus ambassadors** feature is disabled.
- Join link: `/ambassadors/join`. Signed-in users go straight to the application; guests go to registration (or sign-in with `?account=existing`) and return to `/ambassador` after sign-in. After registration and onboarding, the dashboard shows a "Finish your Campus Ambassador application" link for the rest of that session.
- Student workspace: `/ambassador`, or **Campus Ambassador** in the sidebar.
- Super Admin workspace: `/admin/ambassadors`, or **Campus Ambassadors** in the sidebar.

Students complete account onboarding, submit their campus details and accept the programme responsibilities. Super Admin reviews the application and leaves a note visible to the applicant. Approved ambassadors receive a referral link, a WhatsApp sharing message with reward disclosure, a starter guide and activity reporting tools. Rejected applicants can revise and resubmit; suspended ambassadors cannot bypass suspension by reapplying.

## Application lifecycle

Statuses: `pending` (shown as "Submitted · under review"), `approved`, `rejected`, `suspended` and `withdrawn`. Only student accounts may apply; lecturer accounts see an explanation and administrators are refused. The form pre-fills institution, faculty, department, level and phone from the student's profile. The application's `university_id` is set only from an approved institution membership at submission time, never from form input; self-declared institutions (including institutions not yet on AcadSyntra) are kept as text. A pending application can be withdrawn by its owner. Rejected and withdrawn applications can be edited and resubmitted; suspended ambassadors cannot reapply. Status, review fields, referral code and institution link are not mass-assignable. Every submission, withdrawal and decision is audited.

## Programme activities

Super Admin manages activities at `/admin/ambassadors/activities` (title, type, summary, instructions, optional HTTPS resource link, optional start/end dates, "evidence required", open/closed). Approved ambassadors see open activities within their date window on their dashboard and can link a report to one. A closed or expired activity cannot be chosen; an activity that requires evidence needs a supporting link or file. Publishing an activity creates no reward. Creation and changes are audited.

Report evidence files: JPG, PNG, WebP or PDF up to 5 MB. The type is decided by content sniffing, not the extension. Files are stored on the private `local` disk under `ambassador-evidence/{application uuid}/` with random names, and are served only to the reporting ambassador and Super Admin with `nosniff`, `private, no-store` and a sandboxed Content-Security-Policy (PDFs download). A retried submission with the same key stores nothing new. External evidence links are shown as untrusted external links.

## Referrals and reporting

`/r/{code}` redirects every guest to registration. Only an approved, active ambassador’s code is remembered; unknown and suspended codes get the same redirect without attribution, so the link reveals nothing about which codes are active. The first referral is kept in the browser session for up to 30 days, subject to the normal session lifetime. Existing accounts are never retrospectively attributed. Approval and account activity are checked again at registration. Expired, unknown or suspended referrals do not prevent account creation, but receive no credit. API registration also accepts an optional 16-character `referral_code`.

Registration and attribution share a database transaction. Each referred user has at most one referral record, enforced by a unique constraint. Invitations are not click counters, and creating another account is not evidence of useful participation.

Ambassador dashboards display aggregate counts without revealing referred users’ names, emails or private activity. Super Admin can see account identity and review evidence in the restricted referral queue:

- **Referred signups:** attributed registrations.
- **Activated referrals:** completed onboarding followed by activity accepted by the existing foreground activity recorder. This is a product engagement proxy, not proof of study or learning. Referrals may use any supported account path.
- **Returned in days 7–30:** an accepted activity at least seven days and at most thirty days after registration, after onboarding.
- **Reviewed qualifications:** active, email-verified accounts with an admin qualification decision. New signals can still block rewards until re-review; this count is not a promise of payment.
- **Paid buyers:** referred users with a non-zero marketplace order currently marked `paid`, paid after registration. Refunded, partially refunded, unpaid and zero-value orders are excluded. This does not include wallet top-ups or every type of platform purchase.

Inactive accounts are excluded from activation, retention and paid-buyer counts. Results are cumulative; newer referrals have not necessarily had time to qualify. Activation/retention milestones persist on referral records, so notification analytics cleanup does not erase them. This release measures the existing browser foreground activity flow; it does not infer retention from background polling or API calls.

Activity reports include a type, date, attendance estimate, summary and optional HTTPS evidence link. Attendance is explicitly self-reported and separate from measured referral results. Submitting the same report token again does not create another report. Only Super Admin can approve or reject a report; the review note is visible to its author.

## Fraud controls and qualification

Every new and existing referral starts **pending**. A Super Admin must qualify it before it can back a performance reward. Qualification requires all of:

1. A real `email_verified_at` value, even when platform-wide email verification is optional.
2. An active account with completed onboarding and at least seven days since referral registration.
3. Saved study actions on at least three distinct UTC dates within the first 30 days, including an action at or after the seven-day boundary.
4. A documented admin review. Risk flags require explicit acknowledgement after investigation; the admin cannot review their own account or referral.

Only successfully saved study notes, reflections, and practice quiz submissions count. Text must contain at least 80 characters and 12 whitespace-separated words after normalization. A quiz also needs at least three non-empty answers to actual stored questions. Quiz grading remains local and does not require an additional AI call. Page views, login, API polling, failed submissions, empty work and payment alone cannot qualify an account. A keyed hash of normalized content prevents counting identical work twice for the same referral. At most one action counts per date (up to 31 dates across the 30-day interval). A same-day duplicate or retry cannot increase the count. Counts persist independently of general analytics pruning. No private note or reflection body is copied into referral evidence or shown to ambassadors.

A referred user can open **Campus Ambassador → Your invitation** to see study-day progress and request a signed email verification link. This resend path remains available when platform-wide verification is optional and is limited to three requests per hour. The normal mail queue worker must be running. An unverified referred user also gets a dashboard link to this screen.

The restricted review queue shows live risk signals:

- Multiple referred accounts using the same random browser cookie, including across different ambassadors.
- A referral browser also used to submit an ambassador application.
- Repeated signup mailboxes, including Gmail dot/plus aliases and `googlemail.com` aliases. Other providers are not assumed to share Gmail semantics.
- Five or more referred signups from the same network within a fixed UTC clock-hour bucket.
- A mailbox matching the referring ambassador, changed signup email, or missing historical/API browser evidence.

These are investigation signals, not automatic bans: a campus may share devices or Wi-Fi. Super Admin must independently establish that the account belongs to a separate student and record the basis for approval. A submitted review with an outdated evidence signature is rejected. Changed risk evidence after approval blocks a linked reward until a fresh review is completed. The **Qualified** filter lets admins revisit approved referrals; **Hold for review** and **Disqualify** block pending rewards. Past fulfilled payments remain in the audit history and are not automatically clawed back.

The first-party cookie is encrypted, HttpOnly, SameSite=Lax, secure on HTTPS, and lasts up to 90 days. Referral tables store keyed hashes of browser, email and hourly network signals, not raw network addresses or browser tokens. Existing security/audit logs follow their own logging policies. The cookie notice and registration/application disclosures describe this processing. Evidence remains attached to programme records; there is no new automatic retention-pruning job. Rotating `APP_KEY` changes signal digests and invalidates old review signatures, requiring review again.

These controls raise the cost of account farming; they do **not** prove a unique person. Separate mailboxes, different browsers, cleared cookies, VPNs and fabricated but varied study work can evade individual signals. Phone ownership, student IDs, institution identity checks, device fingerprinting, automated bot challenges and bank-account matching are not implemented. Admin identity checks remain necessary before paying meaningful rewards.

## Rewards

There is no automatic commission rate or promise of payment. Super Admin records a reward with an amount, currency, reason and repeatable award reference (for example `october-2026-demo-1`). Award references are unique per ambassador. Replaying an identical award returns the existing record; changing its details under the same reference is rejected.

- **Study wallet credit:** a pending award is fulfilled through the existing spending-wallet service. Currency must match the recipient wallet. The ledger records a marketing expense against the spending-wallet liability. This is spendable platform credit, not a cash withdrawal.
- **Data allowance / performance bonus:** the administrator sends the payment separately, then records its delivery reference. The application does not initiate a bank or mobile-data transfer.
- **Certificate / recognition:** a zero-value award records agreed recognition. The administrator supplies the certificate or recognition separately and records its delivery reference; this release does not generate certificate PDFs.

**Performance bonuses must link one qualified referral from the same ambassador.** Wallet credits and other rewards may also be linked to a referral; the same checks then apply. One referral can back only one pending or fulfilled award, regardless of award reference or reward type. Cancelling a pending award releases that referral for a replacement while preserving the cancelled record. Independent contribution awards remain discretionary and require their own supporting evidence; they must not be used to bypass referral checks.

Qualification, email ownership, account activity, current risk evidence and existing claims are checked again at fulfilment. The admin reward screen hides the delivery action when referral checks fail. For external payments, review the current screen before sending money; the app cannot block a bank transfer made outside the system.

Fulfilment locks the application and award inside a transaction. A retry of an already fulfilled award cannot credit the wallet twice. Suspended ambassadors, inactive recipients, restricted wallets and currency mismatches cannot receive wallet fulfilment. Only pending awards can be cancelled. Application reviews, report reviews, awards, fulfilment and cancellation use the existing audit log. Neither students nor institution administrators can manage programme rewards.

## Operations and performance

Apply migrations `2026_10_27_120000_create_campus_ambassador_programme.php`, `2026_10_31_120000_add_ambassador_referral_review.php` and `2026_10_31_150000_add_ambassador_activities_and_institution_links.php` (additive only: new nullable columns and an `ambassador_tasks` table), then refresh configuration/routes when deploying. It adds four programme tables and a non-public, non-mass-assignable referral marker to users. The **Campus ambassadors** feature in existing feature settings controls public/applicant routes; Super Admin can still manage existing records when it is disabled. No new third-party service, queue, frontend bundle or scheduled job is required. Verification uses the existing mail queue. The second migration adds review/signals columns, a bounded study-day table and reward-to-referral links. Legacy referrals are not auto-qualified or backfilled from page visits. Those already outside their 30-day window cannot earn a new qualification without existing qualifying study evidence. Legacy pending performance bonuses without a referral must be cancelled and reissued against a qualified referral; fulfilled awards remain unchanged.

Lists are paginated (20 applications; 10 referrals, activity reports and rewards per page). List relationships are eager-loaded or counted in SQL. Referral metrics execute one aggregate query, regardless of referral count. Registration without a referral performs no ambassador queries. Existing activity recording is throttled to five-minute intervals; ordinary users perform no additional ambassador query, while referred users with completed onboarding execute at most one conditional milestone update per accepted interval. Review evidence uses indexed correlated counts and batched relationships: four queries for either 1 or 10 referrals, plus the pagination count on the page. Linked reward evidence is also batched for at most 10 rewards. Ordinary study saves incur zero referral queries; referred study saves within the qualification window use a referral lookup and a deduplicated insert. Mutable permissions and wallet balances are not cached.

## Verification

```sh
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/AmbassadorProgrammeTest.php tests/Feature/AmbassadorAntiFraudTest.php tests/Feature/AmbassadorProgrammeCompletionTest.php
```

`AmbassadorProgrammeCompletionTest` covers homepage/menu visibility and the feature switch, the state-aware call to action, the join redirect through sign-in, profile pre-fill and the institution link, student-only eligibility, withdrawal and resubmission, activity management authorization and date windows, evidence-required rules, content-sniffed private evidence uploads and access control, admin filters and the audit trail, and referral links that do not reveal code validity.

The feature suite covers application approval and role preservation, rejected/suspended states, web and API attribution, expiry, owner privacy, institutional admin exclusion, report deduplication, reward deduplication, actual wallet posting, external delivery references, cancellation, currency rollback, milestone persistence after analytics cleanup, and constant metrics query count.

Verification on 31 October 2026: the ambassador, anti-fraud, email verification, authentication, study tools and public website suites passed together (91 tests / 701 assertions). An additional browser-cookie integrity test passed separately (1 test / 10 assertions), for 92 feature tests / 711 assertions in total. The full browser walkthrough passed at 320px, 390px and 1440px, covering invitation progress, referral qualification, report review, wallet credit, linked performance-bonus delivery and ambassador privacy. The anti-fraud migration is applied locally and configuration/routes were refreshed. Tests used isolated SQLite databases; live mail delivery and concurrent production database load were not exercised.

The query-count checks measured one referral aggregate query with both 1 and 31 referrals, four batched evidence queries with both 1 and 10 referrals, and zero tracking queries for unreferred registration/activity/study calls. These are bounded local checks, not production load or concurrent MySQL throughput measurements.

For browser checks, use a separate SQLite database under `/private/tmp/acadsyntra-ambassadors-*`, `APP_ENV=testing`, isolated framework cache paths, database sessions and an array mailer. Run `tests/Browser/prepare-ambassadors.php`, start the app with `php artisan serve --host=127.0.0.1 --port=8027 --no-reload` (the flag preserves the isolated environment), then run `node --test tests/Browser/ambassadors.test.mjs`. The preparation script refuses to run against the normal application database.

The anti-fraud suite covers mandatory email verification with the global switch off, signed-link completion, three-day and seven-day gates, saved study actions, repeated content, cross-ambassador browser reuse, mailbox aliases, network bursts, explicit risk acknowledgement, stale reviews, changed evidence before fulfilment, duplicate reward claims, cancellation/replacement, legacy bonuses, privacy and bounded query counts. Student study tests also exercise quiz retry handling and reflection counting when optional AI feedback fails.
