<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResearchType;
use App\Models\University;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\FeatureAccessService;
use App\Services\IndependentAcademicContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_creation_modes_use_small_type_records_and_defer_institutional_queries(): void
    {
        $university = University::factory()->create();
        $user = User::factory()->create(['university_id' => $university->id, 'role' => 'student']);
        app(IndependentAcademicContextService::class)->ensureResearchType();
        FeatureAccessService::setStatus('research_institutional_expansion', 'enabled', $user->id);
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        foreach (['research', 'final_year'] as $mode) {
            $queries = [];
            $response = $this->actingAs($user)->get(route('research.create', ['studio_mode' => $mode]));
            $response->assertOk()->assertViewHas('departments', fn ($rows) => $rows->isEmpty())
                ->assertViewHas('supervisors', fn ($rows) => $rows->isEmpty())
                ->assertViewHas('types', fn ($rows) => $rows->every(fn ($type) => array_keys($type->getAttributes()) === ['id', 'name']));
            $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'from "departments"')));
            preg_match('/data-methodology-catalogue>(.*?)<\/script>/s', $response->getContent(), $catalogue);
            $this->assertNotEmpty($catalogue);
            foreach (json_decode($catalogue[1], true, flags: JSON_THROW_ON_ERROR) as $method) {
                $this->assertEqualsCanonicalizing(['label', 'decision', 'instruments'], array_keys($method));
            }
        }
    }

    public function test_optional_lookups_are_paginated_searchable_and_tenant_scoped(): void
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Visible department']);
        $foreign = Department::factory()->create(['name' => 'Foreign department']);
        $user = User::factory()->create(['role' => 'university_admin', 'university_id' => $university->id]);
        User::factory()->count(26)->create(['role' => 'lecturer', 'university_id' => $university->id, 'department_id' => $department->id]);
        User::factory()->create(['role' => 'lecturer', 'first_name' => 'Foreign', 'university_id' => $foreign->faculty->university_id]);
        FeatureAccessService::setStatus('research_institutional_expansion', 'enabled', $user->id);

        $this->actingAs($user)->getJson(route('research.create.options', ['kind' => 'departments']))
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('options.0.label', 'Visible department');
        $first = $this->getJson(route('research.create.options', ['kind' => 'supervisors']))
            ->assertOk()->assertJsonCount(25, 'options')->assertJsonPath('next_page', 2);
        $this->assertSame(['id', 'label'], array_keys($first->json('options.0')));
        $this->getJson(route('research.create.options', ['kind' => 'supervisors', 'page' => 2]))
            ->assertOk()->assertJsonCount(1, 'options')->assertJsonPath('next_page', null);
        $this->getJson(route('research.create.options', ['kind' => 'departments', 'q' => 'Foreign']))
            ->assertOk()->assertJsonCount(0, 'options');
    }

    public function test_disabled_institutional_lookups_and_ineligible_accounts_are_denied(): void
    {
        $user = User::factory()->create(['role' => 'student', 'university_id' => University::factory()->create()->id]);
        $this->actingAs($user)->getJson(route('research.create.options', ['kind' => 'supervisors']))->assertForbidden();
        FeatureAccessService::setStatus('research_institutional_expansion', 'enabled', $user->id);
        $user->update(['is_active' => false]);
        $this->getJson(route('research.create.options', ['kind' => 'supervisors']))->assertForbidden();
    }

    public function test_admin_configuration_and_template_pages_are_bounded_without_losing_later_records(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        for ($i = 0; $i < 31; $i++) {
            ResearchType::create(['name' => sprintf('Type %02d', $i), 'slug' => 'type-'.$i, 'is_active' => true]);
        }
        for ($i = 0; $i < 12; $i++) {
            WorkflowDefinition::create(['name' => 'Workflow '.$i, 'key' => 'workflow_'.$i, 'subject_type' => 'research', 'is_active' => true]);
        }
        $this->actingAs($admin)->get(route('research.configuration.index'))->assertOk()
            ->assertViewHas('types', fn ($rows) => $rows->count() === 15 && $rows->total() === 31)
            ->assertViewHas('workflows', fn ($rows) => $rows->count() === 10 && $rows->total() === 12)
            ->assertViewHas('workflowOptions', fn ($rows) => $rows->count() === 12);
        $this->get(route('research.templates.index', ['page' => 3]))->assertOk()
            ->assertViewHas('types', fn ($rows) => $rows->count() === 1 && $rows->total() === 31);
    }

    public function test_timing_metrics_are_opt_in_and_do_not_expose_request_input(): void
    {
        $this->get('/login')->assertHeaderMissing('Server-Timing');
        config(['performance.server_timing' => true]);
        $response = $this->get('/login?secret=must-never-appear');
        $response->assertOk();
        $this->assertMatchesRegularExpression('/^app;dur=\d+\.\d{2}$/', $response->headers->get('Server-Timing'));
    }
}
