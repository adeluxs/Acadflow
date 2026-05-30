# MVP Scope

---

## 1. MVP Definition

### 1.1 Core Concept

The Minimum Viable Product (MVP) includes the essential features required for the platform to function and deliver value to all user types without unnecessary complexity.

### 1.2 Release Target
- **Timeline**: 3-4 months to first release
- **Users**: Single university, single department
- **Scope**: Core functionality only

---

## 2. MVP Features by Role

### 2.1 Student Features (MVP)

| Feature | Priority | Description |
|---------|:--------:|-------------|
| Course Enrollment | P0 | Browse and enroll in courses |
| Create Submission | P0 | Create and submit assignments |
| File Upload | P0 | Upload documents (max 10 files) |
| Submit Work | P0 | Submit for lecturer review |
| View Submission Status | P0 | Track submission progress |
| View Grades | P0 | View received grades |
| Check Attendance | P0 | Check in via QR code |
| View Invoice | P0 | View and pay invoice |
| Download PDF | P1 | Download approved document |

### 2.2 Lecturer Features (MVP)

| Feature | Priority | Description |
|---------|:--------:|-------------|
| View Submissions | P0 | See all submissions for review |
| Download Files | P0 | Download submission files |
| Add Comments | P0 | Add feedback to submissions |
| Request Correction | P0 | Ask for revisions |
| Grade Submission | P0 | Assign score |
| Approve/Reject | P0 | Final approval decision |
| Start Attendance | P0 | Create QR session |
| Close Attendance | P0 | End attendance session |
| View Attendance | P0 | See attendance records |
| Export Grades | P1 | Export as CSV |

### 2.3 Department Admin Features (MVP)

| Feature | Priority | Description |
|---------|:--------:|-------------|
| Manage Courses | P0 | Create and edit courses |
| Assign Lecturers | P0 | Assign lecturers to courses |
| View All Grades | P0 | See all grades for department |
| View Attendance | P0 | Department attendance reports |
| Manage Invoices | P0 | View and verify payments |
| Create Rubric | P1 | Create grading rubrics |
| Manage Templates | P1 | Document templates |

### 2.4 University Admin Features (MVP)

| Feature | Priority | Description |
|---------|:--------:|-------------|
| View All Users | P0 | User list |
| Manage Departments | P0 | Department management |
| Billing Dashboard | P0 | Revenue overview |
| System Reports | P1 | Analytics |

### 2.5 Super Admin Features (MVP)

| Feature | Priority | Description |
|---------|:--------:|-------------|
| Create University | P0 | Add new university |
| System Settings | P0 | Configure system |
| Pricing Configuration | P0 | Set pricing |

---

## 3. MVP Technical Scope

### 3.1 Authentication
- Email/password login
- Role-based access
- Session management

### 3.2 Database
- PostgreSQL
- Core tables only
- Basic migrations

### 3.3 File Storage
- Local storage (dev) / S3 (prod)
- Basic file upload
- Signed download URLs

### 3.4 API
- RESTful endpoints
- JSON responses
- Sanctum authentication

### 3.5 Queue
- Database driver (MVP)
- Basic job processing

---

## 4. MVP Excluded Features

### 4.1 Deferred to Phase 2

| Feature | Rationale |
|---------|------------|
| Group Work | Complex, defer |
| Multiple Universities | Single-university MVP |
| Advanced Analytics | Basic reports only |
| AI Features | Future enhancement |
| SMS Notifications | Optional |
| Mobile App | Web-only MVP |
| Rubric Builder | Simple grading |
| Geofencing | QR-only MVP |
| Bluetooth Beacons | Future enhancement |

### 4.2 Not in Scope

| Feature | Rationale |
|---------|------------|
| Transcript Generation | Separate system |
| Timetable System | Not required |
| Library Integration | Separate system |
| Video Conferencing | External tool |

---

## 5. MVP Workflows

### 5.1 Submission Workflow (MVP)

```
Student                    Lecturer                System
   │                          │                      │
   ├─── Create Submission ───│                      │
   │                          │                      │
   ├─── Upload Files ───────│                      │
   │                          │                      │
   ├─── Submit ──────────────│                      │
   │                          │                      │
   │                    ┌────│────┐               │
   │                    │Open │    │               │
   │                    │Review     │              │
   │                    └────│────┘               │
   │                          │                      │
   │                    Add Comment              │
   │                          │                      │
   │                    Grade/                   │
   │                    Approve                  │
   │                          │                      │
   │<─── Notify ─────────────│                      │
```

### 5.2 Attendance Workflow (MVP)

```
Lecturer                  System                    Student
   │                          │                        │
   │<─── Start Session ───────│                        │
   │                          │                        │
   │                    Generate QR                 │
   │                          │                        │
   │                    Show QR Code ──────────────│
   │                          │                        │
   │                          │<─── Scan QR ─────────│
   │                          │                        │
   │                          ├─── Validate ────────│
   │                          │                        │
   │                          │<─── Success ────────│
   │                          │                        │
   <─── Live List ────────────│                        │
```

---

## 6. MVP Dashboard Views

### 6.1 Student Dashboard
- Quick summary: Enrolled courses, pending submissions, attendance
- Recent submissions
- Upcoming deadlines
- Attendance summary

### 6.2 Lecturer Dashboard
- Pending reviews count
- Today's attendance sessions
- Recent submissions
- Course overview

### 6.3 Admin Dashboard
- Users overview
- Submission statistics
- Attendance rates
- Payment status

---

## 7. MVP Success Criteria

### 7.1 Functional Criteria

| Criterion | Target |
|-----------|--------|
| Users able to register | 100% |
| Students can submit | 100% |
| File upload works | 100% |
| Lecturer can grade | 100% |
| Attendance works | 100% |
| Payment flow works | 100% |

### 7.2 Performance Criteria

| Metric | Target |
|--------|--------|
| Page load | < 2 seconds |
| File upload | < 30 seconds |
| API response | < 500ms |

### 7.3 Stability Criteria

| Metric | Target |
|--------|--------|
| Uptime | 99% |
| Error rate | < 1% |

---

## 8. MVP Release Checklist

### 8.1 Development Complete
- [ ] All P0 features implemented
- [ ] Basic tests passing
- [ ] Code reviewed

### 8.2 Testing Complete
- [ ] Unit tests
- [ ] Feature tests
- [ ] User acceptance testing

### 8.3 Deployment Ready
- [ ] Production server configured
- [ ] Database migrated
- [ ] Environment configured
- [ ] SSL certificate installed

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*