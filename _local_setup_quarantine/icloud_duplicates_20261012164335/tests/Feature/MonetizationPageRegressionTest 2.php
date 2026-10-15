<?php

namespace Tests\Feature;

use App\Models\CommercialAccount;
use App\Models\University;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationPageRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_monetization_page_renders_when_no_institution_currency_is_configured(): void
    {
        $this->withoutMiddleware();
        $admin = User::factory()->create(['role' => 'super_admin', 'account_type' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.monetization'))
            ->assertOk()
            ->assertSee('Monetization');
    }

    public function test_admin_monetization_page_uses_selected_institution_currency(): void
    {
        $this->withoutMiddleware();
        $admin = User::factory()->create(['role' => 'super_admin', 'account_type' => 'super_admin']);
        $university = University::factory()->create();
        SettingService::set('currency', 'USD', 'string', $university->id, $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.monetization', ['institution' => $university->id]))
            ->assertOk()
            ->assertSee('Commercial currency')
            ->assertSee('USD');
    }

    public function test_existing_commercial_account_currency_is_authoritative_for_setup(): void
    {
        $this->withoutMiddleware();
        $admin = User::factory()->create(['role' => 'super_admin', 'account_type' => 'super_admin']);
        $university = University::factory()->create();
        CommercialAccount::query()->create([
            'university_id' => $university->id,
            'name' => $university->name.' Commercial Account',
            'currency' => 'CAD',
            'prepaid_balance_minor' => 0,
            'status' => 'active',
            'metadata' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monetization', ['institution' => $university->id]))
            ->assertOk()
            ->assertSee('Commercial currency')
            ->assertSee('CAD');
    }
}
