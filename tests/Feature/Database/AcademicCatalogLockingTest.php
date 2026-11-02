<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\University;
use App\Services\NigeriaAcademicCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AcademicCatalogLockingTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'acadflow-lock-test-');
        config(['database.connections.catalog_lock_test' => array_replace(
            config('database.connections.sqlite'),
            ['database' => $this->databasePath, 'url' => null]
        )]);
        DB::setDefaultConnection('catalog_lock_test');
        $this->artisan('migrate', ['--database' => 'catalog_lock_test', '--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::purge('catalog_lock_test');
        foreach (['', '-wal', '-shm'] as $suffix) {
            if (is_file($this->databasePath.$suffix)) unlink($this->databasePath.$suffix);
        }
        parent::tearDown();
    }

    public function test_template_seed_waits_for_a_writer_and_preserves_its_settings(): void
    {
        $this->assertSame('wal', DB::selectOne('PRAGMA journal_mode')->journal_mode);
        $this->assertSame(15000, DB::selectOne('PRAGMA busy_timeout')->timeout);
        $institution = University::factory()->create(['institution_type' => 'university']);
        $settings = ['administrator_note' => 'Preserve concurrent edit', 'academic_catalog' => ['requires_institution_verification' => false]];
        $writer = new Process([PHP_BINARY, '-r', <<<'PHP'
            $pdo = new PDO('sqlite:'.$argv[1]);
            $pdo->exec('BEGIN IMMEDIATE');
            $stmt = $pdo->prepare('UPDATE universities SET settings = ? WHERE id = ?');
            $stmt->execute([$argv[3], $argv[2]]);
            echo "LOCKED\n";
            flush();
            usleep(350000);
            $pdo->exec('COMMIT');
            PHP, $this->databasePath, (string) $institution->id, json_encode($settings, JSON_THROW_ON_ERROR)]);
        $writer->setTimeout(10);

        try {
            $writer->start();
            $this->assertTrue($writer->waitUntil(fn ($type, $output) => str_contains($output, 'LOCKED')));
            $start = microtime(true);
            $stats = app(NigeriaAcademicCatalogService::class)->seedStarterTemplate($institution);
            $this->assertGreaterThan(0.2, microtime(true) - $start);
            $writer->wait();
            $this->assertTrue($writer->isSuccessful(), $writer->getErrorOutput());
            $this->assertSame($settings['administrator_note'], $institution->fresh()->settings['administrator_note']);
            $this->assertFalse($institution->fresh()->settings['academic_catalog']['requires_institution_verification']);
            $this->assertSame(Faculty::count(), $stats['faculties']);
            $this->assertSame(Department::count(), $stats['departments']);
            $this->assertSame(Course::count(), $stats['courses']);

            $timestamp = $institution->fresh()->getRawOriginal('updated_at');
            $again = app(NigeriaAcademicCatalogService::class)->seedStarterTemplate($institution);
            $this->assertSame(['institutions' => 1, 'faculties' => 0, 'departments' => 0, 'courses' => 0], $again);
            $this->assertSame($timestamp, $institution->fresh()->getRawOriginal('updated_at'));
        } finally {
            if ($writer->isRunning()) $writer->stop();
        }
    }

    public function test_transaction_retry_discards_rolled_back_counts(): void
    {
        $institution = University::factory()->create(['institution_type' => 'university']);
        $failed = false;
        Event::listen('eloquent.created: '.Faculty::class, function () use (&$failed): void {
            if (! $failed) {
                $failed = true;
                throw new PDOException('database is locked');
            }
        });

        $stats = app(NigeriaAcademicCatalogService::class)->seedStarterTemplate($institution);
        $this->assertTrue($failed);
        $this->assertGreaterThan(0, $stats['faculties']);
        $this->assertSame(Faculty::count(), $stats['faculties']);
        $this->assertSame(Department::count(), $stats['departments']);
        $this->assertSame(Course::count(), $stats['courses']);
    }

    public function test_unrelated_errors_roll_back_without_retrying(): void
    {
        $institution = University::factory()->create();
        $attempts = 0;
        Event::listen('eloquent.created: '.Faculty::class, function () use (&$attempts): void {
            $attempts++;
            throw new RuntimeException('Invalid catalogue fixture');
        });

        try {
            app(NigeriaAcademicCatalogService::class)->seedStarterTemplate($institution);
            $this->fail('Expected the unrelated error to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('Invalid catalogue fixture', $e->getMessage());
        }
        $this->assertSame(1, $attempts);
        $this->assertSame(0, Faculty::count());
        $this->assertNull($institution->fresh()->settings);
    }

    public function test_templates_only_command_resumes_without_remote_registry_requests(): void
    {
        Http::preventStrayRequests();
        $institution = University::factory()->create(['settings' => ['custom' => 'keep']]);
        $this->artisan('acadflow:sync-nigeria-catalog', ['--templates-only' => true])->assertSuccessful();
        $counts = [Faculty::count(), Department::count(), Course::count()];
        $this->artisan('acadflow:sync-nigeria-catalog', ['--templates-only' => true])->assertSuccessful();
        $this->assertSame($counts, [Faculty::count(), Department::count(), Course::count()]);
        $this->assertSame('keep', $institution->fresh()->settings['custom']);
        Http::assertNothingSent();
    }

    public function test_templates_only_command_rejects_conflicting_options(): void
    {
        Http::preventStrayRequests();
        foreach (['--no-templates' => true, '--csv' => '/unused.csv'] as $option => $value) {
            $this->artisan('acadflow:sync-nigeria-catalog', ['--templates-only' => true, $option => $value])->assertFailed();
        }
        $this->assertSame(0, Faculty::count());
        Http::assertNothingSent();
    }
}
