# User Roles and Permissions Matrix

---

## 1. Role Hierarchy

```
┌────────────────────────────────────────────────────────────────────┐
│                         ROLE HIERARCHY                            │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│   Super Admin                                                      │
│        │                                                           │
│        ▼                                                           │
│   University Admin                                                 │
│        │                                                           │
│        ▼                                                           │
│   Department Admin                                                │
│        │                                                           │
│        ▼                                                           │
│   Lecturer                                                         │
│        │                                                           │
│        ▼                                                           │
│   Student                                                          │
│        │                                                           │
│        ▼                                                           │
│   (Group Leader, Supervisor - subtypes of Student/Lecturer)      │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

---

## 2. Role Definitions

### 2.1 Super Admin

**Purpose**: System-wide administrator with full access to all features

**Creation**: First user during system setup (seeded)

**Responsibilities**:
- Create and manage university accounts
- Configure system-wide settings
- View all data across all universities
- Manage billing for all institutions
- System monitoring and maintenance

**Limitations**: Cannot enroll as a student or submit work

---

### 2.2 University Admin

**Purpose**: Administrative head for an entire university

**Creation**: Created by Super Admin

**Responsibilities**:
- Manage faculties within the university
- Create department admins
- View all department data
- Manage university-wide billing
- Generate university reports
- Approve course creation requests

**Scope**: Single university, all faculties and departments

---

### 2.3 Department Admin

**Purpose**: Administrative head for a specific department

**Creation**: Created by University Admin

**Responsibilities**:
- Manage courses within the department
- Assign lecturers to courses
- Manage students in the department
- View department analytics
- Approve student enrollment
- Manage semester billing for department

**Scope**: Single department

---

### 2.4 Lecturer

**Purpose**: Academic staff who teach courses and review submissions

**Creation**: Invited by Department Admin

**Responsibilities**:
- Create and manage courses
- Review and grade submissions
- Request corrections from students
- Start and manage attendance sessions
- View student performance
- Generate course reports

**Scope**: Assigned courses

---

### 2.5 Student

**Purpose**: University students who submit academic work

**Creation**: Self-registration or imported via CSV

**Responsibilities**:
- Enroll in courses
- Submit assignments, projects, reports
- View submission status
- View grades and feedback
- Mark attendance in class
- Make payments
- Generate final documents

**Scope**: Enrolled courses only

---

### 2.6 Group Leader (Student Subtype)

**Purpose**: Student who leads a group project

**Creation**: Selected by group members

**Responsibilities**:
- Create groups
- Invite members to join
- Manage group submission
- Lock submissions from other members
- View group contributions

**Inherits**: All student permissions

---

### 2.7 Supervisor (Lecturer Subtype)

**Purpose**: Lecturer assigned to supervise final-year projects

**Creation**: Assigned by Department Admin

**Responsibilities**:
- All lecturer responsibilities
- Additional: Monitor project progress
- Additional: Schedule project defense
- Additional: Final project approval

**Inherits**: All lecturer permissions

---

## 3. Permission Matrix

### 3.1 Legend

| Symbol | Meaning |
|--------|---------|
| ✓ | Allowed |
| ✗ | Not Allowed |
| C | Conditional (see notes) |

---

### 3.2 User Management Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| View all users | ✓ | ✓ | ✓ | ✗ | ✗ |
| Create users | ✓ | ✓ | C | ✗ | ✗ |
| Edit users | ✓ | ✓ | C | ✗ | ✗ |
| Delete users | ✓ | ✓ | ✗ | ✗ | ✗ |
| Manage roles | ✓ | ✗ | ✗ | ✗ | ✗ |
| View own profile | ✓ | ✓ | ✓ | ✓ | ✓ |
| Edit own profile | ✓ | ✓ | ✓ | ✓ | ✓ |
| Change password | ✓ | ✓ | ✓ | ✓ | ✓ |

---

### 3.3 Department & Faculty Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Create faculty | ✓ | ✓ | ✗ | ✗ | ✗ |
| Edit faculty | ✓ | ✓ | ✗ | ✗ | ✗ |
| Delete faculty | ✓ | ✗ | ✗ | ✗ | ✗ |
| Create department | ✓ | ✓ | ✗ | ✗ | ✗ |
| Edit department | ✓ | ✓ | ✓ | ✗ | ✗ |
| Delete department | ✓ | ✗ | ✗ | ✗ | ✗ |
| View all departments | ✓ | ✓ | ✓ | C | ✗ |
| View own department | ✓ | ✓ | ✓ | ✓ | ✓ |

---

### 3.4 Course Management Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Create course | ✓ | ✓ | ✓ | ✗ | ✗ |
| Edit course | ✓ | ✓ | ✓ | C | ✗ |
| Delete course | ✓ | ✓ | ✗ | ✗ | ✗ |
| Assign lecturer to course | ✓ | ✓ | ✓ | ✗ | ✗ |
| View all courses | ✓ | ✓ | ✓ | ✓ | C |
| Enroll in course | ✗ | ✗ | ✗ | ✗ | ✓ |
| View enrolled courses | ✗ | ✗ | ✗ | ✗ | ✓ |

---

### 3.5 Submission Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Create submission | ✗ | ✗ | ✗ | ✗ | ✓ |
| View own submissions | ✗ | ✗ | ✗ | ✗ | ✓ |
| View course submissions | ✗ | ✗ | C | ✓ | ✗ |
| Comment on submissions | ✗ | ✗ | ✗ | ✓ | C |
| Grade submissions | ✗ | ✗ | ✗ | ✓ | ✗ |
| Request corrections | ✗ | ✗ | ✗ | ✓ | ✗ |
| Resubmit after correction | ✗ | ✗ | ✗ | ✗ | ✓ |
| Approve submissions | ✗ | ✗ | ✗ | ✓ | ✗ |
| Export grades | ✗ | ✗ | ✓ | ✓ | ✗ |

---

### 3.6 Group Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Create group | ✗ | ✗ | ✗ | ✗ | ✓ |
| Join group | ✗ | ✗ | ✗ | ✗ | ✓ |
| Leave group | ✗ | ✗ | ✗ | ✗ | ✓ |
| View group members | ✗ | ✗ | C | ✓ | ✓ |
| Manage group (leader) | ✗ | ✗ | ✗ | ✗ | ✓ |

---

### 3.7 Attendance Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Start attendance session | ✗ | ✗ | ✗ | ✓ | ✗ |
| Stop attendance session | ✗ | ✗ | ✗ | ✓ | ✗ |
| Check in to session | ✗ | ✗ | ✗ | ✗ | ✓ |
| View own attendance | ✗ | ✗ | ✗ | ✗ | ✓ |
| View course attendance | ✗ | ✗ | ✓ | ✓ | ✗ |
| Edit attendance records | ✗ | ✗ | ✓ | ✓ | ✗ |
| Export attendance | ✗ | ✗ | ✓ | ✓ | ✗ |

---

### 3.8 Billing Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Set pricing | ✓ | ✗ | ✗ | ✗ | ✗ |
| Create invoice | ✓ | ✓ | ✓ | ✗ | ✗ |
| View all invoices | ✓ | ✓ | ✓ | ✗ | C |
| View own invoices | ✗ | ✗ | ✗ | ✗ | ✓ |
| Make payment | ✗ | ✗ | ✗ | ✗ | ✓ |
| Verify payment | ✓ | ✓ | ✓ | ✗ | ✗ |
| Generate receipt | ✓ | ✓ | ✓ | C | ✓ |
| Waive payment | ✓ | ✓ | ✓ | ✗ | ✗ |

---

### 3.9 Document Generation Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Manage templates | ✓ | ✓ | ✓ | ✗ | ✗ |
| Generate documents | ✗ | ✗ | ✗ | ✓ | ✓ |
| View own documents | ✗ | ✗ | ✗ | ✗ | ✓ |
| View course documents | ✗ | ✗ | ✓ | ✓ | ✗ |
| Download documents | ✓ | ✓ | ✓ | ✓ | ✓ |
| Print documents | ✗ | ✗ | ✓ | ✓ | ✓ |

---

### 3.10 Reporting & Analytics Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| View all analytics | ✓ | ✓ | ✗ | ✗ | ✗ |
| View department analytics | ✓ | ✓ | ✓ | ✗ | ✗ |
| View course analytics | ✓ | ✓ | ✓ | ✓ | ✗ |
| View own analytics | ✓ | ✓ | ✓ | ✓ | ✓ |
| Export reports | ✓ | ✓ | ✓ | ✓ | C |

---

### 3.11 Notification Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| Send system notification | ✓ | ✓ | ✓ | ✗ | ✗ |
| Send course notification | ✗ | ✗ | ✓ | ✓ | ✗ |
| Receive notifications | ✓ | ✓ | ✓ | ✓ | ✓ |
| Mark as read | ✓ | ✓ | ✓ | ✓ | ✓ |

---

### 3.12 System Settings Permissions

| Permission | Super Admin | University Admin | Department Admin | Lecturer | Student |
|------------|:-----------:|:----------------:|:----------------:|:---------:|:-------:|
| System configuration | ✓ | ✗ | ✗ | ✗ | ✗ |
| University settings | ✓ | ✓ | ✗ | ✗ | ✗ |
| Department settings | ✓ | ✓ | ✓ | ✗ | ✗ |
| Email templates | ✓ | ✓ | C | ✗ | ✗ |

---

## 4. Permission Conditions

### 4.1 Department Admin - User Creation
- Can create lecturers and students within their department
- Cannot create other department admins or university admins

### 4.2 Department Admin - Submission View
- Can view submissions for courses in their department

### 4.3 Department Admin - Invoice View
- Can view invoices for students in their department

### 4.4 Department Admin - Template Management
- Can only edit templates for courses in their department

### 4.5 Department Admin - Email Templates
- Can only edit templates for their department

### 4.6 Lecturer - Course View
- Can view courses they are assigned to teach

### 4.7 Lecturer - Student Submission Comment
- Can comment on submissions they are reviewing

### 4.8 Student - Course View
- Can only view courses they are enrolled in

### 4.9 Student - Own Document Export
- Can export reports when permitted by department admin

---

## 5. Implementation in Laravel

### 5.1 Middleware-based Access Control

```php
// routes/web.php
Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::resource('universities', UniversityController::class);
});

