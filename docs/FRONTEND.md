# Frontend Page Map

---

## 1. Public Pages (No Auth Required)

### 1.1 Landing Page
- `/` - Marketing landing page
- `/features` - Feature highlights
- `/pricing` - Pricing information
- `/contact` - Contact form
- `/about` - About information

### 1.2 Authentication
- `/login` - User login
- `/register` - User registration
- `/forgot-password` - Password recovery
- `/reset-password/{token}` - Password reset

---

## 2. Student Dashboard (`/student`)

### 2.1 Dashboard
- `/student/dashboard` - Student overview
- `/student/notifications` - My notifications

### 2.2 Courses
- `/student/courses` - My enrolled courses
- `/student/courses/{id}` - Course details
- `/student/courses/{id}/enroll` - Course enrollment

### 2.3 Submissions
- `/student/submissions` - My submissions list
- `/student/submissions/create` - Create submission
- `/student/submissions/{uuid}` - Submission details
- `/student/submissions/{uuid}/edit` - Edit submission
- `/student/submissions/{uuid}/upload` - Upload files

### 2.4 Groups
- `/student/groups` - My groups
- `/student/groups/create` - Create group
- `/student/groups/{uuid}` - Group details
- `/student/groups/{uuid}/members` - Group members

### 2.5 Attendance
- `/student/attendance` - My attendance
- `/student/attendance/check-in` - Current session check-in
- `/student/attendance/history` - Attendance history

### 2.6 Billing
- `/student/billing` - My invoices
- `/student/billing/{uuid}` - Invoice details
- `/student/billing/{uuid}/pay` - Make payment
- `/student/billing/receipts` - My receipts

### 2.7 Documents
- `/student/documents` - Generated documents
- `/student/documents/{uuid}/download` - Download PDF

### 2.8 Profile
- `/student/profile` - My profile
- `/student/settings` - Settings

---

## 3. Lecturer Dashboard (`/lecturer`)

### 3.1 Dashboard
- `/lecturer/dashboard` - Lecturer overview
- `/lecturer/notifications` - My notifications

### 3.2 Courses
- `/lecturer/courses` - My teaching courses
- `/lecturer/courses/{id}` - Course details
- `/lecturer/courses/{id}/students` - Enrolled students

### 3.3 Submissions (Review)
- `/lecturer/submissions` - All submissions to review
- `/lecturer/submissions/{uuid}` - Review submission
- `/lecturer/submissions/{uuid}/grade` - Grade submission
- `/lecturer/submissions/{uuid}/comment` - Add comments
- `/lecturer/submissions/{uuid}/approve` - Approve
- `/lecturer/submissions/{uuid}/reject` - Reject

### 3.4 Attendance
- `/lecturer/attendance` - Attendance sessions
- `/lecturer/attendance/create` - Start session
- `/lecturer/attendance/{uuid}` - Session details
- `/lecturer/attendance/{uuid}/qr` - QR code display
- `/lecturer/attendance/{uuid}/close` - Close session

### 3.5 Grades
- `/lecturer/grades` - Course grades
- `/lecturer/grades/export` - Export grades

### 3.6 Reports
- `/lecturer/reports` - Course reports

### 3.7 Profile
- `/lecturer/profile` - My profile
- `/lecturer/settings` - Settings

---

## 4. Department Admin Dashboard (`/department-admin`)

### 4.1 Dashboard
- `/department-admin/dashboard` - Dept overview
- `/department-admin/notifications` - My notifications

### 4.2 Department
- `/department-admin/department` - Manage department
- `/department-admin/department/settings` - Dept settings
- `/department-admin/department/courses` - Manage courses

### 4.3 Users
- `/department-admin/users` - Department users
- `/department-admin/users/lecturers` - Lecturers
- `/department-admin/users/students` - Students
- `/department-admin/users/invite` - Invite user

### 4.4 Courses
- `/department-admin/courses` - All courses
- `/department-admin/courses/create` - Create course
- `/department-admin/courses/{id}` - Edit course

### 4.5 Submissions
- `/department-admin/submissions` - All submissions

### 4.6 Attendance
- `/department-admin/attendance` - Attendance reports

