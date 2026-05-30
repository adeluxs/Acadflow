# API Endpoint Design

---

## 1. API Overview

### 1.1 Base Configuration
- **Version**: v1
- **Base URL**: `/api/v1`
- **Authentication**: Bearer Token (Sanctum)
- **Response Format**: JSON

### 1.2 Authentication Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|:------------:|
| POST | /auth/register | Register new user | No |
| POST | /auth/login | User login | No |
| POST | /auth/logout | User logout | Yes |
| POST | /auth/password/reset | Reset password | No |
| GET | /auth/me | Get current user | Yes |
| PUT | /auth/me | Update profile | Yes |
| PUT | /auth/me/password | Change password | Yes |

### 1.3 User Endpoints

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /users | List all users | Yes | admin |
| GET | /users/{id} | Get user | Yes | admin |
| POST | /users | Create user | Yes | admin |
| PUT | /users/{id} | Update user | Yes | admin |
| DELETE | /users/{id} | Delete user | Yes | super_admin |

---

## 2. Academic Endpoints

### 2.1 Courses

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /courses | List courses | Yes | - |
| GET | /courses/{id} | Get course details | Yes | - |
| POST | /courses | Create course | Yes | dept_admin |
| PUT | /courses/{id} | Update course | Yes | dept_admin |
| DELETE | /courses/{id} | Delete course | Yes | dept_admin |
| GET | /courses/{id}/enrollments | List enrollments | Yes | lecturer |
| POST | /courses/{id}/enroll | Enroll in course | Yes | student |

---

### 2.2 Submissions

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /submissions | List submissions | Yes | - |
| GET | /submissions/{uuid} | Get submission | Yes | owner/lecturer |
| POST | /submissions | Create submission | Yes | student |
| PUT | /submissions/{uuid} | Update submission | Yes | owner |
| DELETE | /submissions/{uuid} | Delete draft | Yes | owner |
| POST | /submissions/{uuid}/submit | Submit work | Yes | student |
| POST | /submissions/{uuid}/upload | Upload file | Yes | student |
| GET | /submissions/{uuid}/versions | List versions | Yes | owner/lecturer |
| GET | /submissions/{uuid}/download | Download file | Yes | owner/lecturer |

### 2.3 Submission Comments

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /submissions/{uuid}/comments | List comments | Yes | owner/lecturer |
| POST | /submissions/{uuid}/comments | Add comment | Yes | lecturer |
| PUT | /comments/{id} | Update comment | Yes | commenter |
| DELETE | /comments/{id} | Delete comment | Yes | commenter |

### 2.4 Grades

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /submissions/{uuid}/grade | Get grade | Yes | owner/lecturer |
| POST | /submissions/{uuid}/grade | Submit grade | Yes | lecturer |
| PUT | /submissions/{uuid}/grade | Update grade | Yes | lecturer |
| POST | /submissions/{uuid}/approve | Approve submission | Yes | lecturer |
| POST | /submissions/{uuid}/reject | Reject submission | Yes | lecturer |
| POST | /submissions/{uuid}/request-correction | Request correction | Yes | lecturer |

---

### 2.5 Rubrics

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /rubrics | List rubrics | Yes | lecturer |
| GET | /rubrics/{id} | Get rubric | Yes | lecturer |
| POST | /rubrics | Create rubric | Yes | dept_admin |
| PUT | /rubrics/{id} | Update rubric | Yes | dept_admin |
| DELETE | /rubrics/{id} | Delete rubric | Yes | dept_admin |

---

## 3. Group Endpoints

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /groups | List groups | Yes | - |
| GET | /groups/{uuid} | Get group | Yes | member |
| POST | /groups | Create group | Yes | student |
| PUT | /groups/{uuid} | Update group | Yes | leader |
| DELETE | /groups/{uuid} | Delete group | Yes | leader |
| POST | /groups/{uuid}/invite | Invite member | Yes | leader |
| POST | /groups/{uuid}/join | Join group | Yes | student |
| POST | /groups/{uuid}/leave | Leave group | Yes | member |
| POST | /groups/{uuid}/lock | Lock submission | Yes | leader |

---

## 4. Attendance Endpoints

