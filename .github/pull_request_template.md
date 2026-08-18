## AcadFlow change summary

Describe the user/developer problem and what changed.

## Scope / architecture

- [ ] Reused existing services/settings/routes before adding new ones
- [ ] Tenant/role/policy boundaries reviewed
- [ ] Feature Management integration reviewed
- [ ] AI changes use the central `AiManager`/`AiRouter` architecture
- [ ] Migration/seeding is production-safe and non-destructive

## Testing

- [ ] Relevant PHPUnit tests added/updated
- [ ] Relevant `scripts/check-*.php` preflights pass
- [ ] `php scripts/check-documentation.php` passes
- [ ] Frontend build checked when frontend changed

## Documentation / changelog

- [ ] `docs/DEVELOPER_GUIDE.md` updated if developer behavior/architecture changed
- [ ] `docs/USER_GUIDE.md` updated if user behavior changed
- [ ] Specialist docs updated where relevant
- [ ] `CHANGELOG.md` updated under **Unreleased**
- [ ] Deployment/migration steps documented

## Data/security review

- [ ] Existing settings/data are preserved
- [ ] No secret/API key is logged or exposed
- [ ] No cross-tenant data path introduced
- [ ] Upload/download/AI context uses authorized data only
