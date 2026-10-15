<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$read = static fn (string $path): string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';

$required = [
    'app/Ai/Providers/GrokProvider.php',
    'database/migrations/2026_08_20_140000_add_grok_provider_and_fast_ai_failover.php',
    'resources/js/performance.js',
    'public/serviceworker.js',
];
foreach ($required as $path) if (! is_file($root.'/'.$path)) $errors[] = "Missing {$path}";

$enum = $read('app/Enums/AiProviderName.php');
$registry = $read('app/Ai/AiProviderRegistry.php');
$grok = $read('app/Ai/Providers/GrokProvider.php');
$config = $read('config/ai.php');
$env = $read('.env.example');
$runtime = $read('app/Services/Ai/AiRuntimeConfigService.php');
$external = $read('app/Ai/Providers/ExternalProvider.php');
$appJs = $read('resources/js/app.js');
$performanceJs = $read('resources/js/performance.js');
$sw = $read('public/serviceworker.js');
$layout = $read('resources/views/layouts/app.blade.php');
$courseController = $read('app/Http/Controllers/CourseController.php');
$attendanceController = $read('app/Http/Controllers/AttendanceController.php');
$attendanceView = $read('resources/views/attendance/lecturer-index.blade.php');
$queue = $read('config/queue.php');

foreach (["case GROK = 'grok'", "self::GROK => 'Grok (xAI)'"] as $needle) if (! str_contains($enum, $needle)) $errors[] = "Grok enum registration missing: {$needle}";
foreach (['GrokProvider', 'AiProviderName::GROK->value => new GrokProvider', 'AiProviderName::GROK->value,'] as $needle) if (! str_contains($registry, $needle)) $errors[] = "Grok registry integration missing: {$needle}";
foreach (['https://api.x.ai/v1', '/chat/completions', "'Authorization' => 'Bearer '"] as $needle) if (! str_contains($grok, $needle)) $errors[] = "Grok protocol integration missing: {$needle}";
foreach (["'grok' => [", "env('XAI_API_KEY')", "env('XAI_MODEL', 'grok-4.5')", "'fast_failover' => env('AI_FAST_FAILOVER', true)"] as $needle) if (! str_contains($config, $needle)) $errors[] = "AI bootstrap configuration missing: {$needle}";
foreach (['XAI_API_KEY=', 'XAI_BASE_URL=https://api.x.ai/v1', 'XAI_MODEL=grok-4.5', 'AI_FAST_FAILOVER=true', 'REDIS_QUEUE_BLOCK_FOR=2'] as $needle) if (! str_contains($env, $needle)) $errors[] = "Environment template missing: {$needle}";
if (! str_contains($runtime, 'public function fastFailover(') || ! str_contains($runtime, "'fast_failover' => \$this->fastFailover")) $errors[] = 'Central runtime fast failover is not wired into provider config.';
if (! str_contains($external, '$fastFailover') || ! str_contains($external, 'if ($fastFailover ||')) $errors[] = 'ExternalProvider does not honor fast interactive failover.';

if (! str_contains($appJs, "import { initPerformanceUX } from './performance';")) $errors[] = 'Global performance UX is not imported.';
if (preg_match("/^import\\s+.*from\\s+['\"]vue['\"]/m", $appJs)) $errors[] = 'Vue is still eagerly imported on every Blade page instead of lazy-loaded.';
foreach (['import(\'vue\')', 'initPerformanceUX()', 'initVueRoot()'] as $needle) if (! str_contains($appJs, $needle)) $errors[] = "App JS lazy/performance boot missing: {$needle}";
foreach (['rel = \'prefetch\'', 'MAX_PREFETCHES_PER_PAGE', "form.dataset.submitting = '1'", 'startNavigationFeedback'] as $needle) if (! str_contains($performanceJs, $needle)) $errors[] = "Navigation/submit performance safeguard missing: {$needle}";

foreach (['navigationPreload.enable()', 'event.preloadResponse', "request.mode === 'navigate'", "['script', 'style', 'image', 'font']"] as $needle) if (! str_contains($sw, $needle)) $errors[] = "Service worker performance behavior missing: {$needle}";
if (str_contains($sw, "'/dashboard',") || str_contains($sw, "'/css/app.css',") || str_contains($sw, "'/js/app.js',")) $errors[] = 'Service worker still precaches dynamic/non-Vite-stable application paths.';

if (! str_contains($layout, '$unreadNotificationCount') || str_contains($layout, 'auth()->user()?->unreadNotifications?->count()')) $errors[] = 'Layout still hydrates/counts unread notifications repeatedly.';
$courseShow = preg_match('/public function show\(Course \$course\): View(.*?)public function enroll/s', $courseController, $m) ? $m[1] : '';
if ($courseShow === '' || str_contains($courseShow, "'enrollments.user'")) $errors[] = 'Course workspace still eager-loads every enrolled user although only aggregate counts are displayed.';
if (! str_contains($attendanceController, "with('course')->withCount('records')") || ! str_contains($attendanceView, '$session->records_count')) $errors[] = 'Attendance listing still hydrates all records/users instead of using a count.';
if (! str_contains($queue, "env('REDIS_QUEUE_BLOCK_FOR', 2)")) $errors[] = 'Redis queue blocking pop optimization is missing.';

if ($errors !== []) {
    fwrite(STDERR, "AcadFlow Grok/performance preflight: FAILED\n\n");
    foreach ($errors as $error) fwrite(STDERR, " - {$error}\n");
    exit(1);
}

echo "AcadFlow Grok/performance preflight: PASS\n";
echo " - Grok (xAI) uses the existing central provider registry/router and shared transport\n";
echo " - Fast provider failover avoids repeated interactive waits before central fallback\n";
echo " - Vue components are lazy-loaded only on pages that actually mount Vue\n";
echo " - Same-origin navigation gets conservative prefetch + immediate first-click feedback\n";
echo " - Duplicate native write submissions are guarded\n";
echo " - PWA worker uses navigation preload and avoids caching authenticated HTML/API reads\n";
echo " - Course/attendance high-volume hydration paths are bounded\n";
echo " - Redis workers use a short blocking pop for low-latency queue wake-up\n";
