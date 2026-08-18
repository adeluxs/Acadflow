# AcadFlow Queue, Scheduler & Redis Operations Guide
## Local Development and Production Shared Hosting (cPanel)

**Project:** AcadFlow  
**Recommended production queue backend:** Redis

> The current `.env.example` keeps `QUEUE_CONNECTION=database` as a safe bootstrap/local default. This operations guide documents the recommended Redis production configuration where the host provides Redis.  
**Applies to:** Current AcadFlow Laravel source  
**Document purpose:** Configure and operate AcadFlow queues and scheduler locally and on production shared hosting without using the database as the active queue backend.

---

# 1. Overview

AcadFlow uses Laravel queues for background work that should not block normal web requests. In the current AcadFlow source, the explicitly used queue names are:

- `default`
- `ai`
- `indexing`
- `analytics`

Examples of work placed on these queues include:

- Scheduled Knowledge Hub publication
- AI moderation and research validation
- Search/content indexing
- Creator/reputation recalculation
- Other jobs that use the application's default queue

AcadFlow also uses Laravel's scheduler for recurring application tasks. The scheduler is separate from the queue worker. **Both must be running**.

For this deployment design:

```text
Web Request
    |
    v
Laravel / AcadFlow
    |
    +----------------------+
    |                      |
    v                      v
Redis Queue            Laravel Scheduler
    |                      |
    v                      v
Queue Worker           Dispatch due work
    |
    v
default / ai / indexing / analytics
```

The active queue backend will be Redis:

```env
QUEUE_CONNECTION=redis
```

The database may still be used for `failed_jobs` and `job_batches`. That does **not** mean the application is using the database as its live queue. Redis remains the active queue backend.

---

# 2. Current AcadFlow Queue Configuration

The current AcadFlow source already contains a Redis queue connection in `config/queue.php`, so you do not need to invent another queue architecture.

The relevant environment variables are:

```env
QUEUE_CONNECTION=redis

REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=360

QUEUE_FAILED_DRIVER=database-uuids
```

AcadFlow's Redis connection is configured through `config/database.php` using variables such as:

```env
REDIS_CLIENT=phpredis
REDIS_URL=
REDIS_HOST=127.0.0.1
REDIS_USERNAME=null
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

For Redis-backed queues, Laravel requires either:

1. The **PhpRedis PHP extension**, or
2. The Composer package **Predis**

PhpRedis is preferred when the server supports it. Predis is a practical fallback on shared hosting where you cannot install PHP extensions yourself.

---

# 3. Important Redis Database Separation

Use separate logical Redis databases for queue data and cache data when your Redis provider supports numbered databases.

Recommended:

```env
REDIS_DB=0
REDIS_CACHE_DB=1
```

Conceptually:

```text
Redis DB 0 -> AcadFlow queue/general Redis connection
Redis DB 1 -> AcadFlow cache
```

Do not run `FLUSHALL` or `FLUSHDB` casually on production Redis because it can remove queued jobs or application cache data.

---

# 4. Recommended Queue Timing Values

AcadFlow contains AI and indexing tasks that can take longer than ordinary jobs.

Recommended:

```env
REDIS_QUEUE_RETRY_AFTER=360
```

On Linux production, use a worker timeout shorter than `retry_after`:

```text
retry_after = 360 seconds
worker timeout = 300 seconds
```

This provides a safety gap so a frozen job is terminated before Redis makes it available for retry.

**Important:** Laravel job timeouts require the PHP PCNTL extension. Windows PHP normally does not provide PCNTL, so the `--timeout` option should not be relied upon when running PHP directly on Windows.

---

# 5. LOCAL DEVELOPMENT — WINDOWS

AcadFlow is commonly developed on Windows. Redis must be available before you switch:

```env
QUEUE_CONNECTION=redis
```

The cleanest local approaches are:

- Docker Desktop
- WSL2/Linux
- A locally provided Redis service
- A remote development Redis instance

Docker is a straightforward option.

---

## 5.1 Start Redis Locally With Docker

If Docker Desktop is installed:

```bash
docker run -d --name acadflow-redis -p 6379:6379 redis:7-alpine redis-server --appendonly yes
```

Check that it is running:

```bash
docker ps
```

Test Redis:

```bash
docker exec -it acadflow-redis redis-cli ping
```

Expected response:

```text
PONG
```

To stop Redis:

```bash
docker stop acadflow-redis
```

To start it again later:

```bash
docker start acadflow-redis
```

To view Redis logs:

```bash
docker logs acadflow-redis
```

---

# 6. LOCAL PHP REDIS CLIENT

## Option A — PhpRedis

Check whether PHP has the Redis extension:

### Windows Command Prompt

```bash
php -m | findstr redis
```

### PowerShell

```powershell
php -m | Select-String redis
```

If you see:

```text
redis
```

you can use:

```env
REDIS_CLIENT=phpredis
```

---

## Option B — Predis

If the Redis PHP extension is unavailable, install Predis:

```bash
composer require predis/predis
```

Then use:

```env
REDIS_CLIENT=predis
```

Predis communicates with Redis in PHP and does not require the PhpRedis extension.

---

# 7. LOCAL `.env` CONFIGURATION

For local Windows development with Redis running on port `6379`:

```env
APP_ENV=local
APP_DEBUG=true

