# System Architecture Overview

---

## 1. Architecture Style

### 1.1 Primary Approach: Modular Monolith

The system follows a **modular monolith** architecture using Laravel. This approach provides:

- **Maintainability**: Clear separation of concerns within bounded contexts
- **Deployability**: Single deployment unit with the flexibility to extract modules later
- **Performance**: No network overhead between modules (unlike microservices)
- **Simplicity**: Single codebase for the team to understand and maintain

### 1.2 Module Boundaries

```
┌─────────────────────────────────────────────────────────────────┐
│                      Presentation Layer                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │   Web UI    │  │  Dashboard  │  │    API      │             │
│  │   (Blade)   │  │   (Vue/React)│  │   (REST)    │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Application Layer                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │  Actions    │  │Controllers  │  │  Middleware │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Domain Layer                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │  Models     │  │  Services   │  │   Events    │             │
│  │  (Entities) │  │  (Business) │  │   (Domain)  │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Infrastructure Layer                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │  Database   │  │   Queues    │  │   Storage   │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Technology Stack

### 2.1 Backend Stack

| Component | Technology | Justification |
|-----------|------------|---------------|
| Framework | Laravel 11 | PHP's most mature framework with excellent documentation |
| PHP Version | PHP 8.3+ | Latest stable with JIT support |
| Database | PostgreSQL 15+ | Better jsonb support, full-text search, and reliability |
| Queue | Redis + Laravel Horizon | Background job management with monitoring |
| Cache | Redis | Session and cache storage |
| Search | Meilisearch (optional) | Full-text search for submissions |
| Storage | S3-compatible (Local/MinIO for dev) | Abstracted file storage |

### 2.2 Frontend Stack

| Component | Technology | Justification |
|-----------|------------|---------------|
| Primary UI | Laravel Blade + Livewire | Server-rendered with interactive components |
| Dashboard | Vue 3 + Inertia | Rich, responsive dashboards |
| Styling | Tailwind CSS | Utility-first, maintainable |
| Icons | Heroicons | Consistent with Tailwind |
| Charts | Chart.js or ApexCharts | Data visualization |

### 2.3 Infrastructure Stack

| Component | Technology | Justification |
|-----------|------------|---------------|
| Web Server | Nginx | Production-grade HTTP server |
| Container | Docker | Consistent environments |
| CI/CD | GitHub Actions | Integrated with repo |
| Monitoring | Laravel Telescope + Sentry | Development and production debugging |

---

## 3. Application Modules

### 3.1 Module Overview

```
app/
├── Modules/
│   ├── Academics/          # Core academic functionality
│   │   ├── Entities/       # Domain models
│   │   ├── Services/       # Business logic
│   │   └── Http/           # Controllers, Requests, Routes
│   │
│   ├── Attendance/         # Attendance system
│   │   ├── Entities/
│   │   ├── Services/
│   │   └── Http/
│   │
│   ├── Billing/            # Payments and subscriptions
│   │   ├── Entities/
│   │   ├── Services/
│   │   └── Http/
│   │
│   ├── Documents/          # Document generation
│   │   ├── Entities/
│   │   ├── Services/
│   │   └── Http/
│   │
│   ├── Users/              # User management and auth
│   │   ├── Entities/
│   │   ├── Services/
│   │   └── Http/
│   │
│   └── Reporting/          # Analytics and reports
│       ├── Entities/
│       ├── Services/
│       └── Http/
│
├── Actions/                # Laravel Actions (thin controllers)
├── DTOs/                   # Data Transfer Objects
├── Enums/                  # PHP Enums for statuses
├── Events/                 # Domain events
├── Jobs/                   # Queue jobs
├── Listeners/              # Event listeners
├── Mail/                   # Email templates
├── Notifications/          # Notification channels
├── Observers/              # Model observers
├── Policies/               # Authorization policies
├── Services/               # Cross-cutting services
└── Traits/                 # Reusable traits
```

### 3.2 Module Dependencies

```
                    ┌──────────────┐
                    │    Users     │
                    │   (Core)     │
                    └──────┬───────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               ▼
    ┌────────────┐  ┌───────────┐  ┌────────────┐
    │ Academics  │  │Attendance │  │  Billing   │
    └─────┬──────┘  └─────┬─────┘  └─────┬──────┘
          │               │               │
          └───────────────┼───────────────┘
                          │
                          ▼
                   ┌────────────┐
                   │ Documents  │
                   └────────────┘
                          │
                          ▼
                   ┌────────────┐
                   │ Reporting  │
                   └────────────┘
