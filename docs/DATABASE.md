# Full Database Schema

---

## 1. Core Tables

### 1.1 users

**Purpose**: Main user table storing all system users (students, lecturers, admins)

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| university_id | BIGINT | FK → universities.id, Nullable | Associated university |
| department_id | BIGINT | FK → departments.id, Nullable | Associated department |
| student_id | VARCHAR(20) | Unique, Nullable | Student/Lecturer ID |
| first_name | VARCHAR(100) | Not Null | First name |
| last_name | VARCHAR(100) | Not Null | Last name |
| email | VARCHAR(255) | Unique, Not Null | Email address |
| password | VARCHAR(255) | Not Null | Bcrypt hash |
| phone | VARCHAR(20) | Nullable | Phone number |
| avatar | VARCHAR(500) | Nullable | Avatar URL |
| role | ENUM | Not Null | super_admin, university_admin, department_admin, lecturer, student |
| is_active | BOOLEAN | Default true | Account status |
| email_verified_at | TIMESTAMP | Nullable | Email verified timestamp |
| last_login_at | TIMESTAMP | Nullable | Last login timestamp |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |
| deleted_at | TIMESTAMP | Nullable | Soft delete timestamp |

**Indexes**:
- `idx_users_email` ON email
- `idx_users_uuid` ON uuid
- `idx_users_role` ON role
- `idx_users_university` ON university_id

---

### 1.2 roles

**Purpose**: Permission roles for system users

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| name | VARCHAR(50) | Unique, Not Null | Role name |
| display_name | VARCHAR(100) | Not Null | Display name |
| description | TEXT | Nullable | Role description |
| level | INT | Not Null | Hierarchy level (1=highest) |
| permissions | JSON | Not Null | JSON array of permissions |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 1.3 permissions

**Purpose**: Granular permissions for actions

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| name | VARCHAR(100) | Unique, Not Null | Permission name |
| module | VARCHAR(50) | Not Null | Module name |
| description | TEXT | Nullable | Permission description |
| created_at | TIMESTAMP | Auto | Created timestamp |

---

### 1.4 password_resets

**Purpose**: Password reset tokens

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| email | VARCHAR(255) | PK | Email address |
| token | VARCHAR(255) | Not Null | Reset token |
| created_at | TIMESTAMP | Auto | Created timestamp |

---

### 1.5 personal_access_tokens

**Purpose**: Laravel Sanctum tokens for API authentication

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| tokenable_id | BIGINT | Not Null | User ID |
| tokenable_type | VARCHAR(255) | Not Null | Model class |
| name | VARCHAR(255) | Not Null | Token name |
| token | VARCHAR(64) | Unique | Hashed token |
| abilities | JSON | Not Null | Token abilities |
| last_used_at | TIMESTAMP | Nullable | Last used |
| expires_at | TIMESTAMP | Nullable | Expiration |
| created_at | TIMESTAMP | Auto | Created timestamp |

---

## 2. Academic Structure Tables

### 2.1 universities

**Purpose**: University/Institution records

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| name | VARCHAR(255) | Not Null | University name |
| short_name | VARCHAR(50) | Not Null | Short name |
| code | VARCHAR(10) | Unique | University code |
| email | VARCHAR(255) | Nullable | Contact email |
| phone | VARCHAR(20) | Nullable | Contact phone |
| address | TEXT | Nullable | Address |
| logo | VARCHAR(500) | Nullable | Logo URL |
| website | VARCHAR(255) | Nullable | Website URL |
| timezone | VARCHAR(50) | Default Africa/Lagos | Timezone |
| is_active | BOOLEAN | Default true | Active status |
| settings | JSON | Nullable | Custom settings |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 2.2 faculties

**Purpose**: Faculty/Faculties within a university

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| university_id | BIGINT | FK → universities.id | Parent university |
| name | VARCHAR(255) | Not Null | Faculty name |
| short_name | VARCHAR(50) | Not Null | Short name |
| code | VARCHAR(10) | Unique | Faculty code |
| dean_id | BIGINT | FK → users.id, Nullable | Dean user |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_faculties_university` ON university_id

---

### 2.3 departments

**Purpose**: Departments within a faculty

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| faculty_id | BIGINT | FK → faculties.id | Parent faculty |
| name | VARCHAR(255) | Not Null | Department name |
| short_name | VARCHAR(50) | Not Null | Short name |
| code | VARCHAR(10) | Unique | Department code |
| head_id | BIGINT | FK → users.id, Nullable | HOD user |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_departments_faculty` ON faculty_id

---

### 2.4 courses

