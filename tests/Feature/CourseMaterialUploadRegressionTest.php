<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\LecturerCourseAssignment;
use App\Models\User;
use App\Services\IndependentAcademicContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseMaterialUploadRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_lecturer_can_upload_checked_enrollment_material_without_boolean_or_access_errors(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');
        config()->set('media.scanner', 'none');

        $context = app(IndependentAcademicContextService::class)->context();
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => null,
            'department_id' => null,
        ]);

        $course = Course::query()->create([
            'department_id' => $context['department']->id,
            'access_mode' => 'independent',
            'owner_user_id' => $lecturer->id,
            'code' => 'IND-MAT-101',
            'name' => 'Independent Material Test',
            'description' => 'Regression coverage for material upload.',
            'credit_hours' => 3,
            'level' => 'General',
            'semester' => 'Independent',
            'type' => 'elective',
            'pass_mark' => 40,
            'is_active' => true,
        ]);

        LecturerCourseAssignment::query()->create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $context['semester']->id,
            'is_coordinator' => true,
        ]);

        $response = $this->actingAs($lecturer)->post(route('lecturer.materials.store', $course), [
            'title' => 'Week One Lecture Note',
            'description' => 'A course material upload.',
            'type' => 'lecture_note',
            'file' => UploadedFile::fake()->create('week-one.pdf', 64, 'application/pdf'),
            // Browsers historically submitted checkbox values as "on" in this form.
            'requires_enrollment' => 'on',
            'is_visible' => 'on',
        ]);

        $response->assertSessionHasNoErrors();
        $material = CourseMaterial::query()->where('course_id', $course->id)->firstOrFail();
        $response->assertRedirect(route('materials.show', [$course, $material]));

        $this->assertTrue($material->requires_enrollment);
        $this->assertTrue($material->is_visible);
        $this->assertSame($context['semester']->id, $material->semester_id);
        $this->assertSame($lecturer->id, $material->uploaded_by);
    }

    public function test_unchecked_material_access_flags_are_saved_as_false(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');
        config()->set('media.scanner', 'none');

        $context = app(IndependentAcademicContextService::class)->context();
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
        ]);
        $course = Course::query()->create([
            'department_id' => $context['department']->id,
            'access_mode' => 'independent',
            'owner_user_id' => $lecturer->id,
            'code' => 'IND-MAT-102',
            'name' => 'Independent Material Flags',
            'credit_hours' => 3,
            'level' => 'General',
            'semester' => 'Independent',
            'type' => 'elective',
            'pass_mark' => 40,
            'is_active' => true,
        ]);
        LecturerCourseAssignment::query()->create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $context['semester']->id,
            'is_coordinator' => true,
        ]);

        $response = $this->actingAs($lecturer)->post(route('lecturer.materials.store', $course), [
            'title' => 'Private Draft',
            'type' => 'reading',
            'file' => UploadedFile::fake()->create('private.pdf', 64, 'application/pdf'),
            'is_public' => '0',
            'requires_enrollment' => '0',
            'is_visible' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $material = CourseMaterial::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertFalse($material->is_public);
        $this->assertFalse($material->requires_enrollment);
        $this->assertFalse($material->is_visible);
    }
}
