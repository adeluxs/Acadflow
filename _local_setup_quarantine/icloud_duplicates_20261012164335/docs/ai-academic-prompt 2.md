You are a senior AI architect, education technology expert, Laravel engineer, machine learning engineer, academic workflow consultant, and software architect.

Your task is to design and implement a comprehensive **AI Academic Assistant** for AcadFlow.

IMPORTANT:
- Before writing any code, thoroughly inspect the existing AcadFlow codebase.
- Determine what AI-related features already exist.
- Reuse existing services, models, controllers, jobs, events, notification systems, storage, queues, and APIs wherever possible.
- Do NOT duplicate functionality.
- Do NOT create duplicate controllers, routes, services, models, migrations, settings, or APIs.
- Extend the existing architecture instead.
- Ensure the AI Assistant integrates seamlessly with the entire AcadFlow ecosystem.

The AI Academic Assistant should not be a single feature. It should be a centralized AI layer that powers multiple intelligent academic workflows across the platform for students, lecturers, supervisors, departments, and administrators.

====================================================
OVERALL GOAL
====================================================

Build a modular AI Academic Assistant capable of helping users before, during, and after every academic activity.

The AI should function as:

- Academic reviewer
- Writing assistant
- Submission validator
- Plagiarism assistant
- Citation checker
- Research assistant
- Study assistant
- Lecturer assistant
- Project assistant
- SIWES report assistant
- Academic analytics engine

The AI should provide guidance, validation, suggestions, and insights—not automatically replace lecturer decisions.

====================================================
PHASE 1 — CODEBASE AUDIT
====================================================

Before implementation:

1. Inspect the entire codebase.

Determine whether the following already exist:

- AI services
- Prompt services
- OpenAI integration
- Anthropic integration
- Gemini integration
- AI jobs
- AI queues
- AI controllers
- AI settings
- AI configuration
- Notification integration
- File parsers
- PDF parsers
- DOCX parsers
- Existing plagiarism logic
- Existing validation logic
- Existing grading system
- Existing submission system
- Existing discussion system
- Existing project workflow
- Existing SIWES workflow

Reuse them whenever possible.

====================================================
PHASE 2 — AI ARCHITECTURE FOUNDATION
====================================================

Before implementing any AI-powered feature, design and implement a centralized AI architecture that will serve as the intelligence layer across the entire AcadFlow ecosystem.

The architecture must be modular, provider-independent, scalable, and extensible.

Do not allow any controller, model, service, or feature to communicate directly with an AI provider.

Every AI request must pass through a centralized AI Manager.

The architecture should support:

Rule-Based AI
External AI Providers
Self-hosted AI Models
Future AI Providers

without changing feature implementations.

AI Architecture

Implement the following architecture:

Application

↓

AI Manager

↓

AI Router

↓

Rule Engine

↓

OR

↓

AI Provider

↓

Response Formatter

↓

Feature Module

Every AI-powered feature in AcadFlow must use this architecture.

AI Manager

Implement a centralized AI Manager responsible for:

Receiving AI requests
Determining the requested feature
Forwarding requests to the AI Router
Logging AI requests
Tracking usage
Returning standardized responses

The AI Manager should become the only gateway for AI operations.

AI Router

The AI Router determines which engine should process a request.

Supported modes:

Rule-Based Only

AI Provider Only

Hybrid Mode

Disabled

The router should be configurable through system settings.


====================================================
PHASE 3 — RULE-BASED AI ENGINE
====================================================

Implement a complete Rule-Based AI engine.

This engine should become the default AI implementation during development, testing, demonstrations, and initial production deployment.

The Rule Engine must operate without requiring any external AI provider.

The Rule Engine should support:

Assignment validation

SIWES validation

Project validation

Submission validation

Citation validation

Formatting validation

Grammar checks (using local libraries where appropriate)

Required section detection

Word count validation

File structure validation

Required attachment validation

Deadline validation

Academic policy validation

Institution-specific rules

Department-specific rules

Knowledge Hub moderation rules

Discussion moderation

Automatic recommendation templates

Automated lecturer feedback templates

Submission readiness scoring

The Rule Engine should produce structured responses identical to those returned by external AI providers.

Rule Packs

Rules should be organized into independent rule packs.

Examples:

Academic Rule Pack

Assignment Rule Pack

SIWES Rule Pack

Project Rule Pack

Knowledge Hub Rule Pack

Discussion Rule Pack

Research Rule Pack

Institution Rule Pack

Department Rule Pack

Administrators should be able to enable or disable rule packs independently.

====================================================
PHASE 4 — AI PROVIDER ABSTRACTION
====================================================

Implement a provider-independent architecture.

Create an AI Provider Interface.

Supported providers should include:

RuleBasedProvider

OpenAIProvider

ClaudeProvider