# ---------------------------------------------------------
# CACHE
# ---------------------------------------------------------
CACHE_STORE=redis

# ---------------------------------------------------------
# REDIS
# ---------------------------------------------------------
REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_PREFIX=acadflow_local_

REDIS_URL=
REDIS_HOST=127.0.0.1
REDIS_USERNAME=null
REDIS_PASSWORD=null
REDIS_PORT=6379

REDIS_DB=0
REDIS_CACHE_DB=1

REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_LOCK_CONNECTION=default

# ---------------------------------------------------------
# QUEUE
# ---------------------------------------------------------
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids

REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=360

# AcadFlow AI should use the same Redis queue connection.
AI_QUEUE_CONNECTION=redis
```

If you installed Predis instead:

```env
REDIS_CLIENT=predis
```

After changing `.env`, always clear configuration caches:

```bash
php artisan optimize:clear
```

---

# 8. LOCAL DATABASE PREPARATION

Even though Redis stores active queued jobs, AcadFlow should still have its Laravel queue support tables because failed jobs and batches may be recorded in the database.

Run:

```bash
php artisan migrate
```

Do **not** create duplicate queue migrations if the project's migrations already contain:

- `jobs`
- `job_batches`
- `failed_jobs`

The current AcadFlow source already has queue infrastructure; migration status should be checked before generating anything new.

Check migration status:

```bash
php artisan migrate:status
```

---

# 9. VERIFY LOCAL REDIS FROM LARAVEL

Start Tinker:

```bash
php artisan tinker
```

Then run:

```php
Illuminate\Support\Facades\Redis::connection()->ping();
```

A successful connection should return a response equivalent to:

```text
PONG
```

You can also test a temporary key:

```php
Illuminate\Support\Facades\Redis::set('acadflow:test', 'working');
Illuminate\Support\Facades\Redis::get('acadflow:test');
```

Expected value:

```text
working
```

Remove it afterward:

```php
Illuminate\Support\Facades\Redis::del('acadflow:test');
```

Exit Tinker:

```php
exit
```

---

# 10. RUN ACADFLOW LOCALLY

AcadFlow needs three processes during normal local development:

## Terminal 1 — Laravel Application

```bash
php artisan serve
```

## Terminal 2 — Redis Queue Worker

On Windows PHP:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --sleep=2 --tries=3 -v
```

Because native Windows PHP normally does not provide PCNTL, do not depend on `--timeout` locally on Windows.

If you are running AcadFlow inside WSL/Linux and PCNTL is installed:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --sleep=2 --tries=3 --timeout=300 -v
```

## Terminal 3 — Laravel Scheduler

```bash
php artisan schedule:work
```

The scheduler is needed even though the queue worker is already running.

The responsibilities are different:

```text
queue:work
    Processes jobs already waiting in Redis.

schedule:work
    Checks AcadFlow's schedule and dispatches/runs work when it becomes due.
