<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CommercialAccount;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\Course;
use App\Models\LecturerCourseAssignment;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceRevenueAllocation;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Models\Setting;
use App\Models\SettingOverride;
use App\Services\AdminLookupService;
use App\Services\MeaningfulActivityService;
use App\Services\SettingService;
use App\Http\Controllers\SettingsController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminManagementPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_management_pages_render_with_a_large_catalogue(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'last_meaningful_activity_at' => now()]);
        $university = University::factory()->create(['code' => 'BENCH']);
        for ($i = 0; $i < 120; $i++) {
            $faculty = Faculty::factory()->create(['university_id' => $university->id, 'code' => 'F'.$i, 'name' => sprintf('Faculty %03d', $i)]);
            for ($j = 0; $j < 3; $j++) {
                Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'D'.$j]);
            }
        }
        $users = User::factory()->count(250)->create(['university_id' => $university->id, 'role' => 'lecturer']);
        foreach ($users as $user) {
            AuditLog::log('login', $user->id);
        }
        for ($i = 0; $i < 60; $i++) {
            $institution = University::factory()->create(['code' => 'B'.$i]);
            CommercialAccount::create(['university_id' => $institution->id, 'name' => 'Benchmark '.$i, 'currency' => 'NGN', 'status' => 'active']);
        }

        $hydrated = [];
        Event::listen('eloquent.retrieved: *', function ($event, $models) use (&$hydrated): void {
            $name = class_basename($models[0]);
            $hydrated[$name] = ($hydrated[$name] ?? 0) + 1;
        });
        $this->actingAs($super);
        $report = [];
        foreach (['admin.users', 'admin.faculties', 'admin.universities', 'admin.settings.audit-logs', 'admin.monetization'] as $route) {
            $this->get(route($route))->assertOk(); // warm settings, templates and feature catalogues
            $samples = [];
            for ($i = 0; $i < 3; $i++) {
                $hydrated = [];
                DB::enableQueryLog();
                DB::flushQueryLog();
                $started = hrtime(true);
                $response = $this->get(route($route))->assertOk();
                $samples[] = ['ms' => round((hrtime(true) - $started) / 1e6, 2), 'queries' => count(DB::getQueryLog()), 'bytes' => strlen($response->getContent()), 'hydrated' => $hydrated];
                DB::disableQueryLog();
            }
            $report[$route] = $samples;
            $last = $samples[2]['hydrated'];
            if ($route === 'admin.users') $this->assertLessThanOrEqual(20, $last['Department'] ?? 0);
            if ($route === 'admin.faculties') {
                $this->assertSame(25, $last['Faculty']);
                $this->assertSame(0, $last['Department'] ?? 0);
                $this->assertSame(0, $last['User'] ?? 0);
                $this->get(route($route, ['page' => 2]))->assertViewHas('faculties', fn ($rows) => $rows->count() === 25 && $rows->first()->departments_count === 3);
                $this->get(route($route, ['q' => 'Faculty 119']))->assertViewHas('faculties', fn ($rows) => $rows->total() === 1);
            }
            if ($route === 'admin.settings.audit-logs') $this->assertLessThanOrEqual(51, $last['User']);
            if ($route === 'admin.monetization') $this->assertSame(20, $last['CommercialAccount']);
        }
        if ($output = getenv('ADMIN_PERFORMANCE_REPORT')) {
            file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
        }
    }

    private function campus(): array
    {
        $university = University::factory()->create(['code' => 'C'.University::count(), 'adoption_status' => 'active']);
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id, 'adoption_status' => 'institution_managed']);
        return [$university, $faculty, $department];
    }

    private function member(User $user, University $university, Department $department, string $role): UserInstitutionAffiliation
    {
        return UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $university->id, 'faculty_id' => $department->faculty_id,
            'department_id' => $department->id, 'affiliation_type' => $role, 'status' => 'verified', 'verified_at' => now(), 'source' => 'test']);
    }

    public function test_lookups_are_paged_searchable_small_and_preserve_department_boundaries(): void
    {
        [$institution, $faculty, $department] = $this->campus();
        [, , $foreign] = $this->campus();
        for ($i = 0; $i < 27; $i++) Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'L'.$i]);
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super);
        $first = $this->getJson(route('admin.lookups', ['kind' => 'departments']))->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('next_page', 2);
        $second = $this->getJson(route('admin.lookups', ['kind' => 'departments', 'page' => 2]))->assertOk()->assertJsonCount(9, 'data')->assertJsonPath('next_page', null);
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->assertSame(['id', 'label'], array_keys($first->json('data.0')));
        $this->getJson(route('admin.lookups', ['kind' => 'departments', 'q' => $foreign->name]))->assertOk()->assertJsonPath('data.0.id', $foreign->id);
        $admin = User::factory()->create(['role' => 'department_admin', 'university_id' => $institution->id, 'department_id' => $department->id]);
        $membership = $this->member($admin, $institution, $department, 'department_admin');
        $this->actingAs($admin)->getJson(route('admin.lookups', ['kind' => 'departments']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $department->id);
        foreach (['audit_users', 'universities', 'commercial_universities', 'deans'] as $kind) {
            $this->getJson(route('admin.lookups', ['kind' => $kind]))->assertForbidden();
        }
        $this->assertNull(app(AdminLookupService::class)->selected($admin, 'departments', $foreign->id));
        $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->actingAs($admin->fresh())->getJson(route('admin.lookups', ['kind' => 'departments']))->assertForbidden();
    }

    public function test_user_filters_follow_approved_memberships_even_when_primary_department_differs(): void
    {
        [$institution, , $department] = $this->campus();
        [, , $foreign] = $this->campus();
        $admin = User::factory()->create(['role' => 'department_admin', 'university_id' => $institution->id, 'department_id' => $department->id]);
        $this->member($admin, $institution, $department, 'department_admin');
        $member = User::factory()->create(['role' => 'lecturer', 'department_id' => $foreign->id, 'email' => 'scoped@example.test']);
        $this->member($member, $institution, $department, 'lecturer');
        $this->actingAs($admin)->get(route('admin.users', ['department_id' => $department->id, 'role' => 'lecturer', 'q' => 'scoped@']))
            ->assertOk()->assertViewHas('users', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $member->id);
        $this->get(route('admin.users', ['department_id' => $foreign->id]))->assertForbidden();
        $this->get(route('admin.users.edit', $member))->assertForbidden();
        $member->update(['department_id' => $department->id]);
        $this->get(route('admin.users.edit', $member))->assertRedirect(route('academic.organisations'));
    }

    public function test_activity_skips_recent_writes_and_records_the_next_day_and_expired_window(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $user = User::factory()->create(['last_meaningful_activity_at' => now()]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(MeaningfulActivityService::class)->record($user);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->travel(5)->minutes();
        app(MeaningfulActivityService::class)->record($user);
        $this->assertDatabaseCount('user_activity_days', 1);
        $this->travelTo(now()->endOfDay()->subMinute());
        app(MeaningfulActivityService::class)->record($user->fresh());
        $this->travel(2)->minutes();
        app(MeaningfulActivityService::class)->record($user->fresh());
        $this->assertDatabaseCount('user_activity_days', 2);
        $this->travelBack();
    }

    public function test_audit_dates_are_inclusive_filters_survive_paging_and_preview_values_are_bounded(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        for ($i = 0; $i < 56; $i++) {
            $log = AuditLog::log('custom_action', $super->id, oldValue: str_repeat('x', 2000), newValue: 'New value', fieldName: 'test');
            $log->forceFill(['created_at' => '2026-10-20 23:59:59'])->saveQuietly();
        }
        AuditLog::log('outside', $super->id)->forceFill(['created_at' => '2026-10-21 00:00:00'])->saveQuietly();
        $this->actingAs($super)->get(route('admin.settings.audit-logs', ['date_to' => '2026-10-20', 'action' => 'custom_action']))->assertOk()
            ->assertViewHas('logs', fn ($rows) => $rows->count() === 50 && strlen($rows->first()->old_value) === 200)
            ->assertSee('date_to=2026-10-20', false)->assertSee('action=custom_action', false)->assertDontSee('outside');
        $this->get(route('admin.settings.audit-logs', ['date_to' => '2026-10-20', 'action' => 'custom_action', 'page' => 2]))->assertOk()
            ->assertViewHas('logs', fn ($rows) => $rows->count() === 6);
        $this->getJson(route('admin.settings.audit-logs', ['date_from' => 'invalid']))->assertUnprocessable();
    }

    public function test_settings_bulk_resolution_keeps_global_scope_and_institution_overrides(): void
    {
        [$institution] = $this->campus();
        for ($i = 0; $i < 80; $i++) Setting::create(['key' => 'performance_setting_'.$i, 'value' => '5', 'type' => 'integer', 'group' => 'general']);
        $setting = Setting::where('key', 'performance_setting_0')->firstOrFail();
        SettingOverride::create(['setting_id' => $setting->id, 'university_id' => $institution->id, 'value' => '17', 'type' => 'integer']);
        $super = User::factory()->create(['role' => 'super_admin', 'university_id' => $institution->id]);
        $this->actingAs($super);
        Cache::flush();
        SettingService::flushRequestCache();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $view = app(SettingsController::class)->index();
        $reads = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select'));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(5, $reads->count());
        $this->assertSame(5, $view->getData()['settings']['general']->firstWhere('key', 'performance_setting_0')->value);
        $admin = User::factory()->create(['role' => 'university_admin', 'university_id' => $institution->id]);
        $this->actingAs($admin);
        SettingService::flushRequestCache();
        $view = app(SettingsController::class)->index();
        $this->assertSame(17, $view->getData()['settings']['general']->firstWhere('key', 'performance_setting_0')->value);
    }

    public function test_account_editing_resolves_an_off_page_account_with_fresh_terms(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $first = null;
        for ($i = 0; $i < 26; $i++) {
            $institution = University::factory()->create(['code' => 'P'.$i]);
            $account = CommercialAccount::create(['university_id' => $institution->id, 'name' => 'Account '.$i, 'currency' => 'CAD', 'status' => 'active', 'prepaid_balance_minor' => 12345, 'metadata' => ['sponsored_features' => ['attendance_tracking']]]);
            $first ??= $account;
        }
        $this->actingAs($super)->get(route('admin.monetization', ['institution' => $first->university_id]))->assertOk()
            ->assertViewHas('commercialAccounts', fn ($rows) => $rows->count() === 20 && ! $rows->contains('id', $first->id))
            ->assertViewHas('setupAccount', fn ($row) => $row->id === $first->id && $row->prepaid_balance_minor === 12345 && $row->currency === 'CAD');
        $first->update(['prepaid_balance_minor' => 54321]);
        $this->get(route('admin.monetization', ['institution' => $first->university_id]))->assertOk()
            ->assertViewHas('setupAccount', fn ($row) => $row->prepaid_balance_minor === 54321);
        $this->getJson(route('admin.lookups', ['kind' => 'commercial_universities']))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_management_indexes_remove_unbounded_order_sorts_and_are_restartable(): void
    {
        $migration = require database_path('migrations/2026_10_22_190000_add_admin_management_indexes.php');
        $migration->down();
        $sql = 'EXPLAIN QUERY PLAN SELECT id FROM users ORDER BY created_at DESC, id DESC LIMIT 20';
        $this->assertStringContainsString('TEMP B-TREE', json_encode(DB::select($sql)));
        $migration->up();
        $migration->up();
        $plan = json_encode(DB::select($sql));
        $this->assertStringContainsString('admin_users_created_idx', $plan);
        $this->assertStringNotContainsString('TEMP B-TREE', $plan);
        $this->assertTrue(Schema::hasIndex('audit_logs', 'admin_audit_user_created_idx'));
    }

    public function test_audit_writes_introspect_legacy_columns_once_and_retain_both_formats(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $log = AuditLog::log('update', modelType: 'Example', modelId: 9, oldValue: ['value' => 'before'], newValue: ['value' => 'after']);
        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()), 'One column listing (including SQLite schema discovery) and one insert, independent of legacy field count.');
        DB::disableQueryLog();
        $this->assertSame('Example', $log->entity_type);
        $this->assertSame(9, $log->entity_id);
        $this->assertSame(['value' => 'after'], $log->new_values);
        $this->assertSame('{"value":"after"}', $log->new_value);
    }

    public function test_course_lecturer_lookup_and_import_semesters_follow_current_department_scope(): void
    {
        [$institution, , $department] = $this->campus();
        [$foreignInstitution, , $foreignDepartment] = $this->campus();
        $admin = User::factory()->create(['role' => 'department_admin', 'university_id' => $institution->id, 'department_id' => $department->id]);
        $this->member($admin, $institution, $department, 'department_admin');
        $course = Course::factory()->create(['department_id' => $department->id]);
        $foreignCourse = Course::factory()->create(['department_id' => $foreignDepartment->id]);
        $session = AcademicSession::factory()->create(['university_id' => $institution->id]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id]);
        Semester::factory()->create(['academic_session_id' => AcademicSession::factory()->create(['university_id' => $foreignInstitution->id])->id]);
        $lecturers = User::factory()->count(24)->create(['department_id' => $foreignDepartment->id]);
        foreach ($lecturers as $lecturer) $this->member($lecturer, $institution, $department, 'lecturer');
        LecturerCourseAssignment::create(['course_id' => $course->id, 'user_id' => $lecturers[0]->id, 'semester_id' => $semester->id]);
        $this->actingAs($admin)->get(route('admin.courses.show', $course))->assertOk()->assertViewHas('lecturers', fn ($rows) => $rows->count() === 20 && ! $rows->contains('id', $lecturers[0]->id));
        $first = $this->getJson(route('admin.lookups', ['kind' => 'lecturers', 'course_id' => $course->id]))->assertOk()->assertJsonCount(20, 'data');
        $second = $this->getJson(route('admin.lookups', ['kind' => 'lecturers', 'course_id' => $course->id, 'page' => 2]))->assertOk()->assertJsonCount(3, 'data');
        $this->assertNotContains($lecturers[0]->id, array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson(route('admin.lookups', ['kind' => 'lecturers', 'course_id' => $foreignCourse->id]))->assertNotFound();
        $this->getJson(route('admin.lookups', ['kind' => 'semesters', 'department_id' => $department->id]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $semester->id);
        $this->getJson(route('admin.lookups', ['kind' => 'semesters', 'department_id' => $foreignDepartment->id]))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_large_revenue_totals_preserve_per_allocation_reversals_and_currency(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $items = [];
        foreach (['NGN', 'USD'] as $currency) {
            $order = CommerceOrder::create(['buyer_id' => $super->id, 'order_number' => 'PERF-'.$currency, 'currency' => $currency, 'subtotal' => '1.00', 'total_amount' => '1.00']);
            $items[$currency] = CommerceOrderItem::create(['commerce_order_id' => $order->id, 'purchasable_type' => 'test', 'purchasable_id' => 1, 'title' => 'Performance test', 'quantity' => 1, 'unit_price' => '1.00', 'total_price' => '1.00']);
        }
        $allocations = [];
        for ($i = 0; $i < 501; $i++) $allocations[] = ['commerce_order_item_id' => $items['NGN']->id, 'allocation_type' => 'platform', 'amount' => '1.00', 'amount_minor' => 100, 'metadata' => json_encode(['reversed_amount_minor' => $i === 500 ? 150 : 25])];
        DB::table('commerce_revenue_allocations')->insert($allocations);
        CommerceRevenueAllocation::create(['commerce_order_item_id' => $items['USD']->id, 'allocation_type' => 'platform', 'amount' => '9.99', 'amount_minor' => 999]);
        SettingService::set('currency', 'NGN', 'string');
        $this->actingAs($super)->get(route('admin.monetization'))->assertOk()->assertViewHas('metrics', fn ($metrics) => $metrics['platform_revenue_minor'] === 37500);
    }

    public function test_edit_forms_preserve_existing_inactive_selections_without_listing_inactive_options(): void
    {
        [$institution, $faculty, $department] = $this->campus();
        $super = User::factory()->create(['role' => 'super_admin']);
        $dean = User::factory()->create(['role' => 'lecturer', 'university_id' => $institution->id, 'is_active' => false]);
        $faculty->update(['dean_id' => $dean->id]);
        $department->update(['is_active' => false]);
        $member = User::factory()->create(['department_id' => $department->id]);
        $this->actingAs($super)->get(route('admin.faculties.edit', $faculty))->assertOk()->assertViewHas('selectedDean', fn ($selected) => $selected['id'] === $dean->id);
        $this->get(route('admin.users.edit', $member))->assertOk()->assertViewHas('selectedDepartment', fn ($selected) => $selected['id'] === $department->id);
        $this->getJson(route('admin.lookups', ['kind' => 'deans', 'university_id' => $institution->id, 'include_inactive' => true]))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('admin.lookups', ['kind' => 'departments', 'include_inactive' => true]))->assertOk()->assertJsonCount(0, 'data');
    }
}
