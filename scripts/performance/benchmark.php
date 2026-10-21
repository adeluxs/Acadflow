<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
$started = microtime(true);
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with((string) $database, '/private/tmp/acadsyntra-studio-')) {
    throw new RuntimeException('Benchmark only against the isolated testing database.');
}
$options = getopt('', ['user:', 'route:', 'runs:', 'output:']);
$user = App\Models\User::findOrFail((int) ($options['user'] ?? 1));
$path = $options['route'] ?? '/research-studio/create';
$runs = max(1, min(100, (int) ($options['runs'] ?? 10)));
$queries = [];
Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) { $queries[] = ['sql' => $query->sql, 'ms' => $query->time]; });
$bootstrap = (microtime(true) - $started) * 1000;
$samples = [];
for ($i = 0; $i < $runs; $i++) {
    auth()->setUser($user);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create($path);
    $queries = [];
    $start = microtime(true);
    $response = $kernel->handle($request);
    $samples[] = ['ms' => round((microtime(true) - $start) * 1000, 2), 'status' => $response->getStatusCode(), 'bytes' => strlen($response->getContent()), 'queries' => count($queries), 'sql_ms' => array_sum(array_column($queries, 'ms'))];
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Benchmark received a non-200 response.');
}
$times = array_column($samples, 'ms'); sort($times);
$report = ['route' => $path, 'bootstrap_ms' => round($bootstrap, 2), 'p50_ms' => $times[(int) ceil($runs * .5) - 1], 'p95_ms' => $times[(int) ceil($runs * .95) - 1], 'peak_mb' => memory_get_peak_usage(true) / 1048576, 'samples' => $samples, 'last_query_shapes' => array_column($queries, 'sql')];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
if (isset($options['output'])) file_put_contents($options['output'], $json);
echo $json;