```

---

# 11. ACADFLOW SCHEDULED TASKS

The current AcadFlow scheduler includes recurring work such as:

- Publishing scheduled Knowledge Hub publications
- Research meeting reminders
- Creator reputation recalculation
- Academic event reminders
- Academic event/challenge lifecycle advancement
- Failed queue-job pruning

Therefore, running only:

```bash
php artisan queue:work
```

is not sufficient for the complete application.

You also need:

```bash
php artisan schedule:work
```

locally or `schedule:run` from cron in production.

---

# 12. CHECK QUEUE STATUS LOCALLY

Show failed jobs:

```bash
php artisan queue:failed
```

Retry all failed jobs:

```bash
php artisan queue:retry all
```

Retry a specific failed job:

```bash
php artisan queue:retry JOB_ID
```

Remove a specific failed job:

```bash
php artisan queue:forget JOB_ID
```

Delete all failed-job records:

```bash
php artisan queue:flush
```

Process one job and exit:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --once -v
```

Restart long-running queue workers after source-code changes:

```bash
php artisan queue:restart
```

For local development, stopping and restarting the worker terminal manually also reloads the newest application code.

---

# 13. LOCAL DEVELOPMENT STARTUP CHECKLIST

Every time you start development:

```text
1. Start MySQL
2. Start Redis
3. Start AcadFlow
4. Start Redis queue worker
5. Start Laravel scheduler
```

Example:

```bash
docker start acadflow-redis
php artisan optimize:clear
php artisan serve
```

Second terminal:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --sleep=2 --tries=3 -v
```

Third terminal:

```bash
php artisan schedule:work
```

---

# 14. PRODUCTION — SHARED HOSTING / cPanel

Shared hosting is different from a VPS.

On normal cPanel hosting you usually cannot:

- Install the Redis server yourself
- Install Supervisor globally
- Keep arbitrary background daemons permanently running
- Modify system services

Therefore, before configuring AcadFlow for Redis, confirm that the hosting provider gives you **Redis access**.

The provider may supply:

- A Redis hostname
- A port
- A username
- A password
- A TLS Redis URL
- A local Unix socket
- A cPanel Redis service

If the hosting company provides no Redis service and does not allow access to an external Redis provider, Redis queues cannot work on that hosting account.

---

# 15. PRODUCTION REDIS REQUIREMENTS

Before deployment, confirm all of the following:

```text
[ ] Redis service is available.
[ ] Redis host or URL is known.
[ ] Redis port is known.
[ ] Redis authentication details are known.
[ ] Remote Redis connections are permitted.
[ ] PhpRedis is enabled OR Predis is installed.
[ ] Cron Jobs are available in cPanel.
[ ] PHP CLI is available.
[ ] The correct CLI PHP version is selected.
[ ] storage/ is writable.
[ ] bootstrap/cache/ is writable.
```

---

# 16. SHARED HOSTING: CHECK PHP REDIS SUPPORT

From cPanel Terminal:

```bash
php -m | grep -i redis
```

If `redis` appears, use:

```env
REDIS_CLIENT=phpredis
```

If not, use Predis.

Install it in the project:

```bash
composer require predis/predis
```

Then:

```env
REDIS_CLIENT=predis
```

If Composer is unavailable on the shared host, install Predis locally, commit/update:

```text
composer.json
composer.lock
```

and deploy dependencies using the hosting workflow you normally use.

Do not manually copy random Redis PHP files into the project.

---

# 17. PRODUCTION `.env` — REDIS QUEUE

Use the credentials provided by your Redis service.

Example using host/port credentials:

```env
APP_ENV=production
APP_DEBUG=false

# ---------------------------------------------------------
# CACHE
# ---------------------------------------------------------
CACHE_STORE=redis

# ---------------------------------------------------------
# REDIS
# ---------------------------------------------------------
REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_PREFIX=acadflow_prod_

REDIS_URL=
REDIS_HOST=YOUR_REDIS_HOST
REDIS_USERNAME=YOUR_REDIS_USERNAME
REDIS_PASSWORD=YOUR_STRONG_REDIS_PASSWORD
REDIS_PORT=6379

