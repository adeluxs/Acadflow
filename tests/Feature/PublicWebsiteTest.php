<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\NotifyPublicContact;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\PublicWebsite;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
        Bus::fake([NotifyPublicContact::class]);
    }

    public static function publicPages(): array
    {
        return array_map(fn ($path) => [$path], ['/', '/features', '/how-it-works', '/about', '/contact', '/faqs', '/help', '/privacy', '/terms', '/cookies', '/disclaimer', '/information/documentation', '/information/security', '/information/about']);
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_render_navigation_and_search_metadata(string $path): void
    {
        $response = $this->get($path)->assertOk();
        $response->assertSee('name="description"', false)->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)->assertSee('application/ld+json', false)
            ->assertSee('Skip to content')->assertSee('mobile-navigation')->assertSee('Cookie policy');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_canonical_and_sitemap_use_working_public_urls(): void
    {
        $this->get('/information/about')->assertSee('href="'.route('public.about').'"', false);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $document = simplexml_load_string($xml);
        $this->assertNotFalse($document);
        $urls = array_map('strval', iterator_to_array($document->xpath('//*[local-name()="loc"]')));
        $this->assertCount(count(array_unique($urls)), $urls);
        foreach (PublicWebsite::PATHS as $slug => $path) $this->assertContains(route('public.'.$slug), $urls);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.route('public.sitemap'), false)->assertSee('Disallow: /admin/');
    }

    public function test_signed_in_users_keep_dashboard_link_on_support_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
        $this->get('/about')->assertOk()->assertSee('Your dashboard');
    }

    private function form(array $replace = []): array
    {
        $token = Str::random(48);
        $this->withSession(['public_contact_tokens' => [$token => time() - 5]]);
        return array_replace(['name' => 'Test visitor', 'email' => 'visitor@example.test', 'topic' => 'product',
            'message' => 'Please explain the available institution onboarding options.',
            'privacy_acknowledged' => '1', 'website' => '', 'form_token' => $token], $replace);
    }

    public function test_valid_contact_is_durably_saved_and_alert_is_queued(): void
    {
        $data = $this->form();
        $this->post('/contact', $data)->assertRedirect(route('public.contact'))->assertSessionHas('contact_reference');
        $this->assertDatabaseHas('contact_messages', ['email' => $data['email'], 'message' => $data['message'], 'status' => 'open']);
        $message = ContactMessage::firstOrFail();
        Bus::assertDispatched(NotifyPublicContact::class, fn ($job) => $job->contactId === $message->id);
        $this->get('/contact')->assertSee('Your message has a place.')->assertSee($message->reference);
        $this->post('/contact', $data)->assertSessionHasErrors('form_token');
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_validation_and_honeypot_reject_invalid_messages(): void
    {
        $this->post('/contact', $this->form(['email' => "bad\nBcc: spam@test.test", 'message' => 'tiny', 'privacy_acknowledged' => '0', 'website' => 'bot']))
            ->assertSessionHasErrors(['email', 'message', 'privacy_acknowledged', 'website']);
        $this->assertDatabaseCount('contact_messages', 0);
        Bus::assertNotDispatched(NotifyPublicContact::class);
    }

    public function test_form_requires_session_token_and_time_to_complete(): void
    {
        $data = $this->form();
        $this->withSession(['public_contact_tokens' => [$data['form_token'] => time()]])
            ->post('/contact', $data)->assertSessionHasErrors('form_token');
        $this->withSession(['public_contact_tokens' => [$data['form_token'] => time() - 86401]])
            ->post('/contact', $data)->assertSessionHasErrors('form_token');
        $this->post('/contact', $this->form(['form_token' => Str::random(48)]))->assertSessionHasErrors('form_token');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_form_is_rate_limited_without_dropping_existing_enquiries(): void
    {
        for ($i = 0; $i < 3; $i++) $this->post('/contact', $this->form())->assertRedirect();
        $this->postJson('/contact', $this->form())->assertStatus(429);
        $this->assertDatabaseCount('contact_messages', 3);
    }

    public function test_queue_failure_does_not_lose_the_contact_message(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->post('/contact', $this->form())->assertSessionHas('contact_reference');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_contact_inbox_is_restricted_and_escapes_visitor_content(): void
    {
        $contact = ContactMessage::create(['reference' => Str::uuid(), 'name' => '<script>alert(1)</script>', 'email' => 'visitor@example.test', 'topic' => 'product', 'message' => '<img src=x onerror=alert(1)>']);
        $this->get('/admin/contact')->assertRedirect(route('login'));
        foreach (['student', 'lecturer', 'university_admin', 'department_admin', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role, 'onboarding_completed_at' => now(), 'email_verified_at' => now()]);
            $this->actingAs($user)->get('/admin/contact')->assertForbidden();
            $this->patch(route('admin.contact.update', $contact), ['status' => 'closed'])->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'super_admin', 'onboarding_completed_at' => now(), 'email_verified_at' => now()]);
        $this->actingAs($admin)->get('/admin/contact')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<img src=x', false);
        $this->patch(route('admin.contact.update', $contact), ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $contact->fresh()->status);
        $this->get('/admin/contact?status=closed')->assertOk()->assertSee($contact->email);
    }

    public function test_seeded_example_support_addresses_are_not_published(): void
    {
        foreach (['support@example.com', 'support@uniflow.edu', 'support@other.invalid'] as $email) {
            SettingService::set('support_email', $email, 'string');
            $this->assertNull(PublicWebsite::supportEmail());
            $this->get('/contact')->assertDontSee('mailto:'.$email, false);
        }
    }

    public function test_alert_uses_configured_mail_and_contains_no_visitor_text(): void
    {
        SettingService::set('support_email', 'support@institution.edu.ng', 'string');
        $contact = ContactMessage::create(['reference' => Str::uuid(), 'name' => 'Private visitor', 'email' => 'private@example.test', 'topic' => 'privacy', 'message' => 'Sensitive context kept in the inbox.']);
        (new NotifyPublicContact($contact->id))->handle();
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $email = $messages->last()->getOriginalMessage();
        $this->assertSame('support@institution.edu.ng', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString((string) $contact->reference, $email->getTextBody());
        $this->assertStringNotContainsString($contact->message, $email->getTextBody());
        $this->assertStringNotContainsString($contact->email, $email->getTextBody());
    }
}