```

---

## 4. System Data Flow

### 4.1 Request Lifecycle

```
1. User Request
       │
       ▼
2. Route (web.php / api.php)
       │
       ▼
3. Middleware (auth, throttle, cors)
       │
       ▼
4. Controller / Action
       │
       ▼
5. Form Request (validation)
       │
       ▼
6. Service (business logic)
       │
       ▼
7. Model (database interaction)
       │
       ▼
8. Event Dispatch (side effects)
       │
       ▼
9. Listener/Job (async processing)
       │
       ▼
10. Response
```

### 4.2 File Upload Flow

```
1. User uploads file via form
       │
       ▼
2. FormRequest validates (type, size, max)
       │
       ▼
3. Controller passes to UploadService
       │
       ▼
4. File scanned for malware (ClamAV or similar)
       │
       ▼
5. File renamed to safe format
       │
       ▼
6. Uploaded to S3-compatible storage
       │
       ▼
7. Metadata saved to database
       │
       ▼
8. Signed URL generated for download
       │
       ▼
9. Response returned
```

### 4.3 PDF Generation Flow

```
1. Student requests document generation
       │
       ▼
2. Job dispatched to queue
       │
       ▼
3. DocumentService merges template + data
       │
       ▼
4. PDF generated (DomPDF or Puppeteer)
       │
       ▼
5. PDF stored in S3
       │
       ▼
6. User notified of completion
       │
       ▼
7. User downloads via signed URL
```

---

## 5. Database Architecture

### 5.1 Primary Database: PostgreSQL

All core data stored in PostgreSQL with:
- JSONB columns for flexible metadata
- Full-text search using pgvector or native
- Partitioning for large tables (submissions, attendance)
- Logical replication for read replicas

### 5.2 Caching Strategy

| Data Type | Cache Driver | TTL | Invalidation |
|-----------|-------------|-----|--------------|
| User sessions | Redis | Session lifetime | On logout |
| Route caching | Redis | 24 hours | On deployment |
| Query results | Redis | 5 minutes | On data change |
| API responses | Redis | 1 minute | On TTL expiry |
| File metadata | Redis | 1 hour | On file change |

### 5.3 Queue Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Sync      │     │  Database   │     │   Redis     │
│  (default)  │     │   (fallback)│     │  (primary)  │
└─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │
      ▼                   ▼                   ▼
┌─────────────────────────────────────────────────────────┐
│                   Queue Workers                          │
│  - PdfGenerationJob                                      │
│  - NotificationJob                                       │
│  - AttendanceCleanupJob                                  │
│  - PaymentVerificationJob                               │
│  - FileScanJob                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 6. API Architecture

### 6.1 API Style: RESTful

```
/api/v1/
├── auth/
│   ├── login
│   ├── logout
│   ├── register
│   └── password/reset
│
├── users/
│   ├── GET /me
│   ├── PUT /me
│   └── PUT /me/password
│
├── academics/
│   ├── /submissions
│   ├── /courses
│   ├── /enrollments
│   └── /grades
│
├── attendance/
│   ├── /sessions
│   ├── /check-in
│   └── /records
│
├── billing/
│   ├── /invoices
│   ├── /payments
│   └── /subscriptions
│
├── documents/
│   ├── /templates
│   └── /generate
│
└── reports/
    ├── /analytics
    └── /exports
```

### 6.2 Response Format

```json
{
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  },
  "message": "Success",
  "status": 200
}
```

### 6.3 Error Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "details": [
      { "field": "email", "message": "Email is required" }
    ]
  },
  "status": 422
}
```

---

## 7. Security Architecture

### 7.1 Authentication Flow

