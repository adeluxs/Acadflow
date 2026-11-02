<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiPromptVersion;
use App\Models\University;
use App\Models\User;
use App\Services\Ai\AiPromptService;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AcadSyntraBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_20_000005_rebrand_platform_as_acadsyntra.php');
    }

    public function test_rebrand_updates_default_settings_and_cached_tenant_fallbacks_without_resetting_customization(): void
    {
        $university = University::factory()->create(['name' => 'Custom Institution']);
        $fallbackInstitution = University::factory()->create();
        SettingService::set('site_name', 'AcadFlow', 'string');
        SettingService::set('site_logo', '/branding/acadflow-logo.png', 'string');
        SettingService::set('site_favicon', 'https://institution.test/custom-icon.png', 'string');
        SettingService::set('site_name', 'My Institution', 'string', $university->id);
        $this->assertSame('AcadFlow', SettingService::getGlobal('site_name'));
        $this->assertSame('AcadFlow', SettingService::get('site_name', null, $fallbackInstitution->id));

        $this->migration()->up();
        $this->assertSame('AcadSyntra', SettingService::getGlobal('site_name'));
        $this->assertSame('AcadSyntra', SettingService::get('site_name', null, $fallbackInstitution->id));
        $this->assertSame('/branding/acadsyntra-logo.png', SettingService::getGlobal('site_logo'));
        $this->assertSame('https://institution.test/custom-icon.png', SettingService::getGlobal('site_favicon'));
        $this->assertSame('My Institution', SettingService::get('site_name', null, $university->id));
        $this->assertSame('Custom Institution', $university->fresh()->name);
        $this->migration()->up();
        $this->assertSame('My Institution', SettingService::get('site_name', null, $university->id));
    }

    public function test_seeded_prompt_rebrand_retains_original_version_and_custom_prompts(): void
    {
        $legacy = 'You are AcadFlow AI Academic Assistant. Respect authorization, tenant boundaries, academic integrity, source grounding, and uncertainty.';
        $stock = AiPromptVersion::create(['feature' => 'brand_test', 'version' => 1, 'system_prompt' => $legacy, 'is_active' => true]);
        $custom = AiPromptVersion::create(['feature' => 'custom_brand_test', 'version' => 1, 'system_prompt' => 'Our own AcadFlow instruction, customized by an administrator.', 'is_active' => true]);
        $user = User::factory()->create(['first_name' => 'AcadFlow', 'last_name' => 'Researcher']);
        $userBefore = $user->fresh()->getRawOriginal();

        $this->migration()->up();
        $this->assertSame($legacy, $stock->fresh()->system_prompt);
        $this->assertFalse($stock->fresh()->is_active);
        $next = AiPromptVersion::where('feature', 'brand_test')->where('is_active', true)->firstOrFail();
        $this->assertSame(2, $next->version);
        $this->assertSame(str_replace('AcadFlow', 'AcadSyntra', $legacy), $next->system_prompt);
        $this->assertSame($custom->system_prompt, $custom->fresh()->system_prompt);
        $this->assertTrue($custom->fresh()->is_active);
        $this->assertSame($userBefore, $user->fresh()->getRawOriginal());
        $count = AiPromptVersion::count();
        $this->migration()->up();
        $this->assertSame($count, AiPromptVersion::count());
    }

    public function test_prompt_composition_rebrands_instructions_without_rewriting_source_context(): void
    {
        AiPromptVersion::create(['feature' => 'brand_context_test', 'version' => 1, 'system_prompt' => 'You are AcadFlow. Use authorized context.', 'user_template' => 'AcadFlow task: {{context_json}}', 'is_active' => true]);
        SettingService::set('ai_global_system_prompt', 'You are AcadFlow AI.', 'string');
        $result = app(AiPromptService::class)->enrich('brand_context_test', ['text' => 'A historical research paper about AcadFlow.'], null);
        $this->assertStringContainsString('You are AcadSyntra', $result['_prompt']['system_prompt']);
        $this->assertStringNotContainsString('AcadFlow', $result['_prompt']['system_prompt']);
        $this->assertStringContainsString('A historical research paper about AcadFlow.', $result['_prompt']['user_prompt']);
        $this->assertStringStartsWith('AcadSyntra task:', $result['_prompt']['user_prompt']);
    }

    public function test_visible_pages_emails_and_manifest_have_current_identity_and_working_icons(): void
    {
        SettingService::set('site_name', 'AcadSyntra', 'string');
        foreach (['/', '/features', '/about', '/contact', '/faqs', '/privacy', '/login', '/register', '/forgot-password'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $visible = html_entity_decode(strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', '', $html)));
            $this->assertStringContainsString('AcadSyntra', $visible, $path);
            $this->assertDoesNotMatchRegularExpression('/\b(AcadFlow|UniFlow|ACADFLOW)\b/', $visible, $path);
        }
        FeatureAccessService::setStatus('pwa_enabled', 'enabled');
        $manifest = $this->get('/manifest.webmanifest')->assertOk()->json();
        $this->assertSame('AcadSyntra', $manifest['name']);
        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);
            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], $width.'x'.$height);
        }
        foreach (['emails.notification', 'emails.plain-text'] as $template) {
            $email = view($template, ['title' => 'A workspace update', 'body' => 'Your next task is ready.', 'data' => []])->render();
            $this->assertStringContainsString('AcadSyntra Notification', $email);
            $this->assertStringNotContainsString('AcadFlow', $email);
        }
    }

    public function test_legacy_asset_urls_contain_new_artwork_and_technical_contracts_are_preserved(): void
    {
        foreach (['mark', 'logo', 'icon-192', 'icon-512'] as $asset) {
            $this->assertSame(hash_file('sha256', public_path('branding/acadsyntra-'.$asset.'.png')), hash_file('sha256', public_path('branding/acadflow-'.$asset.'.png')));
        }
        $this->assertStringContainsString('acadflow_push_account', file_get_contents(resource_path('js/browser-push.js')));
        $this->assertStringContainsString('X-AcadFlow-Request-Id', file_get_contents(app_path('Ai/Providers/ExternalProvider.php')));
        $this->assertStringContainsString('acadflow.research.archive.v1', file_get_contents(app_path('Services/ResearchArchiveService.php')));
        $this->artisan('acadflow:sync-nigeria-catalog', ['--templates-only' => true, '--no-templates' => true])->assertFailed();
    }
}
