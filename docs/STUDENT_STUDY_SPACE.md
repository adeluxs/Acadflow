# My Study Space

Students can open **My Study Space** from the sidebar or choose **Study this course/material**. The workflow connects a source, a saved explanation, practice feedback and a scheduled return visit.

## Student workflow

1. Choose a published material from an enrolled course, resume saved work, or add a private note linked to a course.
2. Read the source and select a passage. Ask for a simpler explanation, an example or a self-check question. The latest successful explanation is saved with its source and question.
3. Request a short practice quiz based on that source. Answer before checking the answer key. Feedback shows your answer, the expected answer, an explanation and a verified source passage when available.
4. Return through the dashboard’s **Today** panel. It prioritizes an assignment closing soon, then due revision, then saved study work. It also shows practice attempts and correct/total answers from the last seven days.

New explanations and quizzes use the existing AI provider, plan allowance and billing controls. Reopening explanations and retaking a saved quiz make no new AI generation request. Practice scores never alter official grades. Short written answers currently use trimmed, case-insensitive exact matching; a differently worded correct answer may need checking against the source.

## Monetization

**Student Study Tools** is registered in the existing paid-feature catalogue as optional fixed access. It remains free when no active pricing rule exists. A super administrator can publish a price and access duration from **Admin → Monetization → Feature Pricing**; students can then activate it from **Paid Feature Access**, or receive it through a verified institution or department commercial account that sponsors `student_study_tools`.

The access package covers the study workspace, private notes, practice quizzes, flashcards, plans, mastery, deadline coaching, self-assessment, annotations and reflections. It does not block the student’s core courses, attendance, billing or assignment submission routes. AI generation is metered separately through the existing assistant pricing and daily allowance controls. The administrator can set assistant-specific prices for Practice Quiz Generator, Flashcard Generator, Study & Deadline Planner and Highlight Summary. Opening saved work and completing a saved quiz does not create a new AI charge.

Scores below 80% schedule another attempt after one day. A successful first attempt schedules three days; successful recall on a later day doubles the previous interval, up to 30 days. Repeating a successful quiz on the same day does not increase its interval. These are study reminders in the app, not predictions of exam performance. No new email or push reminders are sent by this feature.

## Private notes and source limits

Paste text or upload one readable PDF, DOCX or TXT document, subject to the administrator’s allowed extensions, upload limits and malware scanning policy. Files are limited to 5 MB, and the study view uses the first 20,000 readable characters. DOCX archives are also checked for excessive expanded size or entry count. Scanned image PDFs require text extraction before import; this feature does not perform OCR.

Temporary uploads are private, scanned using the existing media service, extracted and removed with their temporary media record. The derived note remains private in the student’s existing note library. Students can manage/delete it through **My private notes → Manage notes**. Deleting a note removes its connected study session and quizzes/attempts through foreign keys. Existing note deletion rules remain authoritative.

Course enrollment and current institutional membership are checked again when opening or using saved work. Hidden/deleted materials, revoked access and disabled material modules are excluded from the Today panel. Private notes remain usable when the course-material module is disabled, provided course access remains. The courses module controls the study workspace; AI module/feature/mode controls generation. If AI is unavailable, students can still read accessible sources and saved explanations. A failed explanation request keeps the previous successful explanation. If the source changes, an existing explanation is marked as needing refresh.

## Implementation

- `StudentStudyController` handles student-only `/study` pages and form submissions.
- `StudySourceService` checks ownership, course/material access, readable source extraction and selected-passage membership.
- `StudyWorkspaceService` builds scoped, bounded queries and calls `AiManager` for `material_assistant`. `PracticeQuizService` uses `practice_generator` with actual source text.
- `student_study_sessions` stores the source link, latest explanation and source fingerprint. Added quiz fields hold the session and revision dates. Unique attempt submission tokens protect retries from duplicate scores and schedule changes.
- New date fields use nullable `DATETIME` for MySQL compatibility. The migration is restartable and preserves existing courses, notes, quizzes and attempts during upgrade.
- Dashboard queries load at most three saved sessions and five due quizzes; full source text, explanations and answer JSON are excluded. Hub lists paginate 12 courses, 12 materials and six private notes. Seven-day progress uses SQL aggregates and an index on user/completion time. Course topics for quiz generation are distinct and capped at 30.
- Source text is untrusted AI input. Prompt instructions require source-based answers and abstention where unsupported. Returned source quotations are retained only if present in the supplied text. AI answer accuracy still requires evaluation with real course material.

## Deployment

Run from the application directory with the server’s supported PHP binary:

```sh
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
npm ci
npm run build
php artisan optimize
php artisan queue:restart
```

If assets are built in CI, deploy the resulting `public/build` directory instead of building on the live host. Restart long-running workers through the existing process supervisor when applicable. Keep the existing queue/scheduler and notification consent configuration. No provider credentials or prices are changed by this feature.

Local verification used PHP 8.5.2, isolated SQLite feature/browser databases and the compiled frontend bundle. Live MySQL execution and real provider answer quality require checks in the deployment environment. MySQL identifier preflight checks all migration identifier lengths.

## Verification and measurements

Backend coverage is in `tests/Feature/ConnectedStudentStudyTest.php` and `tests/Feature/StudyTeachingMonetizationTest.php`, alongside the existing student-tool and core performance tests. It covers grounding, answer-key visibility, feedback, scheduling, retry deduplication, private imports, rejected uploads, revoked/hidden access, disabled AI, failed AI preservation, bounded history queries, free-until-priced access, wallet purchase, role restrictions and migration restart/rollback.

The isolated browser fixture `tests/Browser/prepare-student-study.php` refuses any non-testing database outside `/private/tmp/acadsyntra-studio-study-*`. The browser scenario `tests/Browser/student-study.test.mjs` exercises real source selection, saved work, form grading, dashboard progress and private note persistence at 390px and 1440px, including repeated navigation with loaded styles. AI generation is mocked in backend tests; the browser uses seeded saved explanations/quizzes to avoid live charges.

Performance samples and their limitations are recorded in [the study performance report](performance/student-study-2026-10-23.json). These are local application measurements, not production capacity or student-retention results. The feature’s effect on return visits still needs observation with real students.
