<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserOnboardingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchOnboardingExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_onboarding_resumes_and_can_launch_first_final_year_project(): void
    {
        $user = User::factory()->withoutOnboarding()->create(['role' => 'student', 'account_type' => 'student']);
        $this->actingAs($user)->post(route('onboarding.save', 'path'), ['path' => 'student'])->assertSessionHasNoErrors();
        $this->post(route('onboarding.save', 'profile'), ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'username' => 'research-newcomer'])->assertSessionHasNoErrors();
        $this->post(route('onboarding.skip', 'affiliation'))->assertRedirect(route('onboarding.show', ['step' => 4]));
        $this->get(route('onboarding.show'))->assertOk()->assertSee('Choose the knowledge you care about')->assertSee('Progress saves after each step');
        $this->post(route('onboarding.save', 'interests'), ['programme' => 'Social sciences', 'academic_level' => 'hnd', 'research_interests_text' => 'Community health, Policy research'])->assertSessionHasNoErrors();
        $this->post(route('onboarding.save', 'preferences'), ['profile_visibility' => 'private', 'in_app_notifications' => 1])->assertSessionHasNoErrors();
        $this->get(route('onboarding.show'))->assertOk()->assertSee('Start a final-year project');
        $this->post(route('onboarding.complete'), ['next_action' => 'final_year'])->assertRedirect(route('research.create', ['studio_mode' => 'final_year']));
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->assertSame('hnd', $user->fresh()->academic_level);
        $this->assertSame(['Community health', 'Policy research'], $user->fresh()->research_interests);
        $this->assertSame('private', $user->fresh()->profile_visibility);
        $this->get(route('research.create', ['studio_mode' => 'final_year']))->assertOk()->assertSee('Social sciences');
    }

    public function test_existing_user_is_not_required_to_repeat_onboarding(): void
    {
        $user = User::factory()->create(['password' => 'ExistingPassword123!']);
        $before = $user->onboarding_completed_at;
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ExistingPassword123!'])->assertRedirect(route('dashboard'));
        $this->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
        $this->assertTrue($before->equalTo($user->fresh()->onboarding_completed_at));
        $this->assertDatabaseMissing('user_onboarding_states', ['user_id' => $user->id]);
        $user->update(['academic_level' => 'PhD']);
        $this->actingAs($user->fresh())->get(route('research.create'))->assertOk()->assertSee('value="doctoral" selected', false);
    }

    public function test_onboarding_does_not_bypass_two_factor_challenge(): void
    {
        $user = User::factory()->withoutOnboarding()->create(['two_factor_secret' => 'test-secret']);
        $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('two-factor.challenge'));
        $this->post(route('onboarding.save', 'path'), ['path' => 'lecturer'])->assertRedirect(route('two-factor.challenge'));
        $this->assertDatabaseMissing('user_onboarding_states', ['user_id' => $user->id]);
    }
    public function test_other_supported_account_contexts_keep_neutral_roles(): void
    {
        \Illuminate\Support\Facades\Event::fake();
        $this->get(route('register'))->assertOk()->assertSee('Independent professional')->assertSee('Author or publisher')->assertSee('Other academic participant');
        $this->post(route('store-register'), ['account_type' => 'other', 'first_name' => 'Other', 'last_name' => 'Participant', 'username' => 'other-participant', 'email' => 'other@acadsyntra.test', 'password' => 'StudioExample123!', 'password_confirmation' => 'StudioExample123!', 'terms' => 1])->assertSessionHasNoErrors();
        $this->assertSame('member', User::where('email', 'other@acadsyntra.test')->firstOrFail()->role);
    }

}