REDIS_DB=0
REDIS_CACHE_DB=1

REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_LOCK_CONNECTION=default

# ---------------------------------------------------------
# QUEUE
# ---------------------------------------------------------
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids

REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=360

AI_QUEUE_CONNECTION=redis
```

If the Redis provider gives you one connection URL, use the provider's URL instead:

```env
REDIS_URL=redis://USERNAME:PASSWORD@HOST:PORT
```

or, when the service requires TLS:

```env
REDIS_URL=rediss://USERNAME:PASSWORD@HOST:PORT
```

When using a provider URL, follow that provider's exact connection details.

Never publish your production Redis URL or password.

---

# 18. IMPORTANT SHARED-HOSTING SECURITY RULES

For production Redis:

1. Do not expose Redis publicly without authentication.
2. Use TLS when the Redis provider requires/supports it.
3. Restrict Redis access to the hosting server where possible.
4. Use a strong Redis password.
5. Never commit `.env`.
6. Never put Redis passwords into JavaScript or mobile code.
7. The Laravel backend is the only component that should need direct Redis credentials.
8. Do not use production Redis credentials on a public development machine.
9. Keep queue Redis and cache logically separated where supported.
10. Do not run `redis-cli FLUSHALL` in production.

---

# 19. FIND THE CORRECT PHP PATH IN cPanel

In cPanel Terminal:

```bash
which php
```

Possible result:

```text
/usr/local/bin/php
```

Some cPanel servers use version-specific binaries such as:

```text
/opt/cpanel/ea-php84/root/usr/bin/php
```

Confirm the version:

```bash
/usr/local/bin/php -v
```

or:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php -v
```

Use the same PHP major/minor version that AcadFlow uses on the website.

---

# 20. FIND THE ACADFLOW PRODUCTION PATH

In cPanel Terminal, enter the project folder:

```bash
cd /home/CPANEL_USERNAME/path/to/acadflow
```

Then:

```bash
pwd
```

Example:

```text
/home/example/domains/example.com/public_html
```

Use the actual path returned by your server in all cron commands.

---

# 21. PRODUCTION DEPLOYMENT COMMANDS

From the AcadFlow application root:

```bash
composer install --no-dev --optimize-autoloader
```

Then:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

If `storage:link` has already been created, Laravel may tell you that the link already exists. That is normally fine.

Ensure these directories are writable:

```text
storage/
bootstrap/cache/
```

---

# 22. TEST PRODUCTION REDIS BEFORE STARTING QUEUES

Run:

```bash
php artisan tinker
```

Then:

```php
Illuminate\Support\Facades\Redis::connection()->ping();
```

You should receive a successful Redis response.

Test a key:

```php
Illuminate\Support\Facades\Redis::set('acadflow:production:test', 'ok');
Illuminate\Support\Facades\Redis::get('acadflow:production:test');
```

Then delete it:

```php
Illuminate\Support\Facades\Redis::del('acadflow:production:test');
```

If this fails, do **not** start the queue cron yet. Correct Redis connectivity first.

---

# 23. PRODUCTION SHARED HOSTING — SCHEDULER CRON

AcadFlow's scheduler should be called **every minute**.

In cPanel:

```text
Advanced -> Cron Jobs
```

Set:

```text
Minute: *
Hour: *
Day: *
Month: *
Weekday: *
```

Command:

```bash
cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Equivalent cron expression:

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Replace:

```text
/home/CPANEL_USERNAME/path/to/acadflow
```

and:

```text
/usr/local/bin/php
```

with your actual server paths.

---

# 24. PRODUCTION SHARED HOSTING — REDIS QUEUE CRON

Ordinary shared hosting generally does not have Supervisor for your account.

Therefore, use a short-lived queue worker from cron:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --timeout=300
```

Recommended cPanel cron:

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --timeout=300 >> /dev/null 2>&1
```

This means:

```text
Every minute:
    Start an AcadFlow Redis worker.
    Process waiting jobs.
    Exit when Redis has no more matching jobs.
