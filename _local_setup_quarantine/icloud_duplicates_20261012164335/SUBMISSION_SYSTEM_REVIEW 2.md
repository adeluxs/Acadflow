# Submission System - Comprehensive Review & Recommendations

## Executive Summary

The current submission system provides foundational functionality for students to submit work and lecturers to grade it, but **lacks the critical assignment/task definition layer that would allow lecturers to configure submission rules, deadlines, and requirements upfront**. This results in a reactive system where assignments are created ad-hoc rather than properly managed.

---

## 1. Current Implementation Analysis

### ✓ What's Working

1. **Core Workflow States** - Submissions flow through proper states: draft → submitted → under_review → graded
2. **Version Control** - File version history with timestamps and max 20 version limit
3. **File Upload** - Basic file upload with size limits (50MB) and format restrictions
4. **Grading** - Manual scoring and rubric-based grading support
5. **Comments System** - Inline comments with position data for PDF annotations
6. **Role-Based Access** - Basic permission checks for students/lecturers
7. **Group Support** - Groups exist and can make group submissions
8. **Database Schema** - Reasonable table structure for core features

### ✗ What's Missing or Broken

#### 1. **No Assignment/Task Definition Layer** (CRITICAL)
- **Problem**: Lecturers cannot define assignments before students submit
- **Current**: Students create submissions directly, lecturers react to them
- **Missing**: Submission template/task that lecturers create in advance
- **Impact**: No way to set requirements, deadlines, or rules before submission period
- **Required**: `SubmissionTask` model with lecturer-defined configuration

#### 2. **Weak Deadline Enforcement** (HIGH PRIORITY)
- **Current**: `due_date` field exists but isn't validated
- **Missing**: 
  - Automatic late submission flagging
  - Deadline extension logic
  - Overdue status updates
  - Deadline notifications
- **Code Gap**: No validation in `SubmissionController::submit()` against `due_date`

#### 3. **No Submission Settings/Rules Configuration** (HIGH PRIORITY)
- **Missing in Database**:
  - Allowed file formats per task
  - Max file count
  - Max file size
  - Allowed submission types (individual vs group)
  - Resubmission limits
  - Late submission policy (allow/disallow)
  - Grace period configuration
- **Current**: Hardcoded: "PDF, DOCX, images" only, max 50MB, max 10 files per submission
- **Problem**: No way to customize rules per assignment

#### 4. **No Visibility/Availability Schedule** (MEDIUM PRIORITY)
- **Missing**: `open_at` and `close_at` on submissions
- **Impact**: Students can't see which assignments are available
- **Current**: Submissions appear anytime
- **Required**: Time-based access control to assignment availability

#### 5. **No Payment Access Control** (HIGH PRIORITY - Per PRD)
- **PRD Requirement**: "Unpaid students cannot submit academic work" (R-080)
- **Current**: No check in `SubmissionController::submit()` or `::upload()`
- **Impact**: Violates stated business rule
- **Fix**: Add payment verification in submission authorization

#### 6. **No Resubmission Rules** (MEDIUM PRIORITY)
- **Missing**: Configuration for max resubmissions
- **Current**: Unlimited resubmissions possible
- **Problem**: No control over how many times students can resubmit

#### 7. **No Batch Assignment Creation** (MEDIUM PRIORITY)
- **Current**: Each submission must be created individually by student
- **Missing**: Lecturers can't batch-create assignments for a course
- **Required**: Bulk task creation or templates

#### 8. **Poor Submission Discoverability** (MEDIUM PRIORITY)
- **Problem**: Students don't see "available assignments" until they manually create submissions
- **Missing**: Assignment discovery interface for students
- **Current**: No central place to see all open assignments for a course
- **Required**: "View Available Assignments" page per course

#### 9. **No Automatic Status Updates** (MEDIUM PRIORITY)
- **Current**: Overdue submissions stay in "submitted" state indefinitely
- **Missing**: Background job to update status based on deadline
- **Problem**: No clear indication which submissions are overdue

#### 10. **Incomplete Access Control** (HIGH PRIORITY)
- **Missing Checks**:
  - Is student enrolled in course? ✓ (implicit in create)
  - Is student paid? ✗ (missing)
  - Has payment grace period expired? ✗ (missing)
  - Is assignment open for submission? ✗ (missing - no open_at/close_at)
  - Is resubmission allowed? ✗ (missing)
- **Current**: Basic check in SubmissionPolicy but incomplete

