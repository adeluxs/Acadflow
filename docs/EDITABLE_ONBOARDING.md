# Editable onboarding and profile completion

Users can open **Manage Onboarding** from the account menu or **Complete your profile / Manage onboarding** on My Profile. The dashboard also offers a dismissible suggestion. The management page is `/onboarding/manage`; section links use the existing `/onboarding?edit=1&step=N` flow.

## Saving and compatibility

- Web and API saves share `OnboardingService`. Initial setup persists a resumable draft; after completion, each section immediately updates its own profile fields and the saved state in one transaction.
- Existing `onboarding_completed_at`, `onboarding_version`, state records, creator profiles and memberships are retained. Revisiting the final review cannot replay historical drafts, welcome notifications or completion events.
- Current profile values take precedence over historical draft values for completed users. Changing account path does not clear academic affiliations. Requests cannot supply another user's ID to update their state.
- Required identity fields remain validated. Only students see programme/academic level; lecturers also see academic position, specialisation and supervision interests. Position uses the existing creator profile; specialisation and supervision interests use existing onboarding JSON. No migration is needed.
- Privacy and notification preferences save explicitly and also autosave after changes on completed profiles. Channel settings are synchronised without replacing push consent, quiet hours, reminder consent or other notification settings.

## Progress

The percentage is `provided applicable fields / total applicable fields`, rounded to a whole percentage. The wizard cursor is not a completion score. The calculation includes identity, useful optional profile details, affiliation, role-specific interests and privacy/notification choices. A supplied array counts as one field; unchecked notification choices are still valid choices.

Steps distinguish completed requirements, intentionally skipped optional sections and incomplete sections. `mandatory_complete` reports required profile fields; `fully_completed` reports all applicable information. The existing final confirmation/access flag remains compatible. Missing optional fields never revoke an already completed user's access, and a legacy completed user is never forced through registration again.

Department and institution representatives/administrators also see a separate organisation checklist derived from their existing drafts, applications, approved workspace and setup records. It covers verification, structure, invitations and configuration. Organisation approval is independent of personal profile completion. Department configuration forms reuse the existing authorised module/calendar endpoints.

## Academic connections

The affiliation section uses institution search and loads only the selected institution's selector hierarchy. Selecting a directory entry records a **self-declared** affiliation and grants no organisational permissions. Missing affiliation details can be added later; established identity changes go through My Academic Affiliations and verified membership management.

The management page links to existing membership requests, approval status, secure invitations and workspace switching. These retain existing accounts and academic history. `institution_self_service` still controls whether new requests are available; this feature defaults to disabled in the existing configuration. The management interface respects that setting. No feature or verification policy is enabled automatically.

## Reminders and operations

The existing hourly onboarding campaign now identifies the first genuinely missing required section, rather than reminding about an optional step at the cursor. Completed users with optional gaps do not receive abandoned-personal-onboarding reminders. Users whose required profile fields are ready receive no personal reminder while awaiting final confirmation. Existing organisation setup reminders remain separate.

Optional profile suggestions use the `engagement` category and require **Optional engagement** consent plus personalised recommendations. They begin no earlier than 14 days after completion, send at most once per section, and are separated by at least 14 days across sections. Dismissing the dashboard suggestion also stops optional profile suggestions. Mandatory reminders retain their existing frequency and maximum-count controls.

Both reminder types use the existing email, web push and in-app notification pipeline, channel preferences, quiet hours and delivery limits. Delivery rechecks relevance so queued email/push is cancelled when the relevant requirement or optional section is completed. Previous in-app messages remain part of notification history.

No additional scheduler entry, queue, migration or worker command is required. Follow [notification operations](NOTIFICATION_OPERATIONS.md) for the existing scheduler and `notifications` workers.

## Deployment and stale route cache recovery

Deploy `routes/web.php`, the onboarding controller/service and the updated views together. If `/profile` reports `Route [onboarding.manage] not defined` while the route exists in source, clear the older route cache before rebuilding the release:

```sh
php artisan optimize:clear
npm run build
php artisan optimize
php artisan queue:restart
php artisan route:list --name=onboarding.manage
```

Run these commands from the application directory with the project's supported PHP version. The default shell PHP on this workstation is too old; use `/Applications/MAMP/bin/php/php8.5.2/bin/php` for Artisan. `optimize:clear` removes stale configuration, application, event, route and compiled-view caches; `optimize` rebuilds Laravel's deployment caches. The Vite build regenerates the asset manifest and CSS for the updated Blade templates. The final route check must list `GET|HEAD onboarding/manage` as `onboarding.manage`.

## Verification and performance

`EditableOnboardingTest` covers complete setup, optional skipping and dashboard access, revisiting/editing, canonical values over stale drafts, affiliation protection, existing course/project retention, mandatory completion, reminder cancellation, quiet hours/consent, fresh-device progress, bounded directory responses, lecturer fields, representatives/admins, membership requests/invitations, context revocation, notification preference preservation and read-only progress calculation.

A local SQLite comparison against the previous controller used 25 institutions, each with a faculty and department:

| Section | Previous reads | Current reads | Previous directory records hydrated | Current directory records hydrated |
| --- | ---: | ---: | ---: | ---: |
| Personal profile | 5 | 2 | 75 | 0 |
| Affiliation | 5 | 5 | 75 | 3 |

Search returns at most 12 institutions. Ordinary progress calculation requires a state and creator-profile lookup, creates no state record and has constant query cost as the directory grows. A dismissed dashboard banner requires only the state lookup. Membership/application previews are bounded and application queries run only for relevant roles. No mutable permission is cached across requests. These are local query/record measurements, not production latency guarantees.
