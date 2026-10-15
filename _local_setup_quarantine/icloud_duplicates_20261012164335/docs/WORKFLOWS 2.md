# Core Workflows

---

## 1. Submission Workflow

### 1.1 State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                      SUBMISSION WORKFLOW                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌──────┐     ┌──────────┐     ┌─────────────┐                │
│   │Draft │────▶│Submitted │────▶│Under Review │                │
│   └──────┘     └──────────┘     └──────┬──────┘                │
│                                         │                       │
│                    ┌────────────────────┼────────────────────┐ │
│                    │                    │                    │ │
│                    ▼                    ▼                    ▼ │
│          ┌───────────────┐    ┌───────────┐    ┌────────────┐    │
│          │Correction   │    │ Approved  │    │ Rejected  │    │
│          │ Requested  │    └────┬────┘    └───────────┘    │
│          └─────┬─────┘         │                             │
│                │               ▼                             │
│                │      ┌─────────────────┐                   │
│                └─────│  Resubmitted    │◀──────             │
│                       └────────┬────────┘                    │
│                                │                              │
│                                ▼                              │
│                         ┌───────────────┐                         │
│                         │  Graded     │                         │
│                         └─────────────┘                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Workflow Steps

#### Step 1: Draft Creation
- Student creates new submission with title
- Student adds description
- Student selects submission type
- Student can save as draft at any time

#### Step 2: File Upload
- Student uploads files (max 50MB per file, 10 files total)
- Supported formats: PDF, DOCX, ZIP, images
- Files are scanned for malware
- Files renamed using safe naming convention

#### Step 3: Submission
- Student clicks "Submit"
- System validates all requirements
- System checks payment status
- Submission timestamp recorded
- Lecturer notified

#### Step 4: Lecturer Review
- Lecturer opens submission
- Status changes to "Under Review"
- Lecturer can add inline comments
- Lecturer can highlight correction areas

#### Step 5: Feedback
- Lecturer decides: Approve, Request Correction, or Reject
- If correction needed: Lecturer specifies corrections
- If rejected: Lecturer provides reason

#### Step 6: Resubmission (if requested)
- Student receives notification
- Student uploads corrected version
- New version created (version++)
- Status changes to "Resubmitted"

#### Step 7: Grading
- Lecturer assigns score
- Feedback added
- Status changes to "Graded"

### 1.3 Business Rules

| Rule | Description |
|------|-------------|
| BR-001 | Student can only submit to enrolled courses |
| BR-002 | Student must have paid for the semester |
| BR-003 | Submissions after deadline marked as late |
| BR-004 | Maximum 20 versions per submission |
| BR-005 | Lecturer can grade only after approval |
| BR-006 | Grade can be modified before semester end |

---

## 2. Grading Workflow

### 2.1 Grading State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                       GRADING WORKFLOW                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────┐    ┌─────────────┐    ┌──────────────┐         │
│  │  Select   │───▶│  Score    │───▶│   Finalize   │         │
│  │  Rubric   │    │  Entry    │    │   Grade     │         │
│  └────────────┘    └─────────────┘    └──────┬───────┘         │
│                                                │                │
│                                                ▼                │
│                                         ┌──────────────┐        │
│                                         │   Graded   │         │
│                                         └────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Grading Methods

#### Method 1: Manual Grading
- Lecturer enters score directly (0-100)
- Lecturer adds overall feedback
- Lecturer can add inline comments

#### Method 2: Rubric-Based Grading
- Lecturer selects predefined rubric
- Lecturer scores each criterion
- System calculates total
- Lecturer adds overall feedback

### 2.3 Rubric Structure Example

```
Rubric: Project Evaluation (100 points)

├── Content Quality (30 points)
│   ├── Excellent (25-30): Comprehensive content
│   ├── Good (20-24): Complete content
│   ├── Fair (15-19): Incomplete content
│   └── Poor (0-14): Missing content
│
├── Organization (25 points)
│   ├── Excellent (21-25): Well organized
│   └── ...
│
├── Research (25 points)
│   └── ...
│
└── Presentation (20 points)
    └── ...
```