**Purpose**: Academic courses offered by departments

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| department_id | BIGINT | FK → departments.id | Owning department |
| code | VARCHAR(20) | Unique | Course code |
| name | VARCHAR(255) | Not Null | Course name |
| description | TEXT | Nullable | Course description |
| credit_hours | INT | Default 3 | Credit hours |
| level | VARCHAR(10) | Not Null | Level (100-500) |
| semester | VARCHAR(10) | Not Null | Semester (1st, 2nd) |
| type | ENUM | Not Null | compulsory, elective |
| max_capacity | INT | Nullable | Max students |
| submission_types | JSON | Not Null | Allowed submission types |
| pass_mark | INT | Default 40 | Pass mark |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_courses_department` ON department_id
- `idx_courses_level_semester` ON (level, semester)

---

### 2.5 sessions

**Purpose**: Academic sessions (e.g., 2023/2024)

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| university_id | BIGINT | FK → universities.id | Owning university |
| name | VARCHAR(20) | Unique | Session name |
| start_date | DATE | Not Null | Session start |
| end_date | DATE | Not Null | Session end |
| is_current | BOOLEAN | Default false | Current session |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 2.6 semesters

**Purpose**: Semesters within a session

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| session_id | BIGINT | FK → sessions.id | Parent session |
| name | VARCHAR(20) | Not Null | Semester name |
| number | INT | Not Null | Semester number (1, 2) |
| start_date | DATE | Not Null | Semester start |
| end_date | DATE | Not Null | Semester end |
| grading_deadline | DATE | Nullable | Grade submission deadline |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_semesters_session` ON session_id

---

### 2.7 lecturer_course_assignments

**Purpose**: Link lecturers to courses they teach

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| course_id | BIGINT | FK → courses.id | Course |
| user_id | BIGINT | FK → users.id | Lecturer |
| semester_id | BIGINT | FK → semesters.id | Semester |
| is_coordinator | BOOLEAN | Default false | Course coordinator |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_lecturer_course` ON (course_id, user_id, semester_id)

---

### 2.8 enrollments

**Purpose**: Student course enrollments

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| user_id | BIGINT | FK → users.id | Student |
| course_id | BIGINT | FK → courses.id | Course |
| semester_id | BIGINT | FK → semesters.id | Semester |
| status | ENUM | Not Null | enrolled, dropped, completed |
| enrolled_at | TIMESTAMP | Auto | Enrollment timestamp |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_enrollments_user` ON user_id
- `idx_enrollments_course` ON course_id
- `idx_enrollments_unique` UNIQUE (user_id, course_id, semester_id)

---

## 3. Submission Tables

### 3.1 submissions

**Purpose**: Main submission records

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| user_id | BIGINT | FK → users.id | Submitter |
| course_id | BIGINT | FK → courses.id | Course |
| semester_id | BIGINT | FK → semesters.id | Semester |
| group_id | BIGINT | FK → groups.id, Nullable | Group (if group work) |
| type | ENUM | Not Null | assignment, project, siwes, group, seminar |
| title | VARCHAR(255) | Not Null | Submission title |
| description | TEXT | Nullable | Description |
| status | ENUM | Not Null | Submission status |
| version | INT | Default 1 | Current version |
| due_date | TIMESTAMP | Nullable | Due date |
| submitted_at | TIMESTAMP | Nullable | Submission timestamp |
| graded_at | TIMESTAMP | Nullable | Grading timestamp |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |
| deleted_at | TIMESTAMP | Nullable | Soft delete |

**Indexes**:
- `idx_submissions_user` ON user_id
- `idx_submissions_course` ON course_id
- `idx_submissions_status` ON status
- `idx_submissions_type` ON type

---

### 3.2 submission_versions

**Purpose**: Individual file uploads for submissions

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| submission_id | BIGINT | FK → submissions.id | Parent submission |
| version_number | INT | Not Null | Version number |
| file_name | VARCHAR(255) | Not Null | Original file name |
| file_path | VARCHAR(500) | Not Null | Storage path |
| file_size | BIGINT | Not Null | File size in bytes |
| mime_type | VARCHAR(100) | Not Null | MIME type |
| uploaded_by | BIGINT | FK → users.id | Uploader |
| is_current | BOOLEAN | Default true | Current version flag |
| created_at | TIMESTAMP | Auto | Created timestamp |

**Indexes**:
- `idx_submission_versions_submission` ON submission_id

---

### 3.3 submission_comments