```

This is more appropriate for ordinary cPanel than attempting to keep a permanent `queue:work` process alive without a process manager.

---

# 25. OPTIONAL: PREVENT OVERLAPPING CRON WORKERS

Some shared hosts provide the `flock` utility.

Check:

```bash
which flock
```

If available, you may prevent a second cron worker from starting while the previous one is still running.

Example:

```cron
* * * * * flock -n /tmp/acadflow-queue.lock /bin/bash -lc 'cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --timeout=300' >> /dev/null 2>&1
```

If `flock` is unavailable, use the normal Redis queue cron.

Redis itself reserves jobs while they are being processed. The important requirement is that:

```text
worker timeout < REDIS_QUEUE_RETRY_AFTER
```

Recommended:

```env
REDIS_QUEUE_RETRY_AFTER=360
```

with:

```text
--timeout=300
```

---

# 26. SHARED HOSTING WITH STRICT PROCESS LIMITS

Some shared hosting providers terminate CLI processes after 30–60 seconds.

If your hosting provider has a strict process limit, use a shorter worker lifecycle:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --max-time=50
```

Cron:

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --max-time=50 >> /dev/null 2>&1
```

However, if AcadFlow AI jobs themselves regularly take longer than your host permits a PHP CLI process to run, ordinary shared hosting may not be suitable for those background jobs.

In that situation you need a hosting plan that allows longer worker processes or a server with a proper process manager.

---

# 27. OPTIONAL: SPLIT AI FROM OTHER QUEUES

AcadFlow's `ai` queue can contain slower jobs than `default`, `indexing`, or `analytics`.

On a shared host with enough process allowance, you may use two cron workers.

## General Worker

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=default,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --timeout=120 >> /dev/null 2>&1
```

## AI Worker

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=ai --stop-when-empty --sleep=1 --tries=3 --timeout=300 >> /dev/null 2>&1
```

Keep:

```env
REDIS_QUEUE_RETRY_AFTER=360
```

This approach prevents a large number of ordinary jobs from delaying AI work.

Do this only if the hosting provider permits the extra processes.

For a small installation, the single worker is simpler.

---

# 28. PRODUCTION CRON SUMMARY

For a normal shared-hosting AcadFlow installation, the minimum recommended cron configuration is:

## Scheduler

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Redis Queue Worker

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --timeout=300 >> /dev/null 2>&1
```

That is the main production setup for AcadFlow on shared hosting with Redis.

---

# 29. WHY `--STOP-WHEN-EMPTY` IS USED ON SHARED HOSTING

Without:

```text
--stop-when-empty
```

Laravel's worker is designed to remain alive waiting for future jobs.

That is ideal when Supervisor or another process manager owns the worker.

It is usually not ideal for ordinary cPanel cron.

With:

```text
--stop-when-empty
```

the worker:

1. Starts.
2. Connects to Redis.
3. Processes available AcadFlow jobs.
4. Exits when the selected queues are empty.
5. Starts again on the next cron invocation.

---

# 30. DO NOT USE `QUEUE:LISTEN` IN PRODUCTION

Do not use:

```bash
php artisan queue:listen
```

for production AcadFlow.

Use:

```bash
php artisan queue:work redis
```

`queue:work` is more efficient.

---

# 31. DO NOT USE `SCHEDULE:WORK` FROM cPanel CRON

Locally:

```bash
php artisan schedule:work
```

is convenient.

On shared hosting production, use:

```bash
php artisan schedule:run
```

from a once-per-minute cron.

Do not create a cron job that launches another permanently running `schedule:work` process every minute.

---

# 32. PRODUCTION CACHE AND QUEUE RESTARTS

A permanent queue worker keeps the Laravel application in memory, so code changes normally require:

```bash
php artisan queue:restart
```

With the recommended shared-hosting setup using:

```text
--stop-when-empty
```

workers exit naturally, so new cron workers load the newest source.

After every deployment, still run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

If any long-running worker exists, also run:

```bash
php artisan queue:restart
```

---

# 33. PRODUCTION DEPLOYMENT CHECKLIST

## Before Upload