GeminiProvider

DeepSeekProvider

AzureOpenAIProvider

OllamaProvider

Future providers

Every provider must implement the same interface.

Switching providers should require only changing configuration settings.

Feature code must never depend on a specific provider.

====================================================
PHASE 5 — HYBRID AI MODE
====================================================

Implement Hybrid AI mode.

Hybrid mode should always attempt the cheapest solution first.

Workflow:

Check cache

↓

Run Rule Engine

↓

If Rule Engine successfully answers

↓

Return response

↓

Else

↓

Call configured AI Provider

↓

Return response

↓

If provider fails

↓

Fallback to Rule Engine

The user should never experience application errors due to AI provider failures.

====================================================
PHASE 6 — AI SETTINGS
====================================================

Extend the existing system settings.

Do not duplicate settings.

Add support for:

AI Mode

Default Provider

Fallback Provider

Provider Priority

API Keys

Request Timeout

Maximum Tokens

Daily Request Limit

Monthly Request Limit

Maximum Cost

Enable Rule Engine

Enable External AI

Enable Hybrid Mode

Enable Cache

Enable Logging

Feature-level AI Permissions

Rule Pack Management

Provider Priority Order

Knowledge Hub AI Settings

Workflow AI Settings

All settings must be centralized.


====================================================
PHASE 7 — AI CACHE
====================================================

Implement intelligent AI caching.

Cache should prevent unnecessary processing.

If the same document is analyzed multiple times without changes, return the cached analysis.

Support cache invalidation whenever:

Document changes

Submission changes

Report changes

Settings change

Rules change


====================================================
PHASE 8 — AI BACKGROUND PROCESSING
====================================================

All AI processing must execute asynchronously.

Use:

Laravel Queues

Jobs

Events

Listeners

Notifications

Long-running AI tasks must never block the request lifecycle.

The user interface should display:

Queued

Processing

Completed

Failed

Retrying


====================================================
PHASE 9 — AI ANALYTICS
====================================================

Track every AI request.

Metrics should include:

Rule Engine Requests

Provider Requests

Hybrid Requests

Cache Hits

Cache Misses

Provider Response Time

Rule Engine Response Time

Provider Costs

Estimated Savings

Daily Usage

Monthly Usage

Per-user Usage

Per-department Usage

Per-university Usage

Most Used Features

Average Processing Time

Failure Rate


====================================================
PHASE 10 — COST OPTIMIZATION
====================================================

The AI system should prioritize minimizing external API costs.

Processing order:

Cache
Rule Engine
External AI
Fallback Rule Engine

External AI should only be used when:

The Rule Engine cannot produce an acceptable result.



====================================================
PHASE 11 — AI SUBMISSION VALIDATOR
====================================================

Before any lecturer reviews a submission, the AI should automatically inspect it.

Supported submissions:

- Assignments
- Projects
- SIWES Reports
- Seminar Papers
- Research Papers
- Lab Reports
- Any future academic submission

The validator should detect:

Missing chapters

Missing abstract

Missing references

Missing acknowledgements

Missing appendices

Missing table of contents

Missing figures

Missing tables

Empty sections

Incorrect headings

Incorrect chapter order

Formatting issues

Wrong margin

Wrong font size

Wrong spacing

Wrong page numbering

Wrong template

University formatting violations

Citation inconsistencies

Broken references

Reference duplication

Word count issues

Required section omissions

Document completeness

Submission readiness

The AI should produce:

Validation report

Readiness score

List of corrections

Priority level for each issue

Suggestions for fixing issues

Students should see this report immediately.

Lecturers should also see the validation summary before opening the submission.

====================================================
PHASE 12 — PLAGIARISM DETECTION
====================================================

Implement intelligent plagiarism analysis.

The system should check against:

Internet sources

Internal university repository

Previous submissions

Research papers

Academic journals

Books

Previously submitted SIWES reports

Previously submitted projects

Previously submitted assignments

Output should include:

Overall similarity percentage

Source list

Highlighted copied text

Matched paragraphs

Citation issues

Duplicate references

Possible AI-generated content indicators (if supported)

Risk score

Lecturer summary

Student summary

Allow institutions to configure similarity thresholds.

====================================================
PHASE 13 — ACADEMIC WRITING ASSISTANT
====================================================

Provide writing assistance while students are preparing reports.

Capabilities:

Grammar suggestions

Academic tone improvement

Sentence clarity

Paragraph restructuring

Writing consistency

Academic vocabulary suggestions

Professional writing improvements

Readability improvements

Remove repetition

Detect vague writing

Suggest stronger explanations

Improve introductions

Improve conclusions

Improve transitions

Improve discussion sections

Improve methodology descriptions

The assistant should never silently rewrite work.

Suggestions should always be reviewable by the student.

