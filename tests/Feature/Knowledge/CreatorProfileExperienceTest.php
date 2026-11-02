<?php

namespace Tests\Feature\Knowledge;

use App\Models\ContentDocument;
use App\Models\Department;
use App\Models\KnowledgePublication;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Models\VerificationRequest;
use App\Services\FeatureAccessService;
use App\Services\Reputation\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreatorProfileExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        FeatureAccessService::setStatus('knowledge_hub', 'enabled');
    }

    public function test_sidebar_has_one_profile_link_for_each_normal_account_role(): void
    {
        foreach (['student', 'lecturer', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            $html = view('partials.sidebar')->render();
            $this->assertSame(1, substr_count($html, '>My Profile</span>'), $role.' should have one profile link.');
        }
    }

    public function test_editor_uses_existing_identity_and_shows_only_its_own_bounded_verification_history(): void
    {
        $user = User::factory()->member()->create();
        $user->creatorProfile()->create(['headline' => 'Current headline', 'biography' => 'Current introduction', 'is_public' => false]);
        VerificationRequest::create(['user_id' => User::factory()->create()->id, 'verification_type' => 'researcher', 'status' => 'rejected', 'review_note' => 'Other user private note']);
        foreach (range(1, 8) as $number) {
            VerificationRequest::create(['user_id' => $user->id, 'verification_type' => 'researcher', 'status' => 'rejected', 'review_note' => 'Review '.$number]);
        }
        $this->actingAs($user)->get(route('knowledge.creator.edit'))->assertOk()->assertSee('Reader preview')
            ->assertSee('Current headline')->assertSee('Save creator profile')->assertSee('Add and save an ORCID')
            ->assertDontSee('Other user private note')->assertDontSee('value="university"', false)
            ->assertDontSee('name="personalized_recommendations"', false)
            ->assertViewHas('verificationRequests', fn ($rows) => $rows->count() === 5);
        Http::assertNothingSent();
    }

    public function test_saves_preserve_unsubmitted_profile_data_and_explicitly_disable_public_visibility(): void
    {
        $user = User::factory()->member()->create(['research_interests' => ['Preserved research']]);
        $profile = $user->creatorProfile()->create(['headline' => 'Existing headline', 'biography' => 'Retained biography', 'position' => 'Professor',
            'is_public' => true, 'verification_status' => 'approved', 'social_links' => ['other' => 'https://example.org'],
            'external_profiles' => ['source' => ['name' => 'Preserved source']],
            'privacy_settings' => ['show_institution' => true, 'show_department' => true, 'show_impact' => true, 'personalized_recommendations' => false, 'custom' => true]]);
        $other = User::factory()->create();
        $this->actingAs($user)->put(route('knowledge.creator.update'), ['headline' => 'Revised headline', 'expertise' => 'Research methods, Education, Research methods',
            'is_public' => 0, 'show_institution' => 0, 'show_department' => 0, 'show_impact' => 0, 'user_id' => $other->id, 'verification_status' => 'unverified'])
            ->assertRedirect(route('knowledge.creator.edit'))->assertSessionHasNoErrors();
        $profile->refresh();
        $this->assertFalse($profile->is_public);
        $this->assertFalse($profile->privacy_settings['show_impact']);
        $this->assertFalse($profile->privacy_settings['personalized_recommendations']);
        $this->assertTrue($profile->privacy_settings['custom']);
        $this->assertSame('Retained biography', $profile->biography);
        $this->assertSame('Professor', $profile->position);
        $this->assertSame('approved', $profile->verification_status);
        $this->assertSame(['Research methods', 'Education'], $profile->expertise);
        $this->assertSame(['other' => 'https://example.org'], $profile->social_links);
        $this->assertSame(['source' => ['name' => 'Preserved source']], $profile->external_profiles);
        $this->assertSame(['Preserved research'], $user->fresh()->research_interests);
        $this->assertFalse($other->creatorProfile()->exists());
        $this->assertDatabaseCount('creator_profiles', 1);
    }

    public function test_orcid_change_clears_only_old_import_provenance_and_unsafe_website_is_rejected(): void
    {
        $user = User::factory()->member()->create();
        $profile = $user->creatorProfile()->create(['orcid' => '0000-0001-0002-0003', 'orcid_synced_at' => now(), 'external_profiles' => ['orcid' => ['name' => 'Previous identity'], 'other' => ['name' => 'Keep']]]);
        $this->actingAs($user)->put(route('knowledge.creator.update'), ['orcid' => 'https://orcid.org/0000-0002-1825-0097', 'website' => 'https://example.org'])
            ->assertSessionHasNoErrors();
        $this->assertSame('0000-0002-1825-0097', $profile->fresh()->orcid);
        $this->assertNull($profile->fresh()->orcid_synced_at);
        $this->assertSame(['other' => ['name' => 'Keep']], $profile->fresh()->external_profiles);
        $this->put(route('knowledge.creator.update'), ['website' => 'javascript:alert(1)'])->assertSessionHasErrors('website');
        $this->assertSame('https://example.org', $profile->fresh()->website);
        $this->putJson(route('knowledge.creator.update'), ['orcid' => ['invalid'], 'expertise' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors(['orcid', 'expertise']);
        Http::assertNothingSent();
    }

    public function test_new_profile_is_private_until_explicitly_published_and_can_be_previewed_by_owner(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user)->put(route('knowledge.creator.update'), ['headline' => 'My introduction'])->assertSessionHasNoErrors();
        $this->assertFalse($user->creatorProfile()->sole()->is_public);
        $this->get(route('knowledge.creator', $user))->assertOk()->assertSee('This creator page is private');
        $this->actingAs(User::factory()->member()->create())->get(route('knowledge.creator', $user))->assertNotFound();
        $this->actingAs($user)->put(route('knowledge.creator.update'), ['is_public' => 1])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->member()->create())->get(route('knowledge.creator', $user))->assertOk()->assertSee('My introduction');
    }

    public function test_creator_page_honours_affiliation_and_impact_privacy_without_recalculating_hidden_metrics(): void
    {
        $department = Department::factory()->create(['name' => 'Private department']);
        $creator = User::factory()->member()->create(['university_id' => $department->faculty->university_id, 'department_id' => $department->id]);
        $profile = $creator->creatorProfile()->create(['is_public' => true, 'headline' => 'Public headline', 'expertise' => ['Evidence synthesis'],
            'website' => 'https://example.org/portfolio', 'privacy_settings' => ['show_institution' => false, 'show_department' => false, 'show_impact' => false]]);
        $this->mock(ReputationService::class)->shouldNotReceive('recalculate');
        $this->get(route('knowledge.creator', $creator))->assertOk()->assertSee('Public headline')->assertSee('Evidence synthesis')->assertSee('https://example.org/portfolio')
            ->assertDontSee('Private department')->assertDontSee($department->faculty->university->name)->assertDontSee('Reputation & impact');
        $profile->update(['privacy_settings' => ['show_institution' => true, 'show_department' => true, 'show_impact' => false]]);
        $this->get(route('knowledge.creator', $creator))->assertOk()->assertSee('Private department')->assertSee($department->faculty->university->name);
        $this->assertFalse($creator->reputationProfile()->exists());
    }

    public function test_public_profile_lists_only_public_already_published_work_with_bounded_pagination(): void
    {
        $creator = User::factory()->member()->create();
        $creator->creatorProfile()->create(['is_public' => true, 'privacy_settings' => ['show_impact' => false]]);
        foreach (range(1, 14) as $number) {
            $this->publication($creator, ['title' => 'Public work '.$number]);
        }
        $this->publication($creator, ['title' => 'Private work title', 'visibility' => 'private']);
        $this->publication($creator, ['title' => 'Institution work title', 'visibility' => 'institution']);
        $this->publication($creator, ['title' => 'Future work title', 'published_at' => now()->addDay()]);
        $this->publication($creator, ['title' => 'Draft work title', 'status' => 'draft']);
        $this->get(route('knowledge.creator', $creator))->assertOk()->assertViewHas('publications', fn ($rows) => $rows->count() === 12 && $rows->total() === 14)
            ->assertDontSee('Private work title')->assertDontSee('Institution work title')->assertDontSee('Future work title')->assertDontSee('Draft work title');
        $this->get(route('knowledge.creator', ['creator' => $creator, 'page' => 2]))->assertOk()->assertViewHas('publications', fn ($rows) => $rows->count() === 2);
    }

    public function test_verification_keeps_one_pending_request_and_cannot_be_self_approved(): void
    {
        $user = User::factory()->member()->create();
        $payload = ['verification_type' => 'researcher', 'statement' => 'Please review my research role and the evidence in my public publications.'];
        $this->actingAs($user)->post(route('knowledge.verification.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('knowledge.verification.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, $user->verificationRequests()->count());
        $this->assertSame('pending', $user->verificationRequests()->sole()->status);
        $this->assertSame('member', $user->fresh()->role);
        $this->get(route('knowledge.creator.edit'))->assertOk()->assertSee('Your request is awaiting review');
        $this->post(route('knowledge.verification.store'), ['verification_type' => 'researcher', 'statement' => ''])->assertSessionHasErrors('statement');
        $this->post(route('knowledge.verification.review', $user->verificationRequests()->sole()), ['decision' => 'approve'])->assertForbidden();
    }

    public function test_editor_uses_personal_identity_in_a_different_approved_organisation_workspace(): void
    {
        $personal = Department::factory()->create(['name' => 'Personal department']);
        $other = Department::factory()->create(['name' => 'Selected workspace', 'adoption_status' => 'independent_active']);
        $user = User::factory()->member()->create(['university_id' => $personal->faculty->university_id, 'department_id' => $personal->id]);
        $membership = UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $other->faculty->university_id, 'faculty_id' => $other->faculty_id, 'department_id' => $other->id, 'affiliation_type' => 'lecturer', 'status' => 'verified']);
        $this->actingAs($user)->withSession(['academic_affiliation_id' => $membership->id])->get(route('knowledge.creator.edit'))->assertOk()
            ->assertViewHas('creator', fn ($creator) => $creator->department_id === $personal->id);
        $this->assertSame($personal->id, $user->fresh()->department_id);
    }

    public function test_hidden_impact_adds_no_reputation_or_tag_queries_as_publication_volume_grows(): void
    {
        $creator = User::factory()->member()->create();
        $creator->creatorProfile()->create(['is_public' => true, 'privacy_settings' => ['show_impact' => false]]);
        foreach (range(1, 15) as $number) {
            $this->publication($creator, ['title' => 'Study '.$number]);
        }
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->get(route('knowledge.creator', $creator))->assertOk();
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'reputation_profiles') || str_contains($sql, 'knowledge_tags')));
    }

    public function test_orcid_import_is_explicit_and_preserves_the_curated_biography(): void
    {
        $user = User::factory()->member()->create();
        $profile = $user->creatorProfile()->create(['orcid' => '0000-0002-1825-0097', 'biography' => 'My curated introduction']);
        Http::fake(['*orcid.org/*' => Http::response(['person' => ['name' => ['given-names' => ['value' => 'Imported'], 'family-name' => ['value' => 'Name']], 'biography' => ['content' => 'External biography']], 'activities-summary' => ['works' => ['group' => [['id' => 1]]]]])]);
        $this->actingAs($user)->post(route('knowledge.creator.orcid.sync'))->assertSessionHasNoErrors();
        $this->assertSame('My curated introduction', $profile->fresh()->biography);
        $this->assertSame('Imported Name', $profile->fresh()->external_profiles['orcid']['name']);
        $this->assertSame(1, $profile->fresh()->external_profiles['orcid']['works_count']);
        $this->assertNotNull($profile->fresh()->orcid_synced_at);
        Http::assertSentCount(1);
    }

    public function test_expertise_validation_explains_the_limit_and_new_partial_privacy_does_not_enable_impact(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user)->put(route('knowledge.creator.update'), ['expertise' => implode(', ', array_map(fn ($n) => 'Topic '.$n, range(1, 31)))])
            ->assertSessionHasErrors('expertise');
        $this->put(route('knowledge.creator.update'), ['headline' => 'Researcher', 'show_institution' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($user->creatorProfile()->sole()->privacy_settings['show_impact']);
    }

    private function publication(User $creator, array $attributes): KnowledgePublication
    {
        $document = ContentDocument::create(['owner_id' => $creator->id, 'title' => $attributes['title'], 'document_type' => 'publication', 'editor_mode' => 'rich', 'body' => '<p>Saved publication content.</p>', 'status' => 'published', 'visibility' => $attributes['visibility'] ?? 'public']);

        return KnowledgePublication::create(array_merge(['content_document_id' => $document->id, 'creator_id' => $creator->id, 'title' => 'Public work', 'content_type' => 'article',
            'status' => 'published', 'visibility' => 'public', 'access_type' => 'free', 'published_at' => now()->subDay()], $attributes));
    }
}
