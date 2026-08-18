# AcadFlow Roles and Permission Model

**Current source snapshot:** 2026-08-15  
**User instructions:** `USER_GUIDE.md`

The canonical role enum is `App\Enums\UserRole`.

| Role key | Display label | Scope |
|---|---|---|
| `super_admin` | Super Admin | Platform-wide |
| `university_admin` | University Admin | One university (plus inherited admin hierarchy behavior) |
| `department_admin` | Department Admin | One department |
| `lecturer` | Lecturer | Assigned courses/research responsibilities |
| `student` | Student | Enrolled courses / own work |
| `member` | Platform Member | Personal knowledge/research/community ecosystem access |

`Group Leader` and `Supervisor` are contextual responsibilities, not additional `UserRole` values.

## Permission architecture

`App\Enums\Permission` defines coarse permissions. `User::hasPermission()` maps these by role. Model policies and tenant/course access checks provide resource-level enforcement.

### Super Admin

Has the broadest permission set including users, institutions, courses, billing, analytics, system settings, AI settings and platform administration. Super Admin is still expected to use administrative paths rather than impersonating academic role workflows.

### University Admin

Can manage university-scoped users, faculties, courses, billing/reports and university settings. AI administration is permitted by the role permission map, while platform-only controls remain protected by route/controller rules.

### Department Admin

Can manage department courses, lecturer assignments, department users/reporting/billing and assignment administration. Some user creation/editing behavior is policy-conditional.

### Lecturer

Can manage assigned/authorized courses, assignments, materials, attendance, course submissions/grading/corrections, reports and academic collaboration according to policies.

### Student

Can enrol in eligible courses, view own courses/materials/assignments, create/manage own submissions, check in attendance, manage groups, view own invoices/documents/analytics and use eligible academic tools.

### Platform Member

Has personal profile, group/community/research/knowledge ecosystem permissions but does not automatically gain institutional course/submission/attendance access.

## Authorization hierarchy

Role permission does not automatically grant access to every record. Resource access also checks university/department/course membership and policies. For example, a lecturer normally accesses a course through `LecturerCourseAssignment`; a student through active `Enrollment`.

## Admin hierarchy middleware

`RoleMiddleware` allows Super Admin to enter admin route groups and University Admin to inherit Department Admin route groups where intended. This hierarchy does not convert an admin into a Student or Lecturer.

## Feature/subscription overlays

A user can have role permission but still be blocked because:

- the Feature Management module is disabled/maintenance;
- a parent feature dependency is unavailable;
- the user's subscription does not allow the feature;
- the specific record policy denies access;
- the AI feature itself is disabled.