**Purpose**: Comments on submissions (lecturer feedback)

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| submission_id | BIGINT | FK → submissions.id | Parent submission |
| user_id | BIGINT | FK → users.id | Commenter |
| parent_id | BIGINT | FK → self, Nullable | Parent comment |
| version_id | BIGINT | FK → submission_versions.id, Nullable | Version |
| content | TEXT | Not Null | Comment content |
| type | ENUM | Not Null | general, correction, suggestion |
| status | ENUM | Not Null | pending, addressed, resolved |
| page_number | INT | Nullable | Page number |
| x_position | FLOAT | Nullable | X position |
| y_position | FLOAT | Nullable | Y position |
| is_internal | BOOLEAN | Default false | Internal remarks |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_submission_comments_submission` ON submission_id
- `idx_submission_comments_user` ON user_id

---

### 3.4 submission_grades

**Purpose**: Grades for submissions

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| submission_id | BIGINT | FK → submissions.id | Submission |
| user_id | BIGINT | FK → users.id | Grader |
| score | DECIMAL(5,2) | Nullable | Score (0-100) |
| max_score | DECIMAL(5,2) | Default 100 | Maximum score |
| rubric_id | BIGINT | FK → submission_rubrics.id, Nullable | Rubric used |
| feedback | TEXT | Nullable | Overall feedback |
| is_final | BOOLEAN | Default true | Final grade |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 3.5 submission_rubrics

**Purpose**: Grading rubrics for courses

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| course_id | BIGINT | FK → courses.id | Course |
| name | VARCHAR(255) | Not Null | Rubric name |
| description | TEXT | Nullable | Description |
| criteria | JSON | Not Null | Rubric criteria |
| total_points | INT | Not Null | Total points |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

## 4. Group Tables

### 4.1 groups

**Purpose**: Student groups for group work

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| course_id | BIGINT | FK → courses.id | Course |
| semester_id | BIGINT | FK → semesters.id | Semester |
| name | VARCHAR(255) | Not Null | Group name |
| description | TEXT | Nullable | Description |
| leader_id | BIGINT | FK → users.id | Group leader |
| status | ENUM | Not Null | forming, complete, archived |
| is_locked | BOOLEAN | Default false | Submission locked |
| max_members | INT | Default 6 | Max members |
| formed_at | TIMESTAMP | Nullable | Formation timestamp |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 4.2 group_members

**Purpose**: Group membership records

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| group_id | BIGINT | FK → groups.id | Group |
| user_id | BIGINT | FK → users.id | Member |
| role | ENUM | Not Null | leader, member |
| joined_at | TIMESTAMP | Auto | Join timestamp |
| created_at | TIMESTAMP | Auto | Created timestamp |

**Indexes**:
- `idx_group_members_group` ON group_id
- `idx_group_members_unique` UNIQUE (group_id, user_id)

---

### 4.3 group_contributions

**Purpose**: Track individual contributions to group work

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| group_id | BIGINT | FK → groups.id | Group |
| user_id | BIGINT | FK → users.id | Member |
| submission_version_id | BIGINT | FK → submission_versions.id | Uploaded version |
| contribution_type | ENUM | Not Null | upload, edit, review |
| notes | TEXT | Nullable | Contribution notes |
| created_at | TIMESTAMP | Auto | Created timestamp |

---

## 5. Attendance Tables

### 5.1 attendance_sessions

**Purpose**: Attendance sessions started by lecturers

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| course_id | BIGINT | FK → courses.id | Course |
| semester_id | BIGINT | FK → semesters.id | Semester |
| lecturer_id | BIGINT | FK → users.id | Lecturer |
| qr_code | VARCHAR(255) | Not Null | QR code token |
| qr_expires_at | TIMESTAMP | Not Null | QR expiration |
| started_at | TIMESTAMP | Auto | Start timestamp |
| ended_at | TIMESTAMP | Nullable | End timestamp |
| status | ENUM | Not Null | active, closed, cancelled |
| geofence_radius | INT | Default 100 | Geofence radius (meters) |
| check_in_window | INT | Default 30 | Check-in window (minutes) |
| late_threshold | INT | Default 15 | Late threshold (minutes) |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_attendance_sessions_course` ON course_id
- `idx_attendance_sessions_status` ON status

---

### 5.2 attendance_records

**Purpose**: Individual attendance check-ins

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| session_id | BIGINT | FK → attendance_sessions.id | Session |
| user_id | BIGINT | FK → users.id | Student |
| status | ENUM | Not Null | present, late, absent, invalid, pending |
| check_in_at | TIMESTAMP | Auto | Check-in timestamp |
| latitude | DECIMAL(10,8) | Nullable | GPS latitude |
| longitude | DECIMAL(11,8) | Nullable | GPS longitude |
| ip_address | VARCHAR(45) | Nullable | Device IP |
| device_fingerprint | VARCHAR(255) | Nullable | Device fingerprint |
| is_verified | BOOLEAN | Default false | Verification status |
| verification_notes | TEXT | Nullable | Verification notes |
| created_at | TIMESTAMP | Auto | Created timestamp |