```text
[ ] APP_DEBUG will be false in production.
[ ] Production Redis credentials are available.
[ ] Redis client choice is known: phpredis or predis.
[ ] composer.lock is included.
[ ] Production database backup exists.
```

## After Upload

```text
[ ] Correct production .env is present.
[ ] QUEUE_CONNECTION=redis.
[ ] CACHE_STORE=redis.
[ ] Redis credentials are correct.
[ ] composer install --no-dev --optimize-autoloader completed.
[ ] php artisan optimize:clear completed.
[ ] php artisan migrate --force completed.
[ ] php artisan optimize completed.
[ ] Redis ping succeeds from Laravel.
[ ] Scheduler cron is installed.
[ ] Redis worker cron is installed.
[ ] A queued job is processed successfully.
[ ] php artisan queue:failed is checked.
```

---

# 34. VERIFY THAT THE QUEUE REALLY USES REDIS

Run:

```bash
php artisan tinker
```

Then:

```php
config('queue.default');
```

Expected:

```text
redis
```

Check the Redis queue connection:

```php
config('queue.connections.redis.driver');
```

Expected:

```text
redis
```

Check retry time:

```php
config('queue.connections.redis.retry_after');
```

Expected with this guide:

```text
360
```

Exit:

```php
exit
```

If Laravel still reports old values after editing `.env`, run:

```bash
php artisan optimize:clear
```

and check again.

---

# 35. CHECK CONFIGURATION WITHOUT TINKER

Useful commands:

```bash
php artisan about
```

and:

```bash
php artisan config:show queue
```

If supported by the installed Laravel version, this helps verify that Redis is the default queue connection.

---

# 36. FAILED JOB MANAGEMENT

List failed jobs:

```bash
php artisan queue:failed
```

Retry all:

```bash
php artisan queue:retry all
```

Retry one:

```bash
php artisan queue:retry JOB_ID
```

Forget one:

```bash
php artisan queue:forget JOB_ID
```

Flush failed-job records:

```bash
php artisan queue:flush
```

AcadFlow's scheduler already includes pruning of old failed queue-job records.

---

# 37. TROUBLESHOOTING — `Class "Redis" not found`

Cause:

```env
REDIS_CLIENT=phpredis
```

but the PhpRedis extension is not enabled.

Solution A:

Enable PhpRedis through the hosting provider/cPanel PHP extension manager.

Solution B:

```bash
composer require predis/predis
```

and change:

```env
REDIS_CLIENT=predis
```

Then:

```bash
php artisan optimize:clear
```

---

# 38. TROUBLESHOOTING — `Connection refused`

Typical causes:

- Redis service is not running.
- Wrong `REDIS_HOST`.
- Wrong `REDIS_PORT`.
- Firewall blocks the connection.
- Shared host does not allow remote Redis.
- Redis requires TLS but a normal TCP URL is being used.
- Redis is bound only to localhost on another machine.

Test the exact provider connection details.

Do not fall back silently to `sync` in production merely to hide Redis configuration problems.

---

# 39. TROUBLESHOOTING — Authentication Error

Check:

```env
REDIS_USERNAME=
REDIS_PASSWORD=
```

Some providers use only a password.

Some use both username and password.

Some provide a full `REDIS_URL`.

Use the exact credentials provided by the Redis service.

After editing:

```bash
php artisan optimize:clear
```

---

# 40. TROUBLESHOOTING — Jobs Stay in Redis

Confirm a worker is running.

Local:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics -v
```

Production shared hosting:

Check cPanel Cron Jobs.

Temporarily remove:

```text
>> /dev/null 2>&1
```

from the cron command so errors are emailed/logged by cPanel.

You can also run the command manually from Terminal:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty -v
```

---

# 41. TROUBLESHOOTING — Jobs Go to the Wrong Connection

Check:

```env
QUEUE_CONNECTION=redis
AI_QUEUE_CONNECTION=redis
```

Then:

```bash
php artisan optimize:clear
```

Verify:

```bash
php artisan tinker
```

```php
config('queue.default');
config('ai.queue_connection');
```

---

# 42. TROUBLESHOOTING — Jobs Are Processed Twice

