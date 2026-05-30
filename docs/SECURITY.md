# Security Plan

---

## 1. Authentication Security

### 1.1 User Authentication

| Security Measure | Implementation |
|----------------|--------------|
| Password Hashing | Bcrypt with cost factor 10 |
| Minimum Password | 8 characters, mixed case, numbers |
| Login Throttling | 5 attempts per minute |
| Session Timeout | 30 minutes idle |
| Concurrent Sessions | 3 devices maximum |
| Two-Factor Auth | Optional for lecturers/admins |

### 1.2 Registration Controls

- Email verification required before activation
- Student ID validation against university records
- Department approval for lecturer accounts
- Admin approval for admin accounts

---

## 2. Authorization Security

### 2.1 Role-Based Access Control (RBAC)

```
Implementation: Laravel Policies + Gates
```

| Component | Implementation |
|-----------|--------------|
| Middleware | Role middleware checks |
| Controllers | Policy authorization |
| Routes | Route-level protection |
| Views | Blade `@can` directives |
| API | Token abilities |

### 2.2 Permission Layers

- **Route Level**: Route middleware
- **Controller Level**: Policy checks
- **Model Level**: Gate definitions
- **View Level**: Blade directives

---

## 3. Data Security

### 3.1 Input Validation

| Validation Layer | Implementation |
|----------------|--------------|
| Form Requests | Laravel FormRequest classes |
| Custom Rules | Custom validation rules |
| File Validation | Type, size, malware scanning |
| API Validation | API Resource validation |

### 3.2 Output Encoding

- All user input escaped in Blade templates
- JSON encoding for API responses
- HTML sanitization for user-generated content

### 3.3 SQL Injection Prevention

- Eloquent ORM uses parameterized queries
- No raw query building
- Query builder with bindings

---

## 4. File Security

### 4.1 Upload Restrictions

| Restriction | Value |
|------------|-------|
| Max File Size | 50MB |
| Allowed Types | PDF, DOCX, ZIP, PNG, JPG |
| Scan Malware | ClamAV (if available) |
| Rename | UUID-based safe names |
| Storage | Private S3 bucket |

### 4.2 File Access Control

- Signed URLs with expiration
- No direct file access
- Private storage disk
- Path traversal prevention

### 4.3 Download Protection

```php
// Download flow
$file = Storage::disk('private')->get($path);
$url = Storage::disk('private')->temporaryUrl($path, now()->addHour());
```

---

## 5. API Security

### 5.1 Authentication

| Method | Implementation |
|--------|--------------|
| Web | Session + CSRF cookie |
| Mobile/API | Sanctum token (Bearer) |
| Token Expiry | 60 minutes (configurable) |
| Refresh Token | Long-lived (30 days) |

### 5.2 Rate Limiting

| Endpoint | Limit |
|----------|-------|
| Login | 5 per minute |
| API General | 60 per minute |
| API Submissions | 30 per minute |
| Upload | 10 per minute |

### 5.3 CORS Configuration

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_origins' => ['https://app.example.com'],
'allowed_headers' => ['Authorization', 'Content-Type'],
```

---

## 6. Network Security

### 6.1 HTTPS

- TLS 1.3 required in production
- HTTP redirects to HTTPS
- HSTS header enabled

### 6.2 Firewall Rules

| Port | Service |
|------|---------|
| 443 | HTTPS |
| 22 | SSH (key-only) |

---

## 7. Application Security

### 7.1 CSRF Protection

```php
// All forms include CSRF token
@csrf
```

### 7.2 XSS Prevention

- Blade `{{ }}` escapes HTML
- No raw output of user content
- Content Security Policy header

### 7.3 Clickjacking Prevention

```
X-Frame-Options: DENY
```

### 7.4 MIME Sniffing Prevention

```
X-Content-Type-Options: nosniff
```

---

## 8. Audit Logging

### 8.1 Logged Actions

| Category | Actions |
|----------|---------|
| Authentication | Login, logout, failed attempts |
| Authorization | Access denied |
| Data Changes | Create, update, delete |
| Submissions | Submit, grade, approve |
| Billing | Payment, verification |
| Attendance | Session start/end, check-in |

### 8.2 Log Format

```json
{
  "user_id": 1,
  "action": "submission.grade",
  "entity_type": "submission",
  "entity_id": 123,
  "old_values": {...},
  "new_values": {...},
  "ip_address": "192.168.1.1",
  "timestamp": "2024-01-01 12:00:00"
}
```

---

## 9. Security Checklist

### 9.1 Production Checklist

- [ ] HTTPS enforced
- [ ] DEBUG disabled
- [ ] API keys rotated
- [ ] Session timeout configured
- [ ] Rate limiting enabled
- [ ] CSRF protection active
- [ ] Security headers configured
- [ ] Audit logging enabled
- [ ] File upload restrictions
- [ ] Backup strategy in place

### 9.2 Code Review Checklist

- [ ] No hardcoded credentials
- [ ] No SQL injection risks
- [ ] XSS protection in views
- [ ] CSRF tokens on forms
- [ ] Authorization on routes
- [ ] Input validation present
- [ ] File upload validation
- [ ] Error handling complete

---

## 10. Incident Response

### 10.1 Security Incident Types

| Severity | Response Time |
|----------|--------------|
| Critical | 1 hour |
| High | 4 hours |
| Medium | 24 hours |
| Low | 72 hours |

### 10.2 Backup Strategy

- Full backup: Daily
- Incremental: Every 6 hours
- Retention: 30 days
- Offsite: Weekly copy

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*