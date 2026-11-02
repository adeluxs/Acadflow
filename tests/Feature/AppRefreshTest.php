<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_page_clears_browser_site_data_but_keeps_the_session(): void
    {
        $this->get(route('app.refresh'))->assertOk()
            ->assertHeader('Clear-Site-Data', '"cache", "storage"')
            ->assertSee('AcadSyntra has been refreshed');

        // Signed-in users stay signed in: cookies are not part of the cleared data.
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('app.refresh'))->assertOk();
        $this->assertStringNotContainsString('cookies', $this->get(route('app.refresh'))->headers->get('Clear-Site-Data'));
        $this->get(route('dashboard'))->assertOk();
    }
}