Route::middleware(['auth', 'role:university_admin'])->group(function () {
    Route::resource('faculties', FacultyController::class);
});

Route::middleware(['auth', 'role:department_admin'])->group(function () {
    Route::resource('departments.courses', CourseController::class);
});
```

### 5.2 Policy-based Authorization

```php
// app/Policies/SubmissionPolicy.php
public function viewAny(User $user): bool
{
    return $user->isAdmin() || $user->isLecturer();
}

public function view(User $user, Submission $submission): bool
{
    return $user->isAdmin() 
        || $user->id === $submission->user_id
        || $user->isLecturerForCourse($submission->course_id);
}

public function grade(User $user, Submission $submission): bool
{
    return $user->isLecturerForCourse($submission->course_id)
        && $submission->status === SubmissionStatus::APPROVED;
}
```

### 5.3 Gate Definitions

```php
// app/Providers/AuthServiceProvider.php
Gate::define('manage-billing', function (User $user) {
    return in_array($user->role, ['super_admin', 'university_admin', 'department_admin']);
});

Gate::define('view-analytics', function (User $user) {
    return $user->role !== 'student';
});
```

---

## 6. Access Control Summary Table

### 6.1 Quick Reference

| Feature | Super Admin | Univ Admin | Dept Admin | Lecturer | Student |
|---------|:-----------:|:----------:|:----------:|:--------:|:-------:|
| User Management | Full | Department | Limited | None | Own |
| Course Management | Full | Full | Dept | Own | Enrolled |
| Submission Review | View | View | View | Full | Own |
| Attendance Control | View | View | Full | Full | Check-in |
| Billing Management | Full | Univ | Dept | None | Own |
| Document Generation | Full | Full | Dept | Full | Own |
| Analytics | Full | Univ | Dept | Course | Own |
| System Config | Full | None | None | None | None |

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*