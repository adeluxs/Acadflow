<?php

namespace Tests\Feature;

use App\Models\KnowledgeFollow;
use App\Models\KnowledgePublication;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\University;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\KnowledgeNotificationService;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['notifications', 'academic_challenges', 'knowledge_read_to_earn'] as $feature) {
            FeatureAccessService::setStatus($feature, 'enabled');
        }
        $this->flush();
    }

    private function flush(): void
    {
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function draft(User $creator, string $title, string $visibility = 'public'): KnowledgePublication
    {
        $this->actingAs($creator);
        $type = DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id');
        $publication = app(PublicationService::class)->createDraft(['title' => $title, 'body' => '<p>A useful guide for students about '.$title.'.</p>',
            'publication_type_id' => $type, 'access_type' => 'free', 'visibility' => $visibility, 'excerpt' => 'Short summary of '.$title, 'ownership_declaration' => true], $creator);
        auth()->logout();

        return $publication;
    }

    private function followed(User $creator): KnowledgePublication
    {
        $this->draft($creator, 'Earlier guide');
        KnowledgePublication::withoutGlobalScopes()->where('creator_id', $creator->id)->update(['status' => 'published', 'published_at' => now()->subDays(30)]);

        return KnowledgePublication::withoutGlobalScopes()->where('creator_id', $creator->id)->firstOrFail();
    }

    public function test_followers_get_one_alert_in_app_and_email_when_a_creator_publishes(): void
    {
        $creator = User::factory()->create();
        $earlier = $this->followed($creator);
        $fan = User::factory()->create();
        $muted = User::factory()->create();
        NotificationSetting::create(['user_id' => $muted->id, 'knowledge_updates' => false]);

        $this->actingAs($fan)->get(route('knowledge.show', $earlier))->assertSee('+ Follow '.$creator->first_name);
        $this->post(route('knowledge.creators.follow', $creator))->assertSessionHas('success');
        $this->get(route('knowledge.show', $earlier))->assertSee('✓ Following '.$creator->first_name);
        $this->actingAs($muted)->post(route('knowledge.creators.follow', $creator));
        $this->assertSame(2, KnowledgeNotificationService::followerCount($creator->id));
        $this->actingAs($creator)->post(route('knowledge.creators.follow', $creator))->assertStatus(422);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $publication = $this->draft($creator, 'Brand new guide');
        app(ModerationService::class)->publish($publication, $admin);

        $alert = Notification::where('user_id', $fan->id)->where('type', 'knowledge_new_from_followed')->firstOrFail();
        $this->assertStringContainsString('Brand new guide', $alert->message);
        $this->assertTrue($alert->in_app_visible);
        $this->assertSame('knowledge', $alert->category);
        $this->assertTrue(NotificationLog::where('notification_id', $alert->id)->where('channel', 'email')->exists());
        $this->assertSame(0, Notification::where('user_id', $muted->id)->where('type', 'knowledge_new_from_followed')->count());
        $this->assertSame(0, Notification::where('user_id', $creator->id)->where('type', 'knowledge_new_from_followed')->count());

        // Re-approving later does not alert again.
        app(ModerationService::class)->publish($publication->fresh(), $admin);
        $this->assertSame(1, Notification::where('user_id', $fan->id)->where('type', 'knowledge_new_from_followed')->count());

        // Admin switch off.
        SettingService::set('knowledge_notify_followers_enabled', false, 'boolean');
        $this->flush();
        app(ModerationService::class)->publish($this->draft($creator, 'Quiet guide'), $admin);
        $this->assertSame(1, Notification::where('user_id', $fan->id)->where('type', 'knowledge_new_from_followed')->count());

        // Unfollow.
        $this->actingAs($fan)->post(route('knowledge.creators.follow', $creator));
        $this->assertSame(1, KnowledgeNotificationService::followerCount($creator->id));
    }

    public function test_institution_only_guides_alert_only_followers_at_that_institution(): void
    {
        [$uniA, $uniB] = University::factory()->count(2)->create()->all();
        $creator = User::factory()->create(['university_id' => $uniA->id]);
        $this->followed($creator);
        $sameSchool = User::factory()->create(['university_id' => $uniA->id]);
        $otherSchool = User::factory()->create(['university_id' => $uniB->id]);
        foreach ([$sameSchool, $otherSchool] as $fan) {
            KnowledgeFollow::create(['follower_id' => $fan->id, 'target_type' => 'creator', 'target_id' => $creator->id, 'created_at' => now()]);
        }
        app(ModerationService::class)->publish($this->draft($creator, 'Campus only guide', 'institution'), User::factory()->create(['role' => 'super_admin']));
        $this->assertSame([$sameSchool->id], Notification::where('type', 'knowledge_new_from_followed')->pluck('user_id')->all());
    }

    public function test_weekly_roundup_is_sent_once_per_week_and_respects_preferences(): void
    {
        $creator = User::factory()->create();
        foreach (['Guide one', 'Guide two', 'Guide three'] as $title) {
            $publication = $this->draft($creator, $title);
            $publication->forceFill(['status' => 'published', 'published_at' => now()->subDays(2)])->save();
        }
        $reader = User::factory()->create();
        $optedOut = User::factory()->create();
        NotificationSetting::create(['user_id' => $optedOut->id, 'knowledge_digest' => false]);
        $service = app(KnowledgeNotificationService::class);

        SettingService::set('knowledge_digest_day', now()->dayOfWeekIso === 1 ? 2 : 1, 'integer');
        $this->flush();
        $this->assertSame('not_due', app(KnowledgeNotificationService::class)->dispatchDigest()['status']);

        SettingService::set('knowledge_digest_day', now()->dayOfWeekIso, 'integer');
        SettingService::set('knowledge_digest_hour', now()->hour, 'integer');
        $this->flush();
        $this->artisan('knowledge:weekly-digest')->assertSuccessful();
        $digest = Notification::where('user_id', $reader->id)->where('type', 'knowledge_weekly_digest')->firstOrFail();
        $this->assertStringContainsString('3 new guides', $digest->title);
        $this->assertStringContainsString('Guide one', $digest->message);
        $this->assertSame(0, Notification::where('user_id', $optedOut->id)->where('type', 'knowledge_weekly_digest')->count());

        $this->assertSame('already_sent', app(KnowledgeNotificationService::class)->dispatchDigest()['status']);
        app(KnowledgeNotificationService::class)->dispatchDigest(true);
        $this->assertSame(1, Notification::where('user_id', $reader->id)->where('type', 'knowledge_weekly_digest')->count());

        SettingService::set('knowledge_digest_min_new', 5, 'integer');
        $this->flush();
        $this->assertSame('too_few', app(KnowledgeNotificationService::class)->dispatchDigest(true)['status']);
    }

    public function test_users_can_switch_knowledge_alerts_in_notification_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notifications.settings'))->assertOk()->assertSee('Knowledge Hub weekly roundup')->assertSee('Knowledge Hub: creators you follow');
        $this->put(route('notifications.settings.update'), ['knowledge_updates' => 0, 'knowledge_digest' => 1, 'in_app_enabled' => 1, 'academic_alerts' => 1])->assertSessionHasNoErrors();
        $this->assertFalse(NotificationSetting::where('user_id', $user->id)->value('knowledge_updates'));
        $this->assertTrue(NotificationSetting::where('user_id', $user->id)->value('knowledge_digest'));
    }
}
