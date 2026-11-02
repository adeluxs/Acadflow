<?php

namespace Tests\Feature;

use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionOnboardingSearchRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_supports_full_partial_case_insensitive_and_trimmed_terms(): void
    {
        $this->withoutMiddleware();
        University::factory()->create([
            'name' => 'University of Lagos',
            'short_name' => 'UNILAG',
            'code' => 'UNILAG',
            'state' => 'Lagos',
            'regulator' => 'NUC',
        ]);
        University::factory()->create(['name' => 'University of Ibadan', 'short_name' => 'UI', 'code' => 'UI']);

        $this->getJson(route('institution.onboarding.search', ['q' => 'University of Lagos']))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.0.name', 'University of Lagos');

        $partial = $this->getJson(route('institution.onboarding.search', ['q' => 'Lagos']))->assertOk()->json('data');
        $this->assertContains('University of Lagos', array_column($partial, 'name'));

        $caseInsensitive = $this->getJson(route('institution.onboarding.search', ['q' => 'uNiVeRsItY oF']))->assertOk()->json('data');
        $this->assertCount(2, $caseInsensitive);

        $trimmed = $this->getJson(route('institution.onboarding.search', ['q' => '  lagos  ']))->assertOk()->json('data');
        $this->assertContains('University of Lagos', array_column($trimmed, 'name'));
    }

    public function test_search_returns_empty_collection_and_validation_error_predictably(): void
    {
        $this->withoutMiddleware();
        University::factory()->create(['name' => 'University of Lagos']);

        $this->getJson(route('institution.onboarding.search', ['q' => 'Zaria Example Missing']))
            ->assertOk()->assertExactJson(['success' => true, 'data' => []]);

        $this->getJson(route('institution.onboarding.search', ['q' => 'x']))
            ->assertStatus(422)->assertJsonValidationErrors('q');
    }
}