#### 11. **Minimal Notification System** (LOW PRIORITY)
- **Missing**: Deadline reminders, status change notifications
- **Current**: No notification triggers for submission events
- **Required**: Event-based notifications

#### 12. **Weak Validation** (MEDIUM PRIORITY)
- **Current**: File validation in controller is basic
- **Missing**: 
  - Validate against task-specific requirements
  - Check file format against allowed list
  - Validate file count against max
  - Validate total size against limit

---

## 2. Database Schema Gaps

### submissions table - Missing Columns
```sql
-- Configuration Reference
assignment_id        BIGINT              -- FK to submission_tasks
submission_task_id   BIGINT              -- Alternative name for clarity

-- Metadata
open_at              TIMESTAMP NULL      -- When students can start submitting
close_at             TIMESTAMP NULL      -- When submissions close
is_late              BOOLEAN DEFAULT 0   -- Denormalized for performance
extension_until      TIMESTAMP NULL      -- For individual deadline extensions

-- Submission Rules (Denormalized for Performance)
allow_late           BOOLEAN DEFAULT 1   -- Can submit after close_at
allow_resubmission   BOOLEAN DEFAULT 1   -- Can resubmit after grading
max_resubmissions    INT DEFAULT NULL    -- Null = unlimited

-- Requirements (Denormalized)
instructions         TEXT NULL           -- Task-specific instructions
requirements         JSON NULL           -- File type/count/size rules
rubric_id            BIGINT NULL         -- Default rubric for grading
allow_group          BOOLEAN DEFAULT 0   -- Is this a group task
```

### New Tables Needed
```sql
submission_tasks (
  id, uuid, course_id, semester_id,
  title, description, instructions, requirements (JSON),
  type (assignment/project/siwes/seminar),
  is_individual_or_group,
  open_at, close_at, due_date, late_deadline,
  allow_late_submission, max_resubmissions,
  max_file_size, max_file_count, allowed_formats (JSON),
  rubric_id, is_published, status,
  created_by, created_at, updated_at, deleted_at
)

submission_requirements (
  id, task_id,
  allowed_file_types (array/json),
  max_file_size_mb,
  max_file_count,
  min_word_count (optional),
  require_cover_page (boolean),
  created_at
)
```

---

## 3. Access Control Issues

### Missing Checks
1. **Payment Verification** - No check if student is paid for semester
2. **Enrollment Verification** - Implicit but not explicit
3. **Assignment Availability** - No open_at/close_at validation
4. **Deadline Verification** - Deadline exists but not enforced
5. **Resubmission Limits** - No checking max_resubmissions
6. **Grace Period** - No grace period logic for payment

### Recommendation
Create a unified submission validator service that checks all these rules before allowing submission:
```php
class SubmissionValidator {
    public function validateSubmissionAllowed(Submission $submission): array
    public function validateCanResubmit(Submission $submission): array
    public function validatePaymentStatus(User $user, Semester $semester): bool
    public function validateDeadline(Submission $submission): bool
    public function validateAssignmentOpen(SubmissionTask $task): bool
}
```

---

## 4. Workflow vs Implementation Gaps

### Workflow says:
```
Step 1: Draft Creation → ✓ Works
Step 2: File Upload → ✓ Works (basic)
Step 3: Submission → ✓ Works (missing validation)
Step 4: Lecturer Review → ✓ Works
Step 5: Feedback → ✓ Works
Step 6: Resubmission → ✓ Works (missing rules)
Step 7: Grading → ✓ Works
```

**But workflow says nothing about:**
- Lecturer creating/configuring the assignment
- Setting deadlines and requirements
- Controlling who can submit
- Defining file format rules
- Setting up late submission policies

---

## 5. Missing High-Priority Features for Production

### For Students
1. ✗ View available assignments for a course
2. ✗ See assignment requirements/instructions before submitting
3. ✗ Clear deadline display with warning indicators
4. ✗ Understanding why submission failed (validation errors)
5. ✗ Seeing late submission penalties/warnings
6. ✗ Automatic notification of deadlines

### For Lecturers
1. ✗ Create assignments/tasks (CRITICAL)
2. ✗ Configure submission rules and requirements
3. ✗ Set deadlines and extension windows
4. ✗ Batch create assignments for multiple students
5. ✗ Define late submission policy
6. ✗ Set resubmission limits
7. ✗ View analytics (submissions per task, completion rates)
8. ✗ Bulk upload assignments from templates
9. ✗ Copy assignments between semesters
10. ✗ Create assignment rubrics integrated with tasks