Check the relationship between:

```env
REDIS_QUEUE_RETRY_AFTER=360
```

and the worker:

```text
--timeout=300
```

The timeout should be shorter than `retry_after`.

Also check:

- The job itself does not define a longer `$timeout`.
- External HTTP calls use sensible request timeouts.
- The host is not force-killing CLI processes unexpectedly.
- Redis connectivity is stable.

---

# 43. TROUBLESHOOTING — Queue Worker Uses Old Code

Run:

```bash
php artisan queue:restart
```

For shared hosting with `--stop-when-empty`, wait for the current worker to exit or terminate it through the host if necessary. The next cron invocation will load new code.

Also run:

```bash
php artisan optimize:clear
php artisan optimize
```

after deployments.

---

# 44. TROUBLESHOOTING — CRON WORKS MANUALLY BUT NOT AUTOMATICALLY

Cron often has a different environment from an interactive shell.

Use absolute paths.

Bad:

```cron
* * * * * php artisan schedule:run
```

Better:

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Check:

```bash
which php
pwd
```

and use those exact paths.

---

# 45. TROUBLESHOOTING — cPanel Uses THE WRONG PHP VERSION

Your website may use PHP 8.x while cron's plain `php` command points to another PHP version.

Check:

```bash
php -v
```

If necessary use the version-specific cPanel PHP executable, for example:

```text
/opt/cpanel/ea-php84/root/usr/bin/php
```

Use the path available on your own host.

---

# 46. REDIS QUEUE VS DATABASE QUEUE

With this setup:

```env
QUEUE_CONNECTION=redis
```

new queued jobs are stored in Redis.

AcadFlow is **not** using the `jobs` database table as its active queue.

The database can still contain:

```text
failed_jobs
job_batches
```

because these are persistent operational records.

That is expected.

---

# 47. SHOULD WE DELETE THE `jobs` TABLE?

No.

Do not delete existing queue tables simply because the active queue is Redis.

Reasons:

- Existing migrations may expect them.
- `job_batches` is still useful.
- `failed_jobs` is still useful.
- Future fallback/recovery may require them.
- Removing them gives little benefit.

Leave the existing queue migrations intact.

---

# 48. SHOULD ACADFLOW USE REDIS FOR CACHE TOO?

Recommended for production when Redis is reliable:

```env
CACHE_STORE=redis
```

AcadFlow's feature/module management caching and Laravel cache operations benefit from Redis.

Keep:

```env
REDIS_DB=0
REDIS_CACHE_DB=1
```

where numbered Redis databases are supported.

If your managed Redis service does not support multiple numbered databases, use the provider's supported separation strategy.

---

# 49. SHOULD ACADFLOW USE HORIZON ON SHARED HOSTING?

Not by default.

Laravel Horizon is excellent for monitoring Redis queues, but Horizon itself is a long-running process that should be supervised.

Ordinary cPanel shared hosting usually does not provide a reliable process manager for Horizon.

Therefore:

```text
Shared Hosting:
Redis + queue:work cron + schedule:run cron
```

is the recommended baseline.

If your host specifically supports persistent workers/process supervision, Horizon can be evaluated later.

Do not add Horizon simply for the sake of having another dashboard.

---

# 50. RECOMMENDED FINAL LOCAL CONFIGURATION

```env
APP_ENV=local
APP_DEBUG=true

CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_PREFIX=acadflow_local_
REDIS_URL=
REDIS_HOST=127.0.0.1
REDIS_USERNAME=null
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=360

AI_QUEUE_CONNECTION=redis
```

Run:

```bash
php artisan optimize:clear
php artisan migrate
```

Then:

```bash
php artisan serve
```

Second terminal:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --sleep=2 --tries=3 -v
```

Third terminal:

```bash
php artisan schedule:work
```

---

# 51. RECOMMENDED FINAL PRODUCTION SHARED-HOSTING CONFIGURATION

```env
APP_ENV=production
APP_DEBUG=false

CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_PREFIX=acadflow_prod_