### 2.4 Grade Export
- Export to CSV
- Export to PDF
- Export to university format (XLS)

---

## 3. Attendance Workflow

### 3.1 Attendance State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                     ATTENDANCE WORKFLOW                          │
├��────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐    ┌──────────────┐    ┌─────────────────┐  │
│  │   Start    │───▶│    Active    │───▶│  Student        │  │
│  │   Session  │    │   Session    │    │  Check-in       │  │
│  └─────────────┘    └──────┬───────┘    └────────┬────────┘  │
│                            │                     │            │
│                            │    ┌────────────────┴────────┐ │
│                            │    │                          │ │
│                            ▼    ▼                          ▼ │
│                   ┌─────────────┐   ┌──────────────┐          │
│                   │  Close     │   │   Present    │          │
│                   │  Session   │───┤   or Late    │          │
│                   └─────────────┘   └──────────────┘          │
│                            │                                  │
│                            ▼                                  │
│                   ┌─────────────┐                             │
│                   │  Completed │                              │
│                   └─────────────┘                             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Session Lifecycle

#### 3.2.1 Start Session (Lecturer)
1. Lecturer selects course
2. Lecturer clicks "Start Attendance"
3. System generates QR code
4. System sets session active
5. QR code refreshes every 60 seconds
6. Enrolled students notified

#### 3.2.2 Check-In (Student)
1. Student opens attendance in app
2. Student scans QR code
3. System validates:
   - Token is valid and not expired
   - Student is enrolled in course
   - GPS coordinates within geofence
   - Student hasn't checked in yet
4. System records attendance
5. Status: Present (if within 15 min) or Late

#### 3.2.3 Close Session (Lecturer)
1. Lecturer clicks "End Attendance"
2. System closes check-in window
3. System marks all unchecked students as Absent
4. Session marked as completed
5. Lecturer can view summary

### 3.3 Anti-Fraud Measures

| Measure | Description | Implementation |
|---------|-------------|-----------------|
| QR Expiration | QR changes every 60 seconds | Server generates refreshable token |
| Geofencing | Check location against campus | GPS coordinates vs known campus area |
| Device Fingerprint | Identify unique devices | Hash of device characteristics |
| Single Check-In | Only one attempt per session | Database-level uniqueness |
| IP Logging | Track device IP | Record for audit |

### 3.4 Attendance Status Logic

```
IF check_in_time IS NULL:
    status = "absent"
ELSE IF check_in_time <= session.started_at + 15 minutes:
    status = "present"
ELSE IF check_in_time <= session.started_at + late_threshold:
    status = "late"
ELSE:
    status = "invalid"
```

---

## 4. Billing Workflow

### 4.1 Billing State Machine

```
┌���────────────────────────────────────────────────────────────────┐
│                      BILLING WORKFLOW                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────┐    ┌─────────────┐    ┌──────────────┐         │
│  │  Generate │───▶│   Sent to  │───▶│   Payment   │         │
│  │   Invoice │    │   Student  │    │   Pending   │         │
│  └────────────┘    └─────────────┘    └──────┬───────┘         │
│                                                │                │
│                    ┌────────────────────────────┼──────────────┐ │
│                    │                            │              │ │
│                    ▼                            ▼              ▼ │
│          ┌───────────────┐    ┌─────────────┐    ┌─────────┐  │
│          │   Verified   │    │   Failed   │    │  Waived │  │
│          │   (Paid)    │    │            │    │         │  │
│          └───────────────┘    └─────────────┘    └─────────┘  │
│                                                                  │
│  ┌────────────┐    ┌─────────────┐                         │
│  │   Access   │◀───│  Grace     │                         │
│  │   Granted │    │  Expired   │                         │
│  └────────────┘    └─────────────┘                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Invoice Generation

#### Trigger: Semester Activation
1. Admin activates semester
2. System generates invoice for each student
3. Invoice includes:
   - Student details
   - Semester name
   - Amount: ₦500 (or subscription rate)
   - Due date
   - Payment reference

### 4.3 Payment Methods

| Method | Description | Flow |
|--------|-------------|------|
| Bank Transfer | Transfer to school account | Generate reference → Transfer → Verify |
| Card Payment | Pay via payment gateway | Redirect to gateway → Complete → Verify |
| USSD | USSD code payment | Dial code → Confirm → Verify |

### 4.4 Payment Verification

1. Admin receives payment notification
2. Admin verifies via bank or gateway
3. Admin marks invoice as paid
4. System generates receipt
5. Student receives email notification
6. Student access is granted

### 4.5 Access Control Logic

```
IF invoice.status == 'paid':
    grant_access()
