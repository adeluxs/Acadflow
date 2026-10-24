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

## Verification

`tests/Feature/LecturerTeachingStudioTest.php` covers direct-URL separation between student and lecturer tools, tenant/course scoping, real action signals, dashboard entry points, pagination and bounded query counts. The relevant student study and core dashboard tests are run alongside it.

