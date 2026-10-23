<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Ai\Support\TextExtractor;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\PracticeQuiz;
use App\Models\StudentStudySession;
use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudySourceService
{
    public const TEXT_LIMIT = 20000;

    public function __construct(private readonly TextExtractor $extractor) {}

    public function authorizeSession(User $user, StudentStudySession $session): void
    {
        abort_unless((int) $session->user_id === (int) $user->id, 404);
        $session->loadMissing('course.department.faculty', 'material', 'note');
        $this->authorizeCourse($user, $session->course);
        if ($session->material_id) {
            abort_unless($session->material && (int) $session->material->course_id === (int) $session->course_id, 404);
            abort_unless(FeatureAccessService::canAccessFeature('course_materials', $user), 403);
            Gate::forUser($user)->authorize('view', $session->material);
        } else {
            abort_unless($session->note && (int) $session->note->user_id === (int) $user->id && (int) $session->note->course_id === (int) $session->course_id, 404);
        }
    }

    public function authorizeCourse(User $user, Course $course): void
    {
        $course->loadMissing('department.faculty');
        abort_unless($user->canAccessCourse($course), 403, 'This course is no longer available to your account.');
    }

    public function authorizeQuiz(User $user, PracticeQuiz $quiz): void
    {
        abort_unless((int) $quiz->user_id === (int) $user->id, 404);
        $this->authorizeCourse($user, $quiz->course);
        if ($quiz->study_session_id) {
            abort_unless($quiz->studySession && (int) $quiz->studySession->course_id === (int) $quiz->course_id, 404);
            $this->authorizeSession($user, $quiz->studySession);
        } elseif ($quiz->material_id) {
            abort_unless($quiz->material && (int) $quiz->material->course_id === (int) $quiz->course_id, 404);
            abort_unless(FeatureAccessService::canAccessFeature('course_materials', $user), 403);
            Gate::forUser($user)->authorize('view', $quiz->material);
        }
    }

    public function sessionText(User $user, StudentStudySession $session): string
    {
        $this->authorizeSession($user, $session);

        return $session->material ? $this->materialText($user, $session->material) : $this->plain((string) $session->note->content);
    }

    public function materialText(User $user, CourseMaterial $material): string
    {
        $this->authorizeMaterial($user, $material);
        if ($material->hasContent()) {
            return $this->plain($material->content);
        }
        $material->loadMissing('mediaAsset');
        if (! $material->hasFile() || ($material->mediaAsset && ! in_array($material->mediaAsset->scan_status, ['clean', 'skipped'], true))) {
            return '';
        }
        $storage = Storage::disk($material->disk ?: config('filesystems.default'));
        if (! $storage->exists($material->file_path) || $storage->size($material->file_path) > 5 * 1024 * 1024) {
            return '';
        }

        return $this->readable($this->extractor->fromPath($material->file_path, $material->disk));
    }

    public function authorizeMaterial(User $user, CourseMaterial $material): void
    {
        $this->authorizeCourse($user, $material->course);
        Gate::forUser($user)->authorize('view', $material);
        abort_unless(FeatureAccessService::canAccessFeature('course_materials', $user), 403);
    }

    public function plain(string $html): string
    {
        $html = preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?\s*>/i', "\n", $html) ?? $html;

        return mb_substr(trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, self::TEXT_LIMIT);
    }

    public function readable(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8') || str_starts_with(ltrim($text), '%PDF') || str_starts_with($text, 'PK')) {
            return '';
        }

        return mb_substr(trim($text), 0, self::TEXT_LIMIT);
    }

    public function excerpt(string $text, string $selection): string
    {
        $selection = trim($selection);
        $normalize = fn ($value) => preg_replace('/\s+/u', ' ', trim($value));
        if ($selection !== '' && ! str_contains($normalize($text), $normalize($selection))) {
            throw ValidationException::withMessages(['selected_text' => 'Select a passage from the displayed source text.']);
        }

        return $selection;
    }
}