ELSE IF days_since_semester_start <= grace_days:
    IF allow_grace_period:
        grant_access()
    ELSE:
        deny_access()
ELSE:
    deny_access()
```

### 4.6 Billing Models

#### Model 1: Institution-Paid
- University pays for all students
- One invoice per university
- University admin verifies

#### Model 2: Student-Paid
- Each student pays individually
- One invoice per student
- Student verifies their payment

#### Model 3: Hybrid
- Institution pays base amount
- Student pays remainder
- Split invoices

---

## 5. Document Generation Workflow

### 5.1 Document Generation State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                 DOCUMENT GENERATION WORKFLOW                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────┐    ┌─────────────┐    ┌──────────────┐         │
│  │  Request  │───▶│   Process   │───▶│  Generating │         │
│  │   PDF     │    │   Queue    │    │    Queue    │         │
│  └────────────┘    └─────────────┘    └──────┬───────┘         │
│                                                │                │
│                                                ▼                │
│                                         ┌──────────────┐     │
│                                         │    Ready     │     │
│                                         │   for Download│    │
│                                         └──────────────┘     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Document Structure

#### 5.2.1 Final Year Project
1. Cover Page (university logo, title)
2. Title Page (student info)
3. Declaration
4. Abstract
5. Table of Contents
6. Chapters (1-8)
7. References
8. Appendices
9. Approval Page

#### 5.2.2 SIWES Report
1. Cover Page
2. Title Page
3. Declaration
4. Abstract
5. Introduction
6. Company Profile
7. Activities
8. Learning Outcomes
9. Conclusion
10. References
11. Appendices

### 5.3 Generation Process

1. Student requests document
2. System validates:
   - Submission must be Approved
   - Student must have paid
   - Template must exist
3. Job dispatched to queue
4. DocumentService merges:
   - Template + Student data
   - Submission content
   - University branding
5. PDF generated (DomPDF or Puppeteer)
6. PDF stored in S3/local based on admin settings
7. User notified
8. User downloads via signed URL

---

## 6. Workflow Integration

### 6.1 Cross-Workflow Dependencies

```
                    SUBMISSION WORKFLOW
                           │
                           ▼
┌───────────────────────────────────────────────────────────────┐
│  Prerequisites:                                               │
│  • Student must be enrolled in course                        │
│  • Student must have paid for semester                       │
│  • Course must be active                                      │
└───────────────────────────────────────────────────────────────┘
                           │
                           ▼
                     GRADING WORKFLOW
                           │
                           ▼
┌───────────────────────────────────────────────────────────────┐
│  Prerequisites:                                               │
│  • Submission must be Approved                               │
│  • Lecturer must be assigned to course                       │
└───────────────────────────────────────────────────────────────┘
                           │
                           ▼
                  DOCUMENT WORKFLOW
                           │
                           ▼
┌───────────────────────────────────────────────────────────────┐
│  Prerequisites:                                               │
│  • Submission must be Graded                                 │
│  • Student must have paid                                    │
│  • Template must be available                                │
└───────────────────────────────────────────────────────────────┘
```

### 6.2 Notification Triggers

| Event | Trigger Notification |
|-------|---------------------|
| Submission Created | Student: Confirmation |
| Submission Submitted | Lecturer: New submission |
| Comment Added | Student: New comment |
| Correction Requested | Student: Action required |
| Resubmission Received | Lecturer: New version |
| Submission Approved | Student: Approved |
| Submission Graded | Student: Grade assigned |
| Attendance Started | Students: Session active |
| Payment Received | Admin, Student |
| Document Ready | Student |

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*