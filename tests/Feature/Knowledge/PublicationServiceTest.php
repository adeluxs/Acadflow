<?php

namespace Tests\Feature\Knowledge;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use App\Services\Knowledge\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_updates_reuse_the_shared_document_and_create_version_history(): void
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $creator = User::factory()->create(['university_id' => $university->id, 'department_id' => $department->id]);
        $service = app(PublicationService::class);

        $publication = $service->createDraft([
            'title' => 'Evidence-led study guide',
            'body' => '<p>Initial authorized content.</p>',
            'content_type' => 'study_guide',
            'visibility' => 'institution',
            'access_type' => 'free',
            'tags' => ['Evidence', 'Study'],
        ], $creator);

        $documentId = $publication->content_document_id;
        $service->updateDraft($publication, [
            'title' => 'Evidence-led study guide',
            'body' => '<p>Revised authorized content with a second version.</p>',
            'content_type' => 'study_guide',
            'visibility' => 'institution',
            'access_type' => 'free',
            'tags' => ['Evidence'],
        ], $creator);

        $this->assertSame($documentId, $publication->fresh()->content_document_id);
        $this->assertGreaterThanOrEqual(2, $publication->document->versions()->count());
        $this->assertDatabaseHas('knowledge_publications', ['id' => $publication->id, 'university_id' => $university->id]);
    }
}
