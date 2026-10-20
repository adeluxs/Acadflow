# AcadSyntra Progressive Institution Adoption — Implementation

This release implements the Institution Claiming, Independent Adoption & Institutional Migration master plan on top of AcadSyntra's existing onboarding, tenant, monetization and AI systems.

## Delivered

- Search-first institution onboarding and catalogue claiming.
- Backend duplicate-institution protection at submission and approval.
- Institution adoption lifecycle separate from the legacy `is_active` catalogue usability flag.
- Platform Admin claim verification, request-for-information, rejection and existing-record linking.
- Department-first adoption with Platform Admin verification and department-scoped administration.
- `user_institution_affiliations` as the authoritative self-declared/pending/verified affiliation layer.
- Independent student/lecturer/researcher usage before full institution adoption.
- Institution access requests that preserve the same user account and history.
- University Admin and Department Admin affiliation-review queues with strict scope enforcement.
- Platform Admin-only administrator elevation.
- Verified institution email-domain registry with cross-institution ownership protection.
- Safe student-import reconciliation: existing accounts are verified/attached instead of duplicated.
- Institution-sponsored paid features and AI require both an officially active institution and verified affiliation.
- Personal wallet purchases/entitlements remain separate and continue to work.
- Independent departments migrate in-place to `institution_managed` when the institution officially activates.
- API/mobile endpoints for institution search, affiliation status and access requests.
- Dry-run-first legacy classification command: `php artisan acadflow:institution-adoption-migrate` and `--apply`.
- Audit and in-app notification hooks for sensitive adoption/verification actions.

## Deployment order

1. Back up the production database.
2. Deploy code.
3. Run `php artisan migrate --force`.
4. Run `php artisan acadflow:institution-adoption-migrate` and review the dry-run counts.
5. When satisfied, run `php artisan acadflow:institution-adoption-migrate --apply`.
6. Run `php artisan optimize:clear`.
7. Run `php artisan test` in the deployment environment.

## Important behavior

`universities.is_active` remains a catalogue/workspace usability field for backward compatibility. `universities.adoption_status` is the authoritative answer to whether the institution has officially adopted AcadSyntra. This keeps independent users functional while an institution claim is pending or setup is in progress.

Institution sponsorship is intentionally unavailable to self-declared users. Verification never deletes or recreates a user's account, wallet, history, publications, submissions or other personal data.