### 4.1 Sessions

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /attendance/sessions | List sessions | Yes | lecturer |
| GET | /attendance/sessions/{uuid} | Get session | Yes | lecturer |
| POST | /attendance/sessions | Start session | Yes | lecturer |
| PUT | /attendance/sessions/{uuid} | Update session | Yes | lecturer |
| POST | /attendance/sessions/{uuid}/close | Close session | Yes | lecturer |
| GET | /attendance/sessions/{uuid}/qr | Get QR code | Yes | student |
| GET | /attendance/sessions/active | Get active session | Yes | student |

### 4.2 Check-In

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| POST | /attendance/check-in | Check in | Yes | student |
| GET | /attendance/my-attendance | My attendance | Yes | student |

### 4.3 Records

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /attendance/records | List records | Yes | lecturer |
| GET | /attendance/records/export | Export records | Yes | lecturer |
| PUT | /attendance/records/{id} | Update record | Yes | lecturer |
| POST | /attendance/records/{id}/verify | Verify record | Yes | lecturer |

---

## 5. Billing Endpoints

### 5.1 Invoices

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /invoices | List invoices | Yes | - |
| GET | /invoices/{uuid} | Get invoice | Yes | owner |
| POST | /invoices | Create invoice | Yes | admin |
| PUT | /invoices/{uuid} | Update invoice | Yes | admin |
| POST | /invoices/{uuid}/send | Send invoice | Yes | admin |

### 5.2 Payments

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /payments | List payments | Yes | admin |
| POST | /payments/verify | Verify payment | Yes | admin |
| POST | /payments/initiate | Initiate payment | Yes | student |
| GET | /payments/{uuid} | Get payment | Yes | owner |

### 5.3 Subscriptions

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /subscriptions | List subscriptions | Yes | admin |
| POST | /subscriptions | Create subscription | Yes | super_admin |
| PUT | /subscriptions/{id} | Update subscription | Yes | super_admin |

---

## 6. Document Endpoints

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /documents/templates | List templates | Yes | admin |
| POST | /documents/templates | Create template | Yes | dept_admin |
| PUT | /documents/templates/{id} | Update template | Yes | dept_admin |
| GET | /documents/generated | List generated docs | Yes | - |
| POST | /documents/generate | Generate PDF | Yes | student |
| GET | /documents/generated/{uuid}/download | Download PDF | Yes | owner |

---

## 7. Reporting Endpoints

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /reports/submissions | Submission report | Yes | admin |
| GET | /reports/attendance | Attendance report | Yes | admin |
| GET | /reports/billing | Billing report | Yes | admin |
| GET | /reports/courses | Course report | Yes | admin |
| GET | /reports/export | Export report | Yes | admin |
| GET | /reports/analytics | Analytics data | Yes | admin |

---

## 8. Notification Endpoints

| Method | Endpoint | Description | Auth Required | Permissions |
|--------|----------|-------------|:-------------:|-------------|
| GET | /notifications | List notifications | Yes | - |
| PUT | /notifications/{id}/read | Mark as read | Yes | owner |
| PUT | /notifications/read-all | Mark all as read | Yes | - |
| GET | /notifications/settings | Get settings | Yes | - |
| PUT | /notifications/settings | Update settings | Yes | - |

---

## 9. Department/Faculty Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|:-------------:|
| GET | /universities | List universities | Yes |
| GET | /universities/{id} | Get university | Yes |
| POST | /universities | Create university | Yes |
| PUT | /universities/{id} | Update university | Yes |
| GET | /faculties | List faculties | Yes |
| GET | /departments | List departments | Yes |

---

## 10. Response Formats

### 10.1 Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    "id": 1,
    "name": "Example"
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

### 10.2 Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required"],
    "password": ["The password must be at least 8 characters"]
  },
  "code": "VALIDATION_ERROR"
}
```

### 10.3 Paginated Response

```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [...],
  "links": {
    "first": "/api/v1/resources?page=1",
    "last": "/api/v1/resources?page=7",
    "prev": null,
    "next": "/api/v1/resources?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 7,
    "links": [
      {"url": null, "label": "&laquo; Previous", "active": false},
      {"url": "/api/v1/resources?page=1", "label": "1", "active": true},
      {"url": "/api/v1/resources?page=2", "label": "2", "active": false}
    ],
    "path": "/api/v1/resources",
    "per_page": 15,
    "to": 15,
    "total": 100
  }
}
```

---

## 11. HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

---

## 12. API Versioning Strategy

- Version in URL path: `/api/v1/`
- Maintain backward compatibility
- Deprecate old endpoints with notice
- Support two versions simultaneously during transition

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*