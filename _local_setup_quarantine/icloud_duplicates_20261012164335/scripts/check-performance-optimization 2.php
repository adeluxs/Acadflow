<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$pass = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL: {$message}\n";
};
$read = static fn (string $path): string => (string) file_get_contents($root.'/'.$path);

$provider = $read('app/Providers/AppServiceProvider.php');
$pass(! str_contains($provider, "View::composer('*'"), 'No wildcard view composer runs on every Blade partial');
$pass(str_contains($provider, "View::composer('layouts.app'"), 'Main layout resolves shared settings/feature data once');
$pass(str_contains($provider, 'JobProcessed::class'), 'Long-lived workers clear request-local runtime caches between jobs');

$settings = $read('app/Services/SettingService.php');
$pass(str_contains($settings, 'public static function getMany('), 'Settings service has batched request/cache/database retrieval');
$pass(str_contains($settings, "whereIn('key', array_keys(\$canonicalValues))"), 'Bulk setting updates prefetch existing rows in one query');
$pass(substr_count($settings, 'return self::getMany([') >= 6, 'Grouped settings use batched retrieval');

$dashboard = $read('app/Http/Controllers/DashboardController.php');
$pass(! str_contains($dashboard, '$pendingInvoices'), 'Student dashboard no longer queries unused invoices');
$pass(! str_contains($dashboard, "Group::query()"), 'Student dashboard no longer queries unused groups');
$pass(str_contains($dashboard, 'coursePerformanceSummary($courseIds)'), 'Lecturer performance uses grouped SQL aggregation');

$analytics = $read('app/Services/AnalyticsService.php');
$pass(substr_count($analytics, "SUM(CASE WHEN") >= 10, 'Analytics card counts use conditional aggregates');
$pass(str_contains($analytics, "groupBy('submissions.course_id')"), 'Course grade averages are grouped instead of queried per course');

$aiAnalytics = $read('app/Ai/AiAnalytics.php');
$pass(str_contains($aiAnalytics, 'AVG(processing_time) AS avg_processing_time'), 'AI analytics scalar metrics use one aggregate query');
$pass(substr_count($aiAnalytics, 'selectRaw(') >= 10, 'AI analytics keeps aggregate metrics in SQL');

$apiAttendance = $read('app/Http/Controllers/Api/AttendanceController.php');
$apiSubmission = $read('app/Http/Controllers/Api/SubmissionController.php');
$pass(str_contains($apiAttendance, "SUM(CASE WHEN status = 'present'"), 'Attendance API report uses one conditional aggregate');
$pass(str_contains($apiSubmission, "SUM(CASE WHEN status IN ('submitted', 'resubmitted', 'under_review')"), 'Submission API analytics uses one conditional aggregate');

$admin = $read('app/Http/Controllers/AdminController.php');
$pass(str_contains($admin, 'lazyByIdDesc(500)'), 'Admin CSV exports stream in bounded chunks');
$pass(substr_count($admin, 'selectRaw("SUM(CASE WHEN') >= 4, 'Admin report cards use conditional aggregates');

$exports = $read('app/Http/Controllers/ExportController.php');
$pass(substr_count($exports, 'lazyById(100)') >= 2, 'Batch PDF/ZIP exports iterate records lazily');
$pass(substr_count($exports, 'deleteFileAfterSend(true)') >= 2, 'Temporary batch export ZIPs are removed after delivery');

$billing = $read('app/Http/Controllers/BillingController.php').$read('app/Services/PaymentService.php');
$pass(substr_count($billing, 'chunkById(250') >= 2, 'Invoice generation uses bounded chunks');

$schedule = $read('routes/console.php');
$pass(substr_count($schedule, 'chunkById(250') >= 2, 'Large scheduled dispatch scans use chunking');
$pass(! str_contains($schedule, "->pluck('id')->each"), 'Scheduled dispatches do not materialize all IDs');

$notifications = $read('app/Http/Controllers/Admin/NotificationManagementController.php');
$pass(str_contains($notifications, 'chunkById(250'), 'System announcements are queued in bounded recipient batches');
$pass(str_contains($notifications, 'chunkById(100'), 'Failed notification retries are processed in chunks');

$notificationService = $read('app/Services/NotificationService.php');
$pass(str_contains($notificationService, 'shouldSendEmail(NotificationType $type, ?NotificationSetting $settings)'), 'Notification settings are reused instead of queried twice per recipient');

$reputation = $read('app/Services/Reputation/ReputationService.php');
$pass(str_contains($reputation, "COALESCE(SUM(bookmark_count), 0) AS bookmarks"), 'Reputation recalculation aggregates publication metrics in SQL');
$pass(! str_contains($reputation, "->where('creator_id', ".'$user->id'.")->where('status', 'published')->get()"), 'Reputation recalculation does not hydrate all publications');

$citation = $read('app/Services/Knowledge/CitationNetworkService.php');
$pass(str_contains($citation, "withCount('citationsReceived')"), 'Citation node counts are eager aggregated');
$pass(! str_contains($citation, 'citationsReceived()->count()'), 'Citation graph does not count citations once per node');

$web = $read('routes/web.php');
$pass(! preg_match('/Route::(?:get|post|put|patch|delete|any|match)\([^\n]+(?:fn\s*\(|function\s*\()/m', $web), 'Web routes do not use closures, allowing route caching');

$js = $read('resources/js/app.js');
$pass(! str_contains($js, "import { initRichEditors } from './rich-editor'"), 'Rich editor is absent from the main static import graph');
$pass(str_contains($js, "import('./rich-editor')"), 'Rich editor is dynamically loaded only on editor screens');

$indexMigration = $root.'/database/migrations/2026_09_12_213000_add_hot_path_performance_indexes.php';
$pass(is_file($indexMigration), 'Audited hot-path database indexes are delivered as a migration');
if (is_file($indexMigration)) {
    $indexes = (string) file_get_contents($indexMigration);
    foreach (['notification_user_read_idx','notification_user_created_idx','invoice_user_status_idx','invoice_user_semester_idx','attendance_session_status_idx'] as $name) {
        $pass(str_contains($indexes, $name), "Index {$name} is present");
    }
}

if ($failures !== []) {
    fwrite(STDERR, "\nPerformance optimization preflight failed (".count($failures)." issue(s)).\n");
    exit(1);
}

echo "\nPerformance optimization preflight passed.\n";