REDIS_URL=
REDIS_HOST=YOUR_REDIS_HOST
REDIS_USERNAME=YOUR_REDIS_USERNAME
REDIS_PASSWORD=YOUR_REDIS_PASSWORD
REDIS_PORT=6379

REDIS_DB=0
REDIS_CACHE_DB=1

QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids

REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=360

AI_QUEUE_CONNECTION=redis
```

If PhpRedis is unavailable:

```env
REDIS_CLIENT=predis
```

after installing:

```bash
composer require predis/predis
```

Deployment:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

Cron 1:

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Cron 2:

```cron
* * * * * cd /home/CPANEL_USERNAME/path/to/acadflow && /usr/local/bin/php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty --sleep=1 --tries=3 --timeout=300 >> /dev/null 2>&1
```

---

# 52. FINAL PRODUCTION TEST

After setup:

```bash
php artisan optimize:clear
php artisan tinker
```

Check:

```php
config('queue.default');
Illuminate\Support\Facades\Redis::connection()->ping();
```

Expected queue:

```text
redis
```

Then exit and manually process a queue cycle:

```bash
php artisan queue:work redis --queue=default,ai,indexing,analytics --stop-when-empty -v
```

Check failed jobs:

```bash
php artisan queue:failed
```

Check the scheduler:

```bash
php artisan schedule:list
```

Run it manually once:

```bash
php artisan schedule:run
```

If all commands work, enable the two cPanel cron jobs.

---

# 53. FINAL ARCHITECTURE

## Local

```text
Windows
 |
 +-- MySQL
 +-- Redis (Docker / WSL / local service)
 +-- php artisan serve
 +-- php artisan queue:work redis
 +-- php artisan schedule:work
```

## Production Shared Hosting

```text
Browser / Mobile App
        |
        v
     AcadFlow
        |
        +--------------------+
        |                    |
        v                    v
   Redis Queue          cPanel Cron
        |                    |
        v                    +--> schedule:run every minute
 queue:work cron
 every minute
        |
        +--> default
        +--> ai
        +--> indexing
        +--> analytics
```

---

# 54. Core Commands Cheat Sheet

## Local

```bash
php artisan optimize:clear
php artisan migrate
php artisan serve
php artisan queue:work redis --queue=default,ai,indexing,analytics --sleep=2 --tries=3 -v
php artisan schedule:work
```

## Production Deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

## Queue Operations

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:retry JOB_ID
php artisan queue:forget JOB_ID
php artisan queue:flush
php artisan queue:restart
```

## Scheduler

```bash
php artisan schedule:list
php artisan schedule:run
```

## Redis Test

```bash
php artisan tinker
```

```php
Illuminate\Support\Facades\Redis::connection()->ping();
```

---

# 55. Final Recommendations

For AcadFlow:

1. Use **Redis** as the active queue backend in both local and production environments.
2. Use **Redis cache** when the production Redis service is stable.
3. Keep failed-job records in MySQL using `database-uuids`.
4. Keep the existing `jobs`, `job_batches`, and `failed_jobs` migrations.
5. Run all four current AcadFlow queues:
   - `default`
   - `ai`
   - `indexing`
   - `analytics`
6. Run the Laravel scheduler in addition to the queue worker.
7. On Windows development, use Docker/WSL/local Redis and run workers from a separate terminal.
8. On normal cPanel shared hosting, use short-lived Redis workers from Cron with `--stop-when-empty`.
9. Use `schedule:run` every minute in production.
10. Do not expose Redis credentials to the frontend or mobile application.
11. Do not enable production Redis until Laravel can successfully ping it.
12. Keep `APP_DEBUG=false` in production.
13. Use a `REDIS_QUEUE_RETRY_AFTER` value longer than the Linux worker timeout.
14. If shared-hosting process limits prevent AcadFlow AI jobs from completing, move the queue worker to hosting that supports persistent background processes rather than weakening the queue architecture.

---

# 56. References

This guide follows the queue, Redis, scheduler, worker deployment, timeout, and production practices documented by the official Laravel documentation for the current Laravel generation.

Relevant official Laravel documentation topics:

- Queues
- Redis
- Task Scheduling
- Deployment
- Cache
- Horizon