**Indexes**:
- `idx_attendance_records_session` ON session_id
- `idx_attendance_records_unique` UNIQUE (session_id, user_id)

---

### 5.3 attendance_tokens

**Purpose**: Cached QR tokens for quick validation

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| session_id | BIGINT | FK → attendance_sessions.id | Session |
| token | VARCHAR(255) | Not Null | Token |
| expires_at | TIMESTAMP | Not Null | Expiration |
| created_at | TIMESTAMP | Auto | Created timestamp |

---

### 5.4 attendance_rules

**Purpose**: Department attendance policies

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| department_id | BIGINT | FK → departments.id | Department |
| min_attendance | INT | Default 75 | Min attendance % |
| allow_remote | BOOLEAN | Default false | Remote check-in |
| require_gps | BOOLEAN | Default true | GPS required |
| require_wifi | BOOLEAN | Default false | WiFi required |
| qr_refresh_seconds | INT | Default 60 | QR refresh |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

## 6. Billing Tables

### 6.1 subscriptions

**Purpose**: University/department subscriptions

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| university_id | BIGINT | FK → universities.id | University |
| plan_name | VARCHAR(100) | Not Null | Plan name |
| billing_model | ENUM | Not Null | institution, student, hybrid |
| price_per_student | DECIMAL(10,2) | Not Null | Price per student |
| grace_days | INT | Default 7 | Grace days |
| start_date | DATE | Not Null | Start date |
| end_date | DATE | Not Null | End date |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 6.2 invoices

**Purpose**: Student invoices

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| user_id | BIGINT | FK → users.id | Student |
| semester_id | BIGINT | FK → semesters.id | Semester |
| subscription_id | BIGINT | FK → subscriptions.id | Subscription |
| amount | DECIMAL(10,2) | Not Null | Invoice amount |
| status | ENUM | Not Null | pending, paid, overdue, waived |
| due_date | DATE | Not Null | Due date |
| paid_at | TIMESTAMP | Nullable | Payment timestamp |
| payment_method | VARCHAR(50) | Nullable | Payment method |
| transaction_ref | VARCHAR(100) | Nullable | Transaction ref |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

**Indexes**:
- `idx_invoices_user` ON user_id
- `idx_invoices_status` ON status

---

### 6.3 payments

**Purpose**: Payment records

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| invoice_id | BIGINT | FK → invoices.id | Invoice |
| user_id | BIGINT | FK → users.id | Payer |
| amount | DECIMAL(10,2) | Not Null | Amount paid |
| payment_method | ENUM | Not Null | bank_transfer, card, wallet |
| transaction_ref | VARCHAR(100) | Unique | Transaction ref |
| reference | VARCHAR(100) | Nullable | Payment gateway ref |
| status | ENUM | Not Null | pending, verified, failed |
| verified_at | TIMESTAMP | Nullable | Verification timestamp |
| verified_by | BIGINT | FK → users.id, Nullable | Verifier |
| notes | TEXT | Nullable | Notes |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

## 7. Document Tables

### 7.1 document_templates

**Purpose**: Document templates for PDF generation

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| department_id | BIGINT | FK → departments.id, Nullable | Department |
| type | ENUM | Not Null | project, siwes, group, seminar |
| name | VARCHAR(255) | Not Null | Template name |
| description | TEXT | Nullable | Description |
| template_path | VARCHAR(500) | Not Null | Template file path |
| is_default | BOOLEAN | Default false | Default template |
| is_active | BOOLEAN | Default true | Active status |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

### 7.2 generated_documents

**Purpose**: Generated PDF documents

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| user_id | BIGINT | FK → users.id | Owner |
| submission_id | BIGINT | FK → submissions.id, Nullable | Source submission |
| template_id | BIGINT | FK → document_templates.id | Template |
| title | VARCHAR(255) | Not Null | Document title |
| file_path | VARCHAR(500) | Not Null | PDF file path |
| file_size | BIGINT | Not Null | File size |
| status | ENUM | Not Null | processing, ready, failed |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

## 8. Notification Tables

### 8.1 notifications

**Purpose**: In-app notifications

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| user_id | BIGINT | FK → users.id | Recipient |
| type | VARCHAR(100) | Not Null | Notification type |
| title | VARCHAR(255) | Not Null | Title |
| message | TEXT | Not Null | Message |
| data | JSON | Nullable | Additional data |
| read_at | TIMESTAMP | Nullable | Read timestamp |
| created_at | TIMESTAMP | Auto | Created timestamp |

