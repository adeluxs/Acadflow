<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeCommunity;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Policies\KnowledgeCommunityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EnterpriseIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_registration_creates_neutral_member_and_resumable_onboarding(): void
    {
        Event::fake();

        $response = $this->post(route('store-register'), [
            'account_type' => 'independent_professional',
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'username' => 'ada.okafor',
            'email' => 'ada@example.test',
            'password' => 'EnterprisePass123!',
            'password_confirmation' => 'EnterprisePass123!',
            'terms' => '1',
        ]);

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame('member', $user->role);
        $this->assertNull($user->university_id);
        $this->assertNull($user->onboarding_completed_at);
        $this->assertDatabaseHas('user_onboarding_states', [
            'user_id' => $user->id,
            'path' => 'independent_professional',
            'current_step' => 2,
        ]);
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_student_registration_does_not_grant_privileged_role(): void
    {
        Event::fake();

        $this->post(route('store-register'), [
            'account_type' => 'student',
            'first_name' => 'Tunde',
            'last_name' => 'Bello',
            'username' => 'tunde.bello',
            'email' => 'tunde@example.test',
            'password' => 'EnterprisePass123!',
            'password_confirmation' => 'EnterprisePass123!',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'tunde@example.test', 'role' => 'student']);
    }

    public function test_institutional_onboarding_cannot_skip_affiliation(): void
    {
        $user = User::factory()->withoutOnboarding()->create(['account_type' => 'student', 'role' => 'student']);
        UserOnboardingState::create([
            'user_id' => $user->id,
            'path' => 'student',
            'current_step' => 3,
            'data' => ['path' => 'student', 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'username' => 'student-'.$user->id],
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('onboarding.skip', 'affiliation'))
            ->assertSessionHasErrors('university_id');
    }

    public function test_institution_visibility_never_treats_two_null_universities_as_same_tenant(): void
    {
        $member = User::factory()->member()->create();
        $community = new KnowledgeCommunity([
            'visibility' => 'institution',
            'status' => 'active',
            'owner_id' => $member->id + 100,
            'university_id' => null,
        ]);

        $this->assertFalse((new KnowledgeCommunityPolicy())->view($member, $community));
    }

    public function test_api_token_cannot_bypass_incomplete_onboarding(): void
    {
        $user = User::factory()->withoutOnboarding()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertStatus(403)
            ->assertJsonPath('next_action', 'complete_onboarding');
    }
}