---

## 6. Implementation Recommendations

### Priority 1 (MUST HAVE - v1.0)
1. Create `SubmissionTask` model and table
2. Create `SubmissionRequirement` model and table
3. Build lecturer task management interface
4. Add payment validation to submission flow
5. Add deadline enforcement and late submission flagging
6. Create student assignment discovery interface
7. Extend submission validation based on task rules

### Priority 2 (SHOULD HAVE - v1.1)
1. Deadline reminders and notifications
2. Automatic late submission flagging on schedule
3. Resubmission limits and configuration
4. Assignment templates and bulk creation
5. Grace period configuration for payments
6. Extension granting UI for lecturers

### Priority 3 (NICE TO HAVE - v1.2)
1. Analytics dashboard for submissions
2. Plagiarism detection integration
3. Advanced rubric features
4. Assignment versioning/archiving
5. Automated status updates via background jobs

---

## 7. Specific Code Issues

### SubmissionController::store()
```php
// MISSING: Check if assignment/task exists
// MISSING: Validate task is open for submission
// MISSING: Validate task allows this submission type
// MISSING: Check student is enrolled
// MISSING: Check student has paid

// Current just creates submission with student input
$submission = Submission::create([
    'user_id' => Auth::id(),
    ...
]);
```

### SubmissionController::submit()
```php
// MISSING: Validate due_date if it exists
// MISSING: Flag if submission is late
// MISSING: Check resubmission limits
// MISSING: Verify payment status
// MISSING: Check task is still open

$submission->update(['status' => 'submitted']);
```

### SubmissionController::upload()
```php
// MISSING: Validate against task-specific file rules
// MISSING: Check total file count against task limit
// MISSING: Check against allowed formats from task
// MISSING: Block if resubmission not allowed

// Current validation is generic
$files = $request->file('files');
```

### SubmissionPolicy::view()
```php
// MISSING: Check if assignment is visible to student
// MISSING: Check payment status
// MISSING: Check deadline hasn't passed (if that matters for viewing)
```

---

## 8. Proposed Solution Architecture

### New Models
```
SubmissionTask
  - Lecturer-defined assignment configuration
  - Belongs to Course & Semester
  - HasMany SubmissionRequirements
  - HasMany Submissions
  - Can be template for next semester

SubmissionRequirement
  - File/submission rules for a task
  - Belongs to SubmissionTask
```

### Extended Models
```
Submission
  - Add: assignment_id (FK to SubmissionTask)
  - Add: open_at, close_at, is_late, extension_until
  - Add: allow_late, max_resubmissions
  - Belongs to SubmissionTask (new)

Course
  - HasMany SubmissionTasks (new)
```

### New Services/Validators
```
SubmissionValidator
  - validateSubmissionAllowed()
  - validatePaymentStatus()
  - validateDeadlineCompliance()
  - validateFileRequirements()
  - validateResubmissionAllowed()

SubmissionTaskManager
  - createTaskForCourse()
  - publishTask()
  - closeTaskForNewSubmissions()
  - grantExtension()
  - bulkCreateTasks()

NotificationService (extend)
  - notifyDeadlineApproaching()
  - notifySubmissionReceived()
  - notifyDeadlinePassed()
```

---

## 9. Conclusion

The current submission system has a **solid foundation but is incomplete**. The critical missing piece is the **lecturer task/assignment definition layer**. Without this:

- Lecturers cannot properly configure assignments
- Students cannot discover what assignments are available
- Deadlines cannot be reliably enforced
- File requirements cannot be customized per assignment
- The system doesn't match the PRD requirements

The implementation should prioritize:
1. **SubmissionTask model** - The core missing feature
2. **Lecturer interface** - To create/manage tasks
3. **Validation layer** - To enforce all rules
4. **Student discovery** - To see available assignments
5. **Access control** - To verify payment/enrollment

This will transform it from a reactive grading system into a complete assignment management platform.

---

## Implementation Timeline

- **Phase 1 (Database)**: 2-3 hours
- **Phase 2 (Models & Policies)**: 2-3 hours
- **Phase 3 (Controllers & Logic)**: 4-5 hours
- **Phase 4 (Validation & Business Rules)**: 2-3 hours
- **Total Estimated**: 12-16 hours for production-ready implementation

---

*Last Updated: 2026-04-20*
*Status: Ready for Implementation*