### 4.7 Billing
- `/department-admin/billing` - Department billing
- `/department-admin/billing/invoices` - Manage invoices
- `/department-admin/billing/waivers` - Payment waivers

### 4.8 Templates
- `/department-admin/templates` - Document templates
- `/department-admin/templates/create` - Create template

### 4.9 Reports
- `/department-admin/reports` - Department reports

### 4.10 Profile
- `/department-admin/profile` - My profile
- `/department-admin/settings` - Settings

---

## 5. University Admin Dashboard (`/university-admin`)

### 5.1 Dashboard
- `/university-admin/dashboard` - University overview

### 5.2 Structure
- `/university-admin/faculties` - Manage faculties
- `/university-admin/departments` - Manage departments

### 5.3 Billing
- `/university-admin/billing` - University billing
- `/university-admin/subscriptions` - Manage subscriptions

### 5.4 Reports
- `/university-admin/reports` - University reports

---

## 6. Super Admin Dashboard (`/super-admin`)

### 6.1 Dashboard
- `/super-admin/dashboard` - System overview

### 6.2 Universities
- `/super-admin/universities` - Manage universities
- `/super-admin/universities/create` - Create university
- `/super-admin/universities/{id}` - Edit university

### 6.3 Settings
- `/super-admin/settings` - System settings
- `/super-admin/pricing` - Pricing configuration

### 6.4 Reports
- `/super-admin/reports` - System reports

---

## 7. Page Component Map

### 7.1 Common Layouts
- **App Layout**: Main authenticated layout
- **Public Layout**: Landing page layout
- **Auth Layout**: Login/register layout
- **Dashboard Layout**: Dashboard container
- **Blank Layout**: Modal/full-width pages

### 7.2 Shared Components
- Navigation Bar
- Sidebar Menu
- Header
- Footer
- Breadcrumbs
- Page Title
- Search Bar
- Filters
- Pagination
- Data Table
- Form
- Modal
- Alert/Toast
- Loading Spinner
- Empty State

### 7.3 Student Components
- Submission Card
- Submission Status Badge
- File Upload List
- Group Member List
- Attendance Status Card
- Invoice Card
- Course Card

### 7.4 Lecturer Components
- Submission Review Panel
- Grading Form
- Rubric Editor
- Attendance Session Panel
- Live Attendance List

### 7.5 Admin Components
- Department Card
- User List Table
- Course Management Table
- Invoice Management Table
- Analytics Charts
- Payment List Table

---

## 8. Navigation Structure

### 8.1 Student Navigation
```
Student
├── Dashboard
├── Courses
│   ├── My Courses
│   ├── Enroll
│   └── Course Details
├── Submissions
│   ├── All Submissions
│   ├── Create New
│   └── Submission Details
├── Groups
│   ├── My Groups
│   ├── Create Group
│   └── Group Details
├── Attendance
│   ├── Check In
│   ├── My Attendance
│   └── History
├── Billing
│   ├── Invoices
│   ├── Payments
│   └── Receipts
├── Documents
│   └── Generated Documents
├── Notifications
└── Profile
```

### 8.2 Lecturer Navigation
```
Lecturer
├── Dashboard
├── Courses
│   ├── My Courses
│   └── Course Details
├── Submissions
│   ├── Pending Reviews
│   ├── All Submissions
│   └── Review Details
├── Attendance
│   ├── Sessions
│   ├── Start Session
│   └── Reports
├── Grades
│   ├── Course Grades
│   └── Export
├── Reports
└── Profile
```

---

## 9. Responsive Breakpoints

| Breakpoint | Viewport | Target |
|-----------|---------|--------|
| Mobile | < 640px | Phones |
| Tablet | 640px - 1024px | Tablets |
| Desktop | > 1024px | Desktops |

---

## 10. UX Guidelines

### 10.1 Form Guidelines
- Label above input
- Inline validation
- Clear error messages
- Loading states during submission

### 10.2 Navigation Guidelines
- Breadcrumbs for deep navigation
- Back links for detail pages
- Clear CTAs (primary actions in buttons)

### 10.3 List Guidelines
- Search/filter functionality
- Sort options
- Pagination (or infinite scroll)
- Empty state illustrations

### 10.4 Status Indicators
- Color-coded status badges
- Icon indicators
- Progress bars for multi-step flows

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*