# Product Requirements Document (PRD)
## University Academic Management Platform

---

## 1. Executive Summary

### Project Name
**UniAcademic** - University Academic Management Platform

### Project Type
Full-stack Laravel web application for university academic workflow management

### Core Feature Summary
A comprehensive academic management system enabling students to submit assignments, projects, SIWES reports, and group work; lecturers to review, grade, and provide feedback; administrators to manage departments, billing, and analytics; and an attendance system with fraud-resistant geofencing capabilities.

### Target Users
- **Primary**: University students (undergraduate and postgraduate)
- **Secondary**: Lecturers and academic staff
- **Tertiary**: Department and university administrators
- **Support**: Finance and billing teams

---

## 2. Problem Statement

### Current Challenges
1. **Fragmented Systems**: Universities use multiple disconnected systems for submissions, grading, attendance, and billing
2. **Manual Processes**: Paper-based submissions, manual grading, and face-value attendance marking
3. **Limited Visibility**: Students and lecturers have poor visibility into submission status and history
4. **Billing Complexity**: Semester-based payments are tracked manually with no integration
5. **Document Generation**: Final project printing requires external services or manual formatting
6. **Attendance Fraud**: Students can mark attendance remotely without physical presence

### Target Improvements
1. Centralized academic workflow with one source of truth
2. Digital submission with version history and audit trail
3. Real-time status tracking for all stakeholders
4. Integrated billing with payment verification and access control
5. Automated document generation with university-compliant templates
6. Hybrid fraud-resistant attendance system using QR codes and geofencing

---

## 3. Product Vision

### Vision Statement
To create a unified, modern academic management platform that streamlines the entire academic workflow from submission to grading to document generation, while ensuring fairness through fraud-resistant attendance and flexible billing options.

### Success Metrics
- **Adoption**: 80% student and lecturer adoption within first semester
- **Efficiency**: 50% reduction in submission processing time
- **Accuracy**: 99.9% attendance accuracy with fraud prevention
- **Revenue**: 100% payment collection for active semesters
- **Satisfaction**: 4.5+ average user satisfaction rating

---

## 4. Functional Requirements

### 4.1 Authentication and Authorization

#### 4.1.1 User Registration
- **R-001**: Students register using institutional email and student ID
- **R-002**: Lecturers are invited by department admins
- **R-003**: Admins are created by super admins
- **R-004**: Password must meet minimum security requirements (8+ chars, mixed case, numbers)
- **R-005**: Email verification required before account activation

#### 4.1.2 Login and Session Management
- **R-006**: Login with email and password
- **R-007**: Two-factor authentication optional for lecturers and admins
- **R-008**: Session timeout after 30 minutes of inactivity
- **R-009**: Password reset via email link
- **R-010**: Concurrent session limit: 3 devices

#### 4.1.3 Role-Based Access Control (RBAC)
- **R-011**: Seven distinct roles with granular permissions
- **R-012**: Permissions checked at route and action level
- **R-013**: Role hierarchy: Super Admin > University Admin > Department Admin > Lecturer > Student

### 4.2 Academic Submissions

#### 4.2.1 Submission Types
- **R-014**: Assignment submissions (individual)
- **R-015**: Project submissions (individual or group)
- **R-016**: SIWES reports (individual)
- **R-017**: Group reports (team-based)
- **R-018**: Seminar papers (individual)

#### 4.2.2 Submission Creation
- **R-019**: Create new submission with title, description, and course selection
- **R-020**: Upload files (PDF, DOCX, images) up to 50MB per file
- **R-021**: Rich text editor for inline content
- **R-022**: Save as draft before final submission
- **R-023**: Maximum 10 files per submission
- **R-024**: File naming follows: `{StudentID}_{SubmissionType}_{Date}.{ext}`

#### 4.2.3 Submission States
| State | Description |
|-------|-------------|
| Draft | Saved but not submitted |
| Submitted | Awaiting lecturer review |
| Under Review | Lecturer has opened the submission |
| Correction Requested | Lecturer requested revisions |
| Resubmitted | Student uploaded corrected version |
| Approved | Final version approved by lecturer |
| Graded | Score has been assigned |
| Archived | Moved to archive after semester end |

#### 4.2.4 Version Control
- **R-025**: Every upload creates a new version
- **R-026**: Version history shows all uploads with timestamps
- **R-027**: Lecturers can compare any two versions
- **R-028**: Original version is never overwritten
- **R-029**: Maximum 20 versions per submission

### 4.3 Lecturer Review and Grading

#### 4.3.1 Review Tools
- **R-030**: Open submission in PDF viewer with annotation support
- **R-031**: Add inline comments on specific sections
- **R-032**: Highlight text and mark as correction area
- **R-033**: Request specific corrections with deadline
- **R-034**: Approve or reject submission with comments
- **R-035**: Request resubmission with feedback

#### 4.3.2 Grading
- **R-036**: Manual score entry (0-100)
- **R-037**: Rubric-based grading with predefined criteria
- **R-038**: Add overall feedback comments
- **R-039**: Grade submission after approval
- **R-040**: Allow grade modification before semester end
- **R-041**: Export grades as CSV or PDF

#### 4.3.3 Review Workflow
```
Submitted → Under Review → [Correction Requested | Approved | Rejected]
Correction Requested → Resubmitted → Under Review → Approved
Approved → Graded
Rejected → Archived
```

### 4.4 Group Work Management

#### 4.4.1 Group Formation
- **R-042**: Students create groups with name and description
- **R-043**: Group leader invites members via email or student ID
- **R-044**: Maximum group size: 6 students
- **R-045**: Group formation deadline enforced
- **R-046**: Group members can leave before deadline

#### 4.4.2 Group Submission
- **R-047**: One submission represents entire group
- **R-048**: All members can upload to group submission
- **R-049**: Group leader can lock submissions from other members
- **R-050**: Group contribution log tracks who uploaded what

### 4.5 Attendance System

#### 4.5.1 Session Management
- **R-051**: Only lecturers can start attendance sessions
- **R-052**: Session linked to specific course and lecture slot
- **R-053**: Session duration: configurable (default 30 minutes)
- **R-054**: QR code refreshes every 60 seconds
- **R-055**: Lecturer can extend or close session early
- **R-056**: Only enrolled students can check in

#### 4.5.2 Check-In Methods
- **R-057**: Primary: QR code scan via mobile app
- **R-058**: Secondary: GPS coordinates verification
- **R-059**: Tertiary: Campus Wi-Fi verification (optional)
- **R-060**: Geofence radius: configurable per campus (default 100m)

#### 4.5.3 Attendance Status
| Status | Criteria |
|--------|----------|
| Present | Checked in within first 15 minutes |
| Late | Checked in after 15 minutes |
| Absent | Did not check in |
| Invalid | Attempt outside session or suspicious |
| Pending | Checked in, awaiting validation |

#### 4.5.4 Anti-Fraud Measures
- **R-061**: QR code expires every 60 seconds
- **R-062**: GPS coordinates must match campus location
- **R-063**: Device fingerprinting
- **R-064**: Single check-in per session
- **R-065**: Suspicious attempts logged and flagged

#### 4.5.5 Attendance Analytics
- **R-066**: Per-student attendance percentage
- **R-067**: Per-course attendance summary
- **R-068**: Per-lecturer attendance tracking
- **R-069**: Department-wide attendance reports
- **R-070**: Export to CSV and PDF

### 4.6 Billing and Payments

#### 4.6.1 Pricing Model
- **R-071**: Base price: ₦500 per student per semester
- **R-072**: Institution-paid model (school covers all)
- **R-073**: Student-paid model (individual payment)
- **R-074**: Hybrid model (split payment)

#### 4.6.2 Payment Workflow
- **R-075**: Generate invoice for active semester
- **R-076**: Support bank transfer and card payments
- **R-077**: Verify payment and update status
- **R-078**: Generate payment receipt
- **R-079**: Send payment confirmation email

#### 4.6.3 Access Control
- **R-080**: Unpaid students cannot submit academic work
- **R-081**: Grace period: 7 days after semester start (configurable)
- **R-082**: Admin can grant temporary access
- **R-083**: Payment waiver for scholarship students

#### 4.6.4 Billing Dashboard
- **R-084**: View payment status per student
- **R-085**: Generate invoice reports
- **R-086**: Track revenue per department
- **R-087**: Export financial reports

### 4.7 Document Generation

#### 4.7.1 Supported Document Types
- **R-088**: Final year project
- **R-089**: SIWES report
- **R-090**: Group project report
- **R-091**: Seminar paper

#### 4.7.2 Document Structure
- Cover page (university logo, title, student info)
- Title page
- Declaration page
- Abstract
- Table of contents
- Chapters (1-8)
- References
- Appendices
- Approval page

#### 4.7.3 Generation Features
- **R-092**: Merge student data into template
- **R-093**: Generate PDF with proper pagination
- **R-094**: Include university branding
- **R-095**: Headers and footers on all pages
- **R-096**: Auto-generated table of contents
- **R-097**: Page numbering
- **R-098**: Signature lines on approval page

#### 4.7.4 Export Options
- **R-099**: Download as PDF
- **R-100**: Print-ready format
- **R-101**: Batch generation for entire class

### 4.8 Notifications

#### 4.8.1 Notification Types
| Type | Trigger | Recipients |
|------|---------|------------|
| Submission Received | Student submits | Lecturer |
| Comment Added | Lecturer comments | Student |
| Correction Requested | Lecturer requests revision | Student |
| Approval Granted | Lecturer approves | Student |
| Payment Received | Payment verified | Admin, Student |
| Attendance Started | Lecturer starts session | Enrolled students |
| Attendance Closed | Session ends | Lecturer |
| Deadline Approaching | 24 hours before due | Student |
| Overdue Submission | Past due date | Student, Lecturer |

#### 4.8.2 Delivery Channels
- **R-102**: In-app notifications (real-time)
- **R-103**: Email notifications
- **R-103**: Email notifications

### 4.9 Analytics and Reporting

#### 4.9.1 Dashboard Reports
- **R-106**: Submission counts by type and status
- **R-107**: Late submission percentage
- **R-108**: Lecturer review turnaround time
- **R-109**: Attendance rates by course and department
- **R-110**: Payment compliance rate
- **R-111**: Course-level performance metrics
- **R-112**: Department usage summary

#### 4.9.2 Export Formats
- **R-113**: CSV export
- **R-114**: PDF report generation
- **R-115**: Scheduled email reports

---

## 5. Non-Functional Requirements

### 5.1 Performance Requirements
- **NFR-001**: Page load time < 2 seconds
- **NFR-002**: API response time < 500ms (95th percentile)
- **NFR-003**: File upload handled within 30 seconds
- **NFR-004**: PDF generation < 10 seconds
- **NFR-005**: Support 10,000 concurrent users

### 5.2 Scalability Requirements
- **NFR-006**: Horizontal scaling with load balancer
- **NFR-007**: Database sharding for multi-university support
- **NFR-008**: CDN for static assets
- **NFR-009**: File storage abstraction (S3-compatible)

### 5.3 Security Requirements
- **NFR-010**: All data encrypted at rest (AES-256)
- **NFR-011**: All data encrypted in transit (TLS 1.3)
- **NFR-012**: CSRF protection on all forms
- **NFR-013**: XSS protection on all user inputs
- **NFR-014**: SQL injection prevention via Eloquent
- **NFR-015**: Rate limiting on login and API endpoints
- **NFR-016**: Audit logging for all critical actions
- **NFR-017**: File malware scanning on upload

### 5.4 Availability Requirements
- **NFR-018**: 99.9% uptime (excluding scheduled maintenance)
- **NFR-019**: Scheduled maintenance window: Sunday 2am-4am
- **NFR-020**: Automated backup every 6 hours
- **NFR-021**: 30-day backup retention

### 5.5 Usability Requirements
- **NFR-022**: Mobile-responsive design
- **NFR-023**: WCAG 2.1 AA accessibility compliance
- **NFR-024**: Support for Chrome, Firefox, Safari, Edge
- **NFR-025**: Intuitive navigation with clear CTAs

---

## 6. Out of Scope (v1.0)

The following features are explicitly NOT included in v1.0:
- AI-powered grammar correction
- Plagiarism detection
- Video conferencing integration
- SMS/WhatsApp notifications
- Mobile native apps (web-only for v1.0)
- Multi-year academic planning
- Transcript generation
- Timetable/scheduling system
- Library integration
- Learning Management System (LMS) features

---

## 7. Assumptions

### 7.1 Technical Assumptions
- Universities have reliable internet connectivity
- Students have access to smartphones for attendance
- Lecturers have computers for reviewing submissions
- University provides campus Wi-Fi for geofencing

### 7.2 Operational Assumptions
- University IT department can handle DNS and SSL
- Training will be provided to users
- Super admin will be the initial system administrator

### 7.3 Data Assumptions
- Student data imported via CSV or API
- Course data managed by department admins
- Semester dates configurable per university

---

## 8. Glossary

| Term | Definition |
|------|------------|
| SIWES | Student Industrial Work Experience Scheme |
| RBAC | Role-Based Access Control |
| CRUD | Create, Read, Update, Delete |
| PDF | Portable Document Format |
| QR | Quick Response (code) |
| GPS | Global Positioning System |
| CDN | Content Delivery Network |
| S3 | Amazon Simple Storage Service |
| TLS | Transport Layer Security |
| CSV | Comma-Separated Values |

---

## 9. Appendix

### 9.1 User Stories (Key)

**Student Stories:**
- As a student, I want to submit my assignment so that my lecturer can review it
- As a student, I want to see my submission status so I know what's happening
- As a student, I want to receive corrections so I can improve my work
- As a student, I want to generate my final project as PDF so I can print it
- As a student, I want to mark my attendance in class so my presence is recorded

**Lecturer Stories:**
- As a lecturer, I want to review and grade submissions so I can provide feedback
- As a lecturer, I want to request corrections so students can improve
- As a lecturer, I want to start attendance so I can take class attendance
- As a lecturer, I want to export grades so I can submit to the department

**Admin Stories:**
- As an admin, I want to manage departments and courses so the system reflects reality
- As an admin, I want to view billing so I can track payments
- As an admin, I want to generate reports so I can analyze usage

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*
*Status: Approved for Development*