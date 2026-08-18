# AcadFlow Database & Data Model Guide

**Current source snapshot:** 2026-08-15  
**Database target:** MySQL recommended by `.env.example`

This document is a domain map. The exact schema is defined by `database/migrations/` and Eloquent models; do not trust an old static field table over the migrations.

## Main model domains

### Identity / institution

`User`, `University`, `Faculty`, `Department`, `AcademicSession`, `Semester`, `UserOnboardingState`, `AuditLog`.

### Courses / enrolment

`Course`, `Enrollment`, `LecturerCourseAssignment`, `CourseInvitation`, `CourseMaterial`, `MaterialAccessLog`.

### Assignments / submissions

`SubmissionTask`, `SubmissionTaskRequirement`, `SubmissionTaskAttachment`, `Submission`, `SubmissionVersion`, `SubmissionComment`, `SubmissionExtension`, `LateSubmission`, `SubmissionGrade`, `SubmissionRubric`, `PlagiarismCheck`, `PlagiarismMatch`.

### Attendance

`AttendanceSession`, `AttendanceRecord`.

### Groups / discussions / engagement

`Group`, `GroupMember`, invitations/join requests/tasks/resources; `Discussion`, `DiscussionReply`, `DiscussionTag`; shared engagement models for comments/reactions/mentions/reports/shares/subscriptions/threads.

### Research Studio

`ResearchProject`, `ResearchProjectMember`, `ResearchSection`, `ResearchSectionAuthorship`, workflow definition/instance/stage/transition log, research tasks/action items/milestones/meetings/reminders/corrections/amendments/datasets/archives/literature notes/templates/types/specialized links.

Specialized research models include SIWES placements/logs/attendance/evaluations, seminars/panels/questions and defense models.

### Knowledge Hub

`KnowledgePublication`, categories/tags/bookmarks/citations/follows/moderation reports, creator/reputation models, communities/members/invitations/posts/polls, learning paths/enrolment/progress, reading lists, academic events/registrations/reminders/invitations, challenges/entries/judges/scores/votes/team members, certificates/achievements, scholarly records/integrations and external citation records.

### AI / discovery

`AiAnalysis`, `AiUsageLog`, `AiPromptVersion`, `AiGroundingSession`, `AiGroundingSource`, `SearchDocument`, `SearchChunk`, `Recommendation`, `DiscoveryEvent`.

### Billing / commerce

`SubscriptionPlan`, `Subscription`, `UserSubscription`, `Invoice`, `Payment`, `PaymentGateway`, `Coupon`, `CouponRedemption`, commerce orders/items/entitlements/refunds/revenue allocations, wallet accounts/ledger entries, payout accounts and withdrawals.

### Notifications / media / documents

`Notification`, `NotificationLog`, `NotificationSetting`, `PushSubscription`, `MediaAsset`, `MediaAccessLog`, `SecureDownloadToken`, `DocumentTemplate`, `GeneratedDocument`, content document/version/comment models.

## Migration rules

- treat migrations as append-only production history;
- preserve existing rows/settings;
- use explicit short index names;
- run `scripts/check-mysql-identifiers.php`;
- do not use destructive migrations for normal upgrades;
- use nullable/default-compatible transition steps for live data;
- document data backfills and deployment ordering.

## Seeder rules

Normal seeders create missing defaults and preserve matching existing data. Run `scripts/check-idempotent-seeders.php`. Explicit synchronization commands (such as academic catalogue sync) are separate from normal seeding behavior.

## Cache/session/queue tables

Laravel database-backed cache/session/queue infrastructure is supported. Required table names such as `cache` and `cache_locks` must not be blank. Redis may replace cache/queue in production without requiring removal of database queue support tables.
