<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\KnowledgePublication;
use App\Models\User;
use App\Policies\CourseMaterialPolicy;
use App\Policies\KnowledgePublicationPolicy;
use Tests\TestCase;

class AccessRegressionPolicyTest extends TestCase
{
    public function test_material_uploader_can_open_own_hidden_material(): void
    {
        $lecturer = new User();
        $lecturer->id = 42;

        $course = new Course();
        $course->id = 9;

        $material = new CourseMaterial();
        $material->uploaded_by = 42;
        $material->is_visible = false;
        $material->is_public = false;
        $material->requires_enrollment = true;
        $material->setRelation('course', $course);

        $this->assertTrue((new CourseMaterialPolicy())->view($lecturer, $material));
    }

    public function test_publication_creator_can_always_open_own_publication_record(): void
    {
        $creator = new User();
        $creator->id = 73;

        foreach (['draft', 'pending_review', 'published', 'changes_requested', 'rejected'] as $status) {
            $publication = new KnowledgePublication();
            $publication->creator_id = 73;
            $publication->status = $status;
            $publication->visibility = 'private';
            $publication->access_type = 'premium';

            $this->assertTrue((new KnowledgePublicationPolicy())->view($creator, $publication), "Creator cannot view {$status} publication");
        }
    }

    public function test_creator_edit_and_submit_are_workflow_status_aware(): void
    {
        $creator = new User();
        $creator->id = 73;
        $policy = new KnowledgePublicationPolicy();

        foreach (['draft', 'changes_requested', 'rejected'] as $status) {
            $publication = new KnowledgePublication();
            $publication->creator_id = 73;
            $publication->status = $status;
            $this->assertTrue($policy->update($creator, $publication));
            $this->assertTrue($policy->submit($creator, $publication));
        }

        foreach (['pending_review', 'published'] as $status) {
            $publication = new KnowledgePublication();
            $publication->creator_id = 73;
            $publication->status = $status;
            $this->assertFalse($policy->update($creator, $publication));
            $this->assertFalse($policy->submit($creator, $publication));
            $this->assertTrue($policy->view($creator, $publication));
        }
    }
}
