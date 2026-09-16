# Institution Commercial Account UX

## Purpose

AcadFlow separates commercial **requests** from commercial **authority**. University/institution administrators can understand their institution's approved commercial arrangement and ask for changes, but they cannot directly change prepaid balances, pricing, sponsorship or platform financial terms. Platform/Super Admin remains the authoritative commercial operator.

## Institution Admin flow

1. Open **Commercial Account** from the admin sidebar/dashboard.
2. If no account exists, submit **Request commercial account setup**. This creates only a review request; it does not create funding or a financial obligation.
3. Once active, view:
   - commercial account status and currency;
   - available prepaid commercial budget;
   - institution member count;
   - AI usage/request metrics;
   - institution-funded AI spend;
   - sponsored paid features;
   - department-admin commercial limit;
   - optional student semester fee and invoice grace period.
4. Submit one of the guided requests:
   - **Add prepaid budget**;
   - **Change limits or billing terms**;
   - **Change sponsored AI/features**.
5. Track each request as pending, approved or rejected and read Platform Admin's review note.

## Platform Admin flow

1. Open **Admin → Monetization**.
2. Review the **Commercial requests awaiting review** queue.
3. Choose **Review & apply terms**. AcadFlow locks the institution context so the request cannot accidentally update another institution.
4. Review the guided sections:
   - Institution & account status;
   - Prepaid budget & limits;
   - Sponsored AI and paid features.
5. Approve and apply the terms, or reject the request with a reason visible to the institution.
6. Any prepaid-balance adjustment continues to be posted through the existing double-entry audit ledger.

## Security and accounting rules

- Institution Admin request endpoints never increment/decrement or directly update commercial balances.
- Requests are throttled and duplicate open requests of the same type are blocked.
- Commercial-account creation/update remains Super Admin only.
- The institution ID is server-validated and locked during request review.
- Sponsored features are allowlisted through `config/monetization.php` and are recognized by the central `EntitlementService`.
- A paused commercial account does not sponsor paid feature access or AI usage.
- Request history is preserved in `commercial_account_requests`; applying an approved request changes the real commercial account separately.

## New/changed files

- `app/Http/Controllers/InstitutionCommercialAccountController.php`
- `app/Models/CommercialAccountRequest.php`
- `database/migrations/2026_08_31_150000_create_commercial_account_requests_table.php`
- `resources/views/institution/commercial-account.blade.php`
- `app/Http/Controllers/Admin/MonetizationController.php`
- `resources/views/admin/monetization/index.blade.php`
- `app/Services/Commerce/EntitlementService.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/dashboard/admin.blade.php`
- `resources/views/settings/index.blade.php`
- `scripts/check-commercial-account-ux.php`

## Deployment

After deployment and dependency installation, run normal production migrations so `commercial_account_requests` is created. Existing commercial accounts are not modified destructively by this migration.
