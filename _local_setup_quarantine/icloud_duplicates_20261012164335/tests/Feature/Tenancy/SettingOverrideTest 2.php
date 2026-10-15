<?php

namespace Tests\Feature\Tenancy;

use App\Models\University;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_university_override_does_not_replace_global_setting(): void
    {
        $first = University::factory()->create();
        $second = University::factory()->create();

        SettingService::set('ai_daily_request_limit', 1000, 'integer');
        SettingService::set('ai_daily_request_limit', 250, 'integer', $first->id);

        $this->assertSame(250, SettingService::get('ai_daily_request_limit', 0, $first->id));
        $this->assertSame(1000, SettingService::get('ai_daily_request_limit', 0, $second->id));
        $this->assertSame(1000, SettingService::get('ai_daily_request_limit', 0, null));
    }
}