**Indexes**:
- `idx_notifications_user` ON user_id

---

### 8.2 notification_settings

**Purpose**: User notification preferences

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| user_id | BIGINT | FK → users.id | User |
| email_enabled | BOOLEAN | Default true | Email notifications |
| push_enabled | BOOLEAN | Default true | Push notifications |
| submission_notifications | BOOLEAN | Default true | Submission alerts |
| grade_notifications | BOOLEAN | Default true | Grade alerts |
| attendance_notifications | BOOLEAN | Default true | Attendance alerts |
| billing_notifications | BOOLEAN | Default true | Billing alerts |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

## 9. Audit and Logging

### 9.1 audit_logs

**Purpose**: Audit trail for critical actions

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| user_id | BIGINT | FK → users.id, Nullable | Actor |
| action | VARCHAR(100) | Not Null | Action performed |
| entity_type | VARCHAR(255) | Not Null | Entity class |
| entity_id | BIGINT | Not Null | Entity ID |
| old_values | JSON | Nullable | Previous values |
| new_values | JSON | Nullable | New values |
| ip_address | VARCHAR(45) | Nullable | Client IP |
| user_agent | TEXT | Nullable | User agent |
| created_at | TIMESTAMP | Auto | Created timestamp |

**Indexes**:
- `idx_audit_logs_user` ON user_id
- `idx_audit_logs_entity` ON (entity_type, entity_id)

---

## 10. Approval Flow Tables

### 10.1 approval_flows

**Purpose**: Multi-step approval workflows

| Field | Type | Constraints | Description |
|-------|------|--------------|-------------|
| id | BIGINT | PK, Auto-increment | Unique identifier |
| uuid | VARCHAR(36) | Unique, Indexed | Public identifier |
| entity_type | VARCHAR(255) | Not Null | Entity class |
| entity_id | BIGINT | Not Null | Entity ID |
| current_step | INT | Default 1 | Current step |
| status | ENUM | Not Null | pending, approved, rejected |
| approved_by | BIGINT | FK → users.id, Nullable | Approver |
| approved_at | TIMESTAMP | Nullable | Approval timestamp |
| notes | TEXT | Nullable | Approval notes |
| created_at | TIMESTAMP | Auto | Created timestamp |
| updated_at | TIMESTAMP | Auto | Updated timestamp |

---

## 11. ER Diagram Summary

```
┌────────────────────────────────────────────────────────────────────────────┐
│                          DATABASE ERD SUMMARY                             │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  users ──────┬─────── roles                                                │
│    │         │                                                        │
│    │         ├── lecturer_course_assignments ── courses ── enrollments      │
│    │         │                                    │                    │
│    │         │                                    ▼                    │
│    │         ▼                              semesters ── sessions         │
│    │    faculties ── departments ─────────────┘                        │
│    │         │                                                         │
│    │         ▼                                                         │
│    │    groups ���─ group_members                                        │
│    │         │                                                         │
│    │         ▼                                                         │
│    │    submissions ── submission_versions                             │
│    │         │              │                                           │
│    │         ├──────────────┼──────────────┐                          │
│    │         ▼              ▼              ▼                          │
│    │    submission_comments  submission_grades  submission_rubrics    │
│    │                                                             │
│    ▼                                                             │
│ universities ── subscriptions ── invoices ── payments               │
│    │                                                             │
│    ▼                                                             │
│ attendance_sessions ── attendance_records                         │
│    │                                                             │
│    ▼                                                             │
│ document_templates ── generated_documents                         │
│    │                                                             │
│    ▼                                                             │
│ notifications ── notification_settings                           │
│    │                                                             │
│    ▼                                                             │
│ audit_logs                                                       │
│                                                                         │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## 12. Database Conventions

### 12.1 Naming Conventions

- **Tables**: snake_case, plural (users, submissions)
- **Primary Keys**: id (BIGINT, auto-incrementing)
- **Foreign Keys**: `table_name_id` (e.g., course_id)
- **Indexes**: idx_tablename_column
- **Unique Constraints**: uk_tablename_columns

### 12.2 Timestamp Handling

- All tables include `created_at` and `updated_at`
- Use `TIMESTAMP` with CURRENT_TIMESTAMP
- Soft deletes: use `deleted_at` (nullable timestamp)

### 12.3 JSON Fields

- Use JSONB in PostgreSQL for flexibility
- Store arrays and objects appropriately
- Index using GIN for performance

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*