```
User Login
    │
    ▼
Credentials Validation (Laravel Auth)
    │
    ▼
Generate Token (Sanctum) / Session
    │
    ▼
Return token/session to client
    │
    ▼
Subsequent requests: Token in Header / Cookie
```

### 7.2 Authorization Flow

```
Request Arrives
    │
    ▼
Auth Middleware (Authenticated?)
    │
    ▼
Policy Check (authorized?)
    │
    ▼
Pass / 403 Forbidden
```

### 7.3 File Security

```
Uploaded File
    │
    ▼
Mime Type Validation
    │
    ▼
Virus Scan (if possible)
    │
    ▼
Rename to UUID
    │
    ▼
Store in private S3 bucket
    │
    ▼
Generate signed URL (expires in 1 hour)
```

---

## 8. Scalability Design

### 8.1 Horizontal Scaling

```
                    ┌─────────────┐
                    │ Load Balancer│
                    │  (nginx)    │
                    └──────┬──────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
  ┌───────────┐    ┌───────────┐    ┌───────────┐
  │  App 1    │    │  App 2    │    │  App 3    │
  │ (Laravel) │    │ (Laravel) │    │ (Laravel) │
  └─────┬─────┘    └─────┬─────┘    └─────┬─────┘
        │                │                │
        └────────────────┼────────────────┘
                         │
                         ▼
                  ┌───────────┐
                  │PostgreSQL │
                  │Primary DB │
                  └───────────┘
                         │
                         ▼
                  ┌───────────┐
                  │PostgreSQL │
                  │Read Replica│
                  └───────────┘
```

### 8.2 Read/Write Splitting

- Writes go to primary database
- Reads (reports, dashboards) go to read replica
- Eloquent automatically routes based on query type

### 8.3 Caching Scale

- Application-level cache with Redis
- CDN for static assets (fonts, images)
- Database query result caching for expensive operations

---

## 9. Monitoring and Observability

### 9.1 Application Monitoring

| Tool | Purpose |
|------|---------|
| Laravel Telescope | Development debugging |
| Sentry | Production error tracking |
| Laravel Log | Application logging |
| Prometheus + Grafana | Metrics and dashboards |

### 9.2 Health Checks

```
/health
├── /health/database
├── /health/cache
├── /health/queue
└── /health/storage
```

---

## 10. Deployment Architecture

### 10.1 Production Setup

```
┌─────────────────────────────────────────────────────────────┐
│                      Cloud Infrastructure                   │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │  Web Server  │  │  Web Server  │  │  Web Server  │    │
│  │   (nginx)    │  │   (nginx)    │  │   (nginx)    │    │
│  └──────────────┘  └──────────────┘  └──────────────┘    │
│         │                 │                 │              │
│         └─────────────────┼─────────────────┘              │
│                           ▼                                │
│                   ┌──────────────┐                        │
│                   │ Load Balancer│                        │
│                   └──────────────┘                        │
│                           │                                │
│                           ▼                                │
│  ┌─────────────────────────────────────────────────────┐  │
│  │              Laravel Application Cluster            │  │
│  │                                                      │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐│  │
│  │  │   Worker 1  │  │   Worker 2  │  │   Worker 3  ││  │
│  │  │  (Queue)    │  │  (Queue)    │  │  (Queue)    ││  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘│  │
│  └─────────────────────────────────────────────────────┘  │
│                           │                                │
└───────────────────────────┼────────────────────────────────┘
                            ▼
              ┌─────────────────────────────┐
              │     PostgreSQL Cluster       │
              │   (Primary + Replica)        │
              └─────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────────┐
              │         Redis Cluster        │
              │   (Cache + Queue)            │
              └─────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────────┐
              │    S3-Compatible Storage     │
              │      (Files)                 │
              └─────────────────────────────┘
```

### 10.2 Environment Configuration

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://uniacademic.example.com

DB_CONNECTION=pgsql
DB_HOST=primary.db.uniacademic.internal
DB_PORT=5432

REDIS_HOST=redis.uniacademic.internal
REDIS_PASSWORD=***

QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=***
AWS_SECRET_ACCESS_KEY=***
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=uniacademic-files
```

---

*Document Version: 1.0*
*Last Updated: 2026-04-14*