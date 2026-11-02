# Knowledge Hub creator economy and storytelling

This release extends the existing Knowledge Hub. It keeps publication URLs, creator ownership, research links, moderation history, orders, entitlements, wallets and ledger records intact.

## Audit matrix

| Capability | Audit result | Existing/reused location | Action |
|---|---|---|---|
| Publication drafts, moderation, versions and scheduling | Working | `KnowledgePublicationController`, `PublicationService`, content documents/versions and policies | Preserved and extended |
| Categories and publication formats | Incomplete | `KnowledgeCategory`, legacy `content_type` | Added admin-managed areas, hierarchy metadata and distinct `KnowledgePublicationType`; legacy values are backfilled and retained |
| Research credibility and provenance | Working | moderation reports, plagiarism checks, citations, DOI/research links | Preserved; academic labels remain separate from popularity, price and advertising |
| Secure files and previews | Working | media assets and signed download flow | Preserved |
| Creator profiles and verification | Working | creator profile, verification and reputation models | Reused for publishing identity |
| Likes/comments/bookmarks/follows/views | Working | engagement models, `KnowledgeEvent`, notifications | Reused with backend feature toggles |
| Search and recommendation | Working with narrow taxonomy | `KnowledgeDiscoveryService`, recommendation/search services | Expanded to area, format, access and recency filters; feeds remain paginated |
| Series, chapters and reading progress | Missing | No prior series domain | Added series around existing publications, ordered chapters, progress, following and navigation |
| Paid publication access | Working | shared commerce orders, verified gateway flow and entitlements | Reused for premium publications and chapters; duplicate ownership blocks repeat charges |
| Creator eligibility | Missing | Creator profiles/payout accounts existed | Added reviewed applications, ownership declaration and suspension enforcement |
| Earnings, refunds and withdrawals | Working | commerce allocations, wallet ledger, hold/release, refunds and payouts | Preserved; 85/15 is snapshotted per sale and existing admin settings remain authoritative |
| Paid pushing/boosting | Missing after repository-wide audit | Admin feature/pin placement existed, but no purchasable promotion plan, campaign or payment activation existed | Added one promotion implementation using shared commerce; admin feature/pin placement remains an editorial control |
| Publisher dashboard | Incomplete | Publication pipeline and commerce wallet were separate | Added bounded publication, reader, series, sale, balance and campaign summaries with links to the existing wallet |
| Runtime controls | Incomplete | Central settings existed | Added independent backend-enforced Knowledge Hub controls |
| View-based creator payouts | Future/optional | None | Deliberately not added; initial monetization is purchase based |

## Publishing and serialized stories

The editor selects a database-managed publication type separately from a subject category. Academic fields appear only for academic formats; story metadata, audience and content warnings remain optional for relevant creative work. All rendered rich text continues through the existing sanitizer and versioned content document.

Creators can create a series, add existing Knowledge Hub publications as ordered chapters, mix free and premium chapters, and set a configurable minimum premium price. Readers get a table of contents, previous/next navigation and per-account progress. Purchased chapters use the existing entitlement table and cannot be charged twice.

## Selling publications

Free publishing remains available while its independent toggle is enabled. To sell new content, a creator submits the monetization application from Creator Profile, confirms ownership/permission and terms, and may select a verified payout account. An administrator approves, rejects or suspends the application. Publication moderation remains a separate decision.

The selling price is read from the stored publication. The server verifies publication state, creator eligibility and ownership attestation before creating an order. Gateway callbacks and webhooks grant access only after provider verification; wallet settlement uses the same transactional order completion. Each order stores minor-unit totals and each allocation stores its percentage and amount, so changing the default 15% commission never recalculates historic sales.

Earnings follow the existing pending hold, release, available balance, refund reversal and withdrawal workflows. Knowledge purchases and creator earnings are separate from subscriptions, tool packages and AI allowances.

## Paid promotion

Admins create, price, disable and reorder promotion packages. A creator selects a public published work and package, then pays through the shared wallet or configured gateway. Verified completion activates the campaign for the package duration. The Hub records bounded sponsored impressions and click-throughs, shows the word **Sponsored**, and states that placement is not academic endorsement. Expired campaigns leave delivery automatically, and a full refund cancels delivery. Admins can pause, resume or cancel campaigns.

The implementation uses `KnowledgePromotionCampaign` as a shared-commerce purchasable. Promotion revenue has no creator seller allocation and is posted as platform revenue through the existing ledger. Price and duration are snapshotted on the campaign/order; package edits affect future campaigns only.

## Administration and operations

Use **Admin → Knowledge Admin** to manage publication types, subject categories, creator applications, promotion packages/campaigns and runtime controls. Use the existing monetization administration for commission, financial reconciliation, refunds, payout verification and withdrawals.

Deploy application code and both migrations together, then run:

```sh
php artisan migrate --force
php artisan optimize:clear
npm ci
npm run build
php artisan optimize
php artisan queue:restart
```

On this workstation use `/Applications/MAMP/bin/php/php8.5.2/bin/php` for Artisan. Production must keep the existing notification/default queue workers and scheduler running. The migrations are additive. Rollback drops the new promotion records first, then series/types/eligibility tables and added publication/category columns; take a database backup before rolling back a release that has live sales or reader progress.

## Performance and security notes

Public feeds and management tables are paginated or bounded. Cards select short document previews rather than full manuscripts, dashboard summaries run aggregate queries, admin selectors are capped, campaign delivery uses indexed status/time columns, and sponsored impression updates are batched. Policies and creator ownership scope all author actions. Prices, seller identity, duration and commission are server-derived. CSRF, gateway verification, idempotency, row locks, entitlement uniqueness, private media rules and the auditable ledger remain in force.

The initial release does not add anonymous monetized stories, publisher subscriptions, tips, audio/video, institutional licences or engagement-pool payouts.
