<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StylesheetNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_layout_uses_a_worker_that_bypasses_asset_caching(): void
    {
        Queue::fake();
        $this->app->detectEnvironment(fn () => 'local');
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user)->get(route('admin.users'))->assertOk()
            ->assertSee('content="/serviceworker.js?dev=1"', false)
            ->assertSee("updateViaCache: 'none'", false)
            ->assertSee("navigator.serviceWorker.addEventListener('controllerchange'", false);
    }

    public function test_production_keeps_the_normal_worker_and_the_same_notification_registration(): void
    {
        Queue::fake();
        $this->app->detectEnvironment(fn () => 'production');
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user)->get(route('admin.users'))->assertOk()
            ->assertSee('content="/serviceworker.js"', false)
            ->assertDontSee('/serviceworker.js?dev=1', false);
    }
}