====================================================
PHASE 14 — CITATION & REFERENCE ASSISTANT
====================================================

Support multiple citation styles:

APA

MLA

Chicago

Harvard

IEEE

The assistant should:

Validate citations

Validate bibliography

Detect missing references

Detect uncited references

Detect inconsistent formatting

Suggest corrections

Check reference ordering

Check DOI formatting

Check URLs

Warn about incomplete references

====================================================
PHASE 15 — FINAL YEAR PROJECT ASSISTANT
====================================================

Assist students throughout the project lifecycle.

Capabilities:

Proposal review

Title review

Research objective validation

Research question validation

Chapter validation

Methodology suggestions

Literature review guidance

Reference validation

Formatting validation

Submission readiness

Supervisor comment summarization

Version comparison

Correction tracking

Project progress analysis

====================================================
PHASE 16 — SIWES REPORT ASSISTANT
====================================================

Focus only on SIWES reports.

Capabilities:

Validate required sections

Check formatting

Check logbook consistency

Check organization details

Check work descriptions

Check supervisor information

Check conclusion

Check recommendations

Generate readiness report

Provide correction suggestions

====================================================
PHASE 17 — STUDY ASSISTANT
====================================================

Allow students to ask questions from:

Course materials

Assignments

Slides

Lecture notes

PDFs

Word documents

Discussions

The assistant should:

Explain difficult concepts

Generate summaries

Answer questions

Suggest further reading

Recommend related materials

Explain lecturer feedback

Provide revision tips

====================================================
PHASE 18 — AI MATERIAL ASSISTANT
====================================================

For every uploaded material:

Generate automatic summary

Generate keywords

Extract topics

Generate learning objectives

Suggest prerequisite materials

Suggest related discussions

Enable semantic search

====================================================
PHASE 19 — LECTURER AI ASSISTANT
====================================================

Assist lecturers with:

Submission summaries

Common mistakes across submissions

Suggested feedback

Rubric assistance

Performance analytics

Class weaknesses

Frequently misunderstood topics

Student progress insights

AI should never automatically grade without lecturer approval.

====================================================
PHASE 20 — DISCUSSION AI
====================================================

Enhance discussions by allowing AI to:

Answer common questions

Recommend similar discussions

Recommend relevant materials

Summarize long discussion threads

Detect duplicate questions

Suggest lecturer responses

====================================================
PHASE 21 — AI SEARCH
====================================================

Implement semantic search across:

Materials

Assignments

Projects

SIWES reports

Discussions

Announcements

Lecture notes

Students should be able to search by meaning rather than exact keywords.

====================================================
PHASE 22 — AI ANALYTICS
====================================================

Generate insights such as:

Most common writing mistakes

Most common plagiarism sources

Average submission readiness

Submission quality trends

Course performance trends

Department insights

Project completion trends

====================================================
PHASE 23 — BACKGROUND PROCESSING
====================================================

All heavy AI operations must run asynchronously.

Use:

Laravel Queues

Jobs

Events

Listeners

Notifications

No AI request should block the user interface.

Users should see:

Processing...

Analysis Complete

Validation Ready

====================================================
PHASE 24 — SETTINGS
====================================================

Integrate with system settings.

Allow administrators to configure:

AI provider

Model

Temperature

Maximum tokens

Similarity threshold

Supported citation styles

Supported document formats

Maximum document size

Enable/disable plagiarism

Enable/disable writing assistant

Enable/disable semantic search

Enable/disable AI summaries

Enable/disable study assistant

Enable/disable lecturer assistant

Ensure all settings are centralized, respected throughout the application, and not duplicated.

====================================================
PHASE 25 — SECURITY
====================================================

Respect permissions.

Students can only access their own analyses.

Lecturers can only access analyses for their assigned courses.

Department admins only within their department.

University admins only within their institution.

Super admins have full access.

====================================================
PHASE 26 — IMPLEMENTATION RULES
====================================================

- Inspect the existing system before coding.
- Extend existing functionality instead of recreating it.
- Do not duplicate models, services, migrations, settings, jobs, routes, or APIs.
- Keep the AI Assistant modular and extensible.
- Follow Laravel best practices.
- Make the architecture scalable for thousands of concurrent users.
- Ensure every AI feature integrates cleanly with assignments, projects, SIWES, discussions, materials, and future academic modules.

====================================================
FINAL DELIVERABLE
====================================================

At completion, provide:

1. Existing AI components found.
2. Components reused.
3. New components created.
4. Any duplicate logic removed or merged.
5. How each AI module integrates with the existing AcadFlow system.
6. Any recommended future enhancements.

The final result should be a unified, production-ready AI Academic Assistant that serves as the intelligence layer across the entire AcadFlow platform, improving academic quality, reducing lecturer workload, and enhancing the learning experience for students.