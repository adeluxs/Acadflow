# AcadFlow Institution Onboarding Release

## Implemented journey
1. University Representative submits an institution onboarding request.
2. Platform Admin reviews the request.
3. Approval creates/links the institution and promotes the requesting representative to University Admin.
4. Platform Admin completes guided setup: academic structure and commercial terms.
5. University Admin receives a dedicated institution setup checklist.
6. University Admin manages faculties, users, courses and Commercial Account requests.
7. Institution is activated only after a University Admin and academic structure exist.

## Commercial boundary
University Admins can view commercial information and submit requests. Platform Admin/Super Admin applies financial terms, funding changes and sponsorship decisions.

## User onboarding
Institutional users continue through normal personal onboarding while preserving the verified institutional affiliation.

## Validation
- Full PHP syntax sweep on the release tree.
- Route/controller target validation.
- New onboarding view route-reference validation.
- Composer/Laravel integration tests require deployment dependencies (`vendor`).
