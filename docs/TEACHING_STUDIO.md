# Teaching Studio

Teaching Studio is the lecturer counterpart to the student-only study workspace. It focuses on the work that brings lecturers back because it changes what they do next: student submissions waiting for feedback, open course questions, course preparation gaps and drafts awaiting a decision.

## Lecturer workflow

1. Open **Teaching Studio** from the lecturer sidebar or dashboard.
2. Follow the next teaching action. The studio prioritizes the oldest reviewable submission, then an open student question, then course preparation.
3. Use **Course pulse** to see enrolled students, visible materials, published assignments and pending reviews for each assigned course.
4. Open the existing material editor to prepare content, or use Lecturer AI Studio to produce a lesson plan, outline, quiz, rubric, summary or rewrite. AI output remains a draft until the lecturer reviews and applies it.

The page also links directly to submission review, discussions, material creation, assignment creation and the existing AI draft library. It remains useful when AI is unavailable; manual course preparation and all operational signals continue to work.

## Product rationale

The strongest repeat value for lecturers is a short, trustworthy feedback loop:

- **Respond:** clear work waiting for review while feedback is still useful.
- **Listen:** see unresolved student questions before confusion spreads.
- **Prepare:** add the next material or assignment where a course has a gap.
- **Finish:** review or discard generated drafts instead of accumulating unfinished AI output.

These signals describe work state. They do not rank lecturers, claim to measure teaching quality or expose individual student study notes, explanations, quizzes or revision history. Future improvements should favour evidence lecturers can act on, such as recurring question themes and assignment misconception summaries, with minimum-group privacy thresholds before any cohort insight is shown.

## Access and performance

All `/lecturer/teaching` routes require the `lecturer` role. Courses are selected through current lecturer assignments, and course policies remain authoritative for every destination action. Another lecturer’s courses, submissions, questions and AI drafts are excluded.

Student study routes, including direct URLs for learning preferences, notes, quizzes, flashcards, plans, mastery, deadline coaching, self-assessment, annotations and reflections, require the `student` role. The general AI Assistant renders study shortcuts only for students and lecturer shortcuts only for lecturers.

The studio uses 12-item simple pagination and count subqueries for course cards. It does not hydrate material bodies, submission files, student study sessions or quiz answers. Open questions are limited to four cards. The dashboard adds only aggregate teaching signals and keeps its existing six-course preview. The automated performance regression verifies that adding 25 assigned courses does not cause per-course queries.

AI generation still uses the existing central provider routing, feature switches, allowance and billing controls. Teaching Studio does not send new email or push reminders.

## Monetization

**Lecturer Teaching Studio** is presented to lecturers as the **Teaching Pro** package. It remains free until a super administrator publishes an active price under **Admin → Monetization → Feature Pricing**. The administrator can offer monthly and semester access, included Lecturer Assistant requests, an optional one-time trial and read-only grace days. Lecturers activate it from **Plans & Usage**, or receive access through a verified institution or department commercial account that sponsors `lecturer_teaching_tools`.

The package covers the Teaching Studio signals and lecturer content-draft workflow. Core lecturer course management, material publishing, assignment management and submission review remain available outside the package. Daily free requests and included package requests are consumed before Lecturer Assistant overage pricing. Lecturers can cap monthly AI spending, and a failed generation restores the reserved package request. Grace access keeps saved studio pages readable while blocking new drafts and other writes until renewal.

Lecturers may buy **Academic AI Plus** alongside Teaching Pro for a separate shared allowance across writing, citation, research, validation, assignment, submission, academic-integrity, SIWES, project, discussion and Knowledge Hub assistants. Package labels on **Plans & Usage** show which allowance applies. AI Plus never bypasses course, project, submission or content authorization.

## Verification

`tests/Feature/LecturerTeachingStudioTest.php` covers direct-URL separation between student and lecturer tools, tenant/course scoping, real action signals, dashboard entry points, pagination and bounded query counts. `tests/Feature/StudyTeachingMonetizationTest.php` covers free-until-priced behavior, paid route protection, role-specific catalogues and wallet activation. The relevant student study and core dashboard tests are run alongside them.
