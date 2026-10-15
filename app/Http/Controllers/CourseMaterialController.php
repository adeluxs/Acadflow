<?php

namespace App\Http\Controllers;

use App\Events\NewMaterialUploaded;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\PdfService;
use App\Services\SettingService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseMaterialController extends Controller
{
    public function __construct(
        private PdfService $pdfService,
        private MediaSecurityService $mediaSecurityService,
        private AcademicContextService $academicContext,
        private SafeFileDeliveryService $fileDelivery,
    ) {}

    /**
     * List all materials visible to the authenticated user.
     */
    public function all()
    {
        $user = Auth::user();
        $query = CourseMaterial::query()
            ->with(['course.department.faculty', 'uploader'])
            ->where('is_visible', true)
            ->latest('published_at');

        if ($user->isStudent()) {
            $courseIds = $user->enrollments()
                ->where('status', 'enrolled')
                ->pluck('course_id');

            $query->where(function ($q) use ($courseIds) {
                $q->where('is_public', true)
                    ->orWhereIn('course_id', $courseIds);
            });
        } elseif ($user->isLecturer()) {
            $courseIds = $user->lecturerAssignments()->pluck('course_id');
            $query->whereIn('course_id', $courseIds);
        } elseif ($user->isDepartmentAdmin()) {
            $query->whereHas('course', fn ($q) => $q->where('department_id', $user->department_id));
        } elseif ($user->isUniversityAdmin()) {
            $query->whereHas('course.department.faculty', fn ($q) => $q->where('university_id', $user->university_id));
        }

        $materials = $query->paginate(20);

        return view('materials.all', compact('materials'));
    }

    /**
     * List materials for a course
     */
    public function index(Course $course)
    {
        $this->authorize('view', $course);
        $user = Auth::user();
        $semester = $this->academicContext->activeSemesterForCourse($course);

        $query = $course->materials()
            ->where('semester_id', $semester?->id)
            ->with('uploader')
            ->orderBy('topic')
            ->orderBy('week_number')
            ->orderBy('sequence_order')
            ->orderBy('created_at', 'desc');

        // Students only see published/visible material. Authorized lecturers
        // and admins can see hidden material so they can manage their own work.
        if ($user->isStudent() && ! $user->isAdmin()) {
            $query->where('is_visible', true)
                ->where(function ($q) use ($course, $user) {
                $q->where('is_public', true)
                    ->orWhereHas('course.enrollments', function ($q2) use ($course, $user) {
                        $q2->where('course_id', $course->id)
                            ->where('user_id', $user->id)
                            ->where('status', 'enrolled');
                    });
            });
        }

        $materials = $query->paginate(20);

        // Group by topic/week
        $grouped = $materials->getCollection()->groupBy(function ($item) {
            if ($item->topic) {
                return 'topic_'.$item->topic;
            } elseif ($item->week_number) {
                return 'week_'.$item->week_number;
            }

            return 'other';
        });

        return view('materials.index', compact('course', 'materials', 'grouped'));
    }

     

    //create mateial page for lecturer
    public function create(Course $course)
    {
        $this->authorize('create', $course);

        return view('materials.lecturer-create', compact('course'));
    }





   
    /**
     * Upload material (lecturer/admin)
     */
   
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();

        $this->authorize('create', $course);

        // Course access has already been authorized above. Resolve upload limits and
        // semester context from the course itself so independent classrooms keep
        // working even though their lecturer intentionally has no institution on
        // their public profile.
        $course->loadMissing('department.faculty');
        $courseUniversityId = $course->department?->faculty?->university_id;
        $maxFileSizeBytes = SettingService::getMaxUploadSize($courseUniversityId);
        $maxFileSizeKb = intdiv($maxFileSizeBytes, 1024);
        $allowedMimes = implode(',', SettingService::getAllowedExtensions($courseUniversityId));

        // Native HTML checkboxes submit "on" when checked and nothing when
        // unchecked. Normalize them before Laravel's boolean validation so both
        // browser forms and API-style 0/1 values behave consistently.
        $this->normalizeMaterialBooleans($request, [
            'is_public' => false,
            'requires_enrollment' => true,
            'is_visible' => true,
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'type' => 'required|in:lecture_note,slides,reading,video,assignment,exam,reference,other',
            'file' => 'nullable|file|mimes:'.$allowedMimes.'|max:'.$maxFileSizeKb,
            'content' => 'nullable|string|max:200000',
            'topic' => 'nullable|string|max:255',
            'week_number' => 'nullable|integer|min:1|max:20',
            'sequence_order' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
            'requires_enrollment' => 'boolean',
            'is_visible' => 'boolean',
        ]);

        $file = $request->file('file');
        // Authored content is untrusted HTML: sanitize before persisting so the
        // stored value is always safe to render later without extra escaping.
        $cleanContent = HtmlSanitizer::clean((string) ($validated['content'] ?? ''));
        $hasContent = trim(strip_tags($cleanContent)) !== '';

        if ($file === null && ! $hasContent) {
            throw ValidationException::withMessages([
                'content' => 'Provide an uploaded file or write the material content directly.',
            ]);
        }

        $semester = $this->academicContext->requireActiveSemesterForCourse($course);

        $material = DB::transaction(function () use ($request, $file, $user, $course, $semester, $validated, $cleanContent, $hasContent): CourseMaterial {
            $asset = null;
            if ($file !== null) {
                $asset = $this->mediaSecurityService->store($file, $user, $course, ($validated['is_public'] ?? false) ? 'public' : 'institution', [
                    'purpose' => 'course_material',
                    'course_uuid' => $course->uuid,
                    'semester_id' => $semester->id,
                ]);
                if (! in_array($asset->scan_status, ['clean', 'skipped'], true)) {
                    throw ValidationException::withMessages(['file' => 'The material did not pass the configured security scan.']);
                }
            }

            $material = CourseMaterial::create([
                'uuid' => Str::uuid(),
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'uploaded_by' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'file_name' => $asset?->original_name,
                'file_path' => $asset?->path,
                'disk' => $asset?->disk,
                'media_asset_id' => $asset?->id,
                'mime_type' => $asset?->mime_type,
                'file_size' => $asset?->size_bytes,
                'content' => $hasContent ? $cleanContent : null,
                'content_format' => 'html',
                'content_updated_at' => $hasContent ? now() : null,
                'source' => $asset !== null ? 'upload' : ($request->boolean('ai_assisted') ? 'ai_assisted' : 'authored'),
                'topic' => $validated['topic'] ?? null,
                'week_number' => $validated['week_number'] ?? null,
                'sequence_order' => $validated['sequence_order'] ?? 0,
                'is_public' => $validated['is_public'] ?? false,
                'requires_enrollment' => $validated['requires_enrollment'] ?? true,
                'is_visible' => $validated['is_visible'] ?? true,
                'published_at' => now(),
            ]);

            if ($asset !== null) {
                $asset->update(['attachable_type' => $material->getMorphClass(), 'attachable_id' => $material->id]);
            }

            return $material;
        });

        // Authored and AI-assisted materials are a distinct, higher-risk change
        // surface, so record an explicit audit entry for observability.
        if ($material->source !== 'upload') {
            AuditLog::log(
                'course_material_authored',
                $user->id,
                CourseMaterial::class,
                $material->id,
                null,
                ['source' => $material->source, 'title' => $material->title],
                $request->ip(),
                $request->userAgent(),
            );
        }

        // Fire event to notify enrolled students
        $enrolledStudents = User::whereHas('enrollments', function ($q) use ($course, $semester) {
            $q->where('course_id', $course->id)
                ->where('semester_id', $semester->id)
                ->where('status', 'enrolled');
        })->get();

        if ($enrolledStudents->count() > 0) {
            event(new NewMaterialUploaded($material, $course, $enrolledStudents));
        }

        return redirect()->route('materials.show', [$course, $material])
            ->with('success', 'Material uploaded successfully.');
    }

    /**
     * Show single material
     */
    public function show(Course $course, CourseMaterial $material)
    {
        if ($material->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('view', $material);

        $material->load(['uploader', 'accessLogs.user']);
        // Students only ever see their own highlights and notes.
        $material->setRelation('annotations', $material->annotations()->where('user_id', Auth::id())->latest()->get());

        // Log view
        $material->accessLogs()->create([
            'user_id' => Auth::id(),
            'action' => 'viewed',
        ]);

        $discussions = $material->discussions()
            ->where('status', 'open')
            ->with(['user', 'replies.user'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('materials.show', compact('course', 'material', 'discussions'));
    }

    /**
     * Download material
     */
    public function download(Course $course, CourseMaterial $material)
    {
        if ($material->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('view', $material);

        // Increment download count
        $material->increment('download_count');

        // Log download
        $material->accessLogs()->create([
            'user_id' => Auth::id(),
            'action' => 'downloaded',
        ]);

        $material->loadMissing('mediaAsset');
        if ($material->mediaAsset) {
            abort_unless(in_array($material->mediaAsset->scan_status, ['clean', 'skipped'], true), 423, 'This file is unavailable until security checks pass.');
        }
        return $this->fileDelivery->stream(
            $material->disk ?: (string) config('filesystems.default', 'local'),
            $material->file_path,
            $material->file_name,
            $material->mime_type,
            'attachment'
        );
    }

    /**
     * Export course materials as PDF
     */
    public function exportPdf(Course $course)
    {
        $user = Auth::user();

        // Only lecturers/admins with access to this exact course can export.
        abort_unless(($user->isLecturer() || $user->isAdmin()) && $user->canAccessCourse($course), 403);

        $materials = $course->materials()
            ->where('is_visible', true)
            ->with(['uploader', 'accessLogs'])
            ->orderBy('topic')
            ->orderBy('week_number')
            ->orderBy('sequence_order')
            ->get();

        $pdfContent = $this->pdfService->generateCourseMaterialsPdf($course, $materials);
        $filename = 'materials_'.$course->code.'_'.now()->format('Y-m-d').'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Edit material
     */
    public function edit(Course $course, CourseMaterial $material)
    {
        if ($material->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $material);

        return view('materials.edit', compact('course', 'material'));
    }

    /**
     * Update material
     */
    public function update(Request $request, Course $course, CourseMaterial $material)
    {
        if ($material->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $material);

        $this->normalizeMaterialBooleans($request, [
            'is_public' => (bool) $material->is_public,
            'requires_enrollment' => (bool) $material->requires_enrollment,
            'is_visible' => (bool) $material->is_visible,
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'type' => 'required|in:lecture_note,slides,reading,video,assignment,exam,reference,other',
            'content' => 'nullable|string|max:200000',
            'topic' => 'nullable|string|max:255',
            'week_number' => 'nullable|integer|min:1|max:20',
            'sequence_order' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
            'requires_enrollment' => 'boolean',
            'is_visible' => 'boolean',
        ]);

        $updates = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'topic' => $validated['topic'] ?? null,
            'week_number' => $validated['week_number'] ?? null,
            'sequence_order' => $validated['sequence_order'] ?? 0,
            'is_public' => $validated['is_public'] ?? false,
            'requires_enrollment' => $validated['requires_enrollment'] ?? true,
            'is_visible' => $validated['is_visible'] ?? true,
        ];

        // Only touch authored content when the edit form actually submits the
        // content field, so metadata-only edits of an uploaded material never
        // clear an existing authored body.
        if ($request->has('content')) {
            $cleanContent = HtmlSanitizer::clean((string) ($validated['content'] ?? ''));
            $hasContent = trim(strip_tags($cleanContent)) !== '';
            $updates['content'] = $hasContent ? $cleanContent : null;
            $updates['content_format'] = 'html';
            $updates['content_updated_at'] = $hasContent ? now() : null;
            if ($hasContent && ! $material->hasFile()) {
                $updates['source'] = in_array($material->source, ['authored', 'ai_assisted'], true)
                    ? $material->source
                    : 'authored';
            }
        }

        $material->update($updates);

        if (array_key_exists('content', $updates)) {
            AuditLog::log(
                'course_material_content_updated',
                $request->user()?->id,
                CourseMaterial::class,
                $material->id,
                null,
                ['has_content' => $updates['content'] !== null],
                $request->ip(),
                $request->userAgent(),
            );
        }

        return redirect()->route('materials.show', [$course, $material])
            ->with('success', 'Material updated successfully.');
    }

    /**
     * Normalize browser checkbox values into actual booleans before validation.
     *
     * @param array{is_public?:bool,requires_enrollment?:bool,is_visible?:bool} $defaults
     */
    private function normalizeMaterialBooleans(Request $request, array $defaults = []): void
    {
        $normalized = [];
        foreach (['is_public', 'requires_enrollment', 'is_visible'] as $field) {
            $normalized[$field] = $request->has($field)
                ? $request->boolean($field)
                : (bool) ($defaults[$field] ?? false);
        }

        $request->merge($normalized);
    }

    /**
     * Delete material
     */
    public function destroy(Course $course, CourseMaterial $material)
    {
        if ($material->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('delete', $material);

        $storage = Storage::disk($material->disk ?: config('filesystems.default', 'local'));
        if ($storage->exists($material->file_path)) {
            $storage->delete($material->file_path);
        }
        $material->mediaAsset?->delete();
        $material->delete();

        return redirect()->route('courses.show', $course)
            ->with('success', 'Material deleted successfully.');
    }
}
