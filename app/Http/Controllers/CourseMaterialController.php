<?php

namespace App\Http\Controllers;

use App\Events\NewMaterialUploaded;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Semester;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\PdfService;
use App\Services\SettingService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseMaterialController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
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
        $user = Auth::user();
        $semester = $this->academicContext->activeSemesterForCourse($course);

        $query = $course->materials()
            ->where('semester_id', $semester?->id)
            ->where('is_visible', true)
            ->with('uploader')
            ->orderBy('topic')
            ->orderBy('week_number')
            ->orderBy('sequence_order')
            ->orderBy('created_at', 'desc');

        // Students only see materials from enrolled courses unless public
        if ($user->isStudent() && ! $user->isAdmin()) {
            $query->where(function ($q) use ($course, $user) {
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

        // Get effective upload limit from user's plan
        $maxFileSizeBytes = $this->subscriptionService->getUploadLimitForUser($user);
        $maxFileSizeKb = $maxFileSizeBytes / 1024;
        $allowedMimes = implode(',', SettingService::getAllowedExtensions());

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:lecture_note,slides,reading,video,assignment,exam,reference,other',
            'file' => 'required|file|mimes:'.$allowedMimes.'|max:'.$maxFileSizeKb,
            'topic' => 'nullable|string|max:255',
            'week_number' => 'nullable|integer|min:1|max:20',
            'sequence_order' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
            'requires_enrollment' => 'boolean',
        ]);

        $file = $request->file('file');

        // Subscription validation
        $subscriptionErrors = $this->subscriptionService->validateMaterialUpload(
            $user,
            $file->getSize(),
            $file->getMimeType()
        );
        if (! empty($subscriptionErrors)) {
            return back()->withErrors(['file' => $subscriptionErrors]);
        }

        $universityId = $course->loadMissing('department.faculty')->department?->faculty?->university_id;
        abort_unless($universityId && $universityId === $user->university_id, 403);
        $semester = Semester::active()
            ->whereHas('academicSession', fn ($query) => $query->where('university_id', $universityId))
            ->firstOrFail();

        $material = DB::transaction(function () use ($file, $user, $course, $semester, $validated): CourseMaterial {
            $asset = $this->mediaSecurityService->store($file, $user, $course, ($validated['is_public'] ?? false) ? 'public' : 'institution', [
                'purpose' => 'course_material',
                'course_uuid' => $course->uuid,
                'semester_id' => $semester->id,
            ]);
            if (! in_array($asset->scan_status, ['clean', 'skipped'], true)) {
                throw ValidationException::withMessages(['file' => 'The material did not pass the configured security scan.']);
            }

            $material = CourseMaterial::create([
                'uuid' => Str::uuid(),
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'uploaded_by' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'file_name' => $asset->original_name,
                'file_path' => $asset->path,
                'disk' => $asset->disk,
                'media_asset_id' => $asset->id,
                'mime_type' => $asset->mime_type,
                'file_size' => $asset->size_bytes,
                'topic' => $validated['topic'] ?? null,
                'week_number' => $validated['week_number'] ?? null,
                'sequence_order' => $validated['sequence_order'] ?? 0,
                'is_public' => $validated['is_public'] ?? false,
                'requires_enrollment' => $validated['requires_enrollment'] ?? true,
                'is_visible' => true,
                'published_at' => now(),
            ]);
            $asset->update(['attachable_type' => $material->getMorphClass(), 'attachable_id' => $material->id]);
            return $material;
        });

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

        if (! $material->canBeViewedBy(Auth::user())) {
            abort(403);
        }

        $material->load(['uploader', 'accessLogs.user']);

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

        if (! $material->canBeViewedBy(Auth::user())) {
            abort(403);
        }

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

        // Only lecturers and admins can export
        if (! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403);
        }

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

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:lecture_note,slides,reading,video,assignment,exam,reference,other',
            'topic' => 'nullable|string|max:255',
            'week_number' => 'nullable|integer|min:1|max:20',
            'sequence_order' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
            'requires_enrollment' => 'boolean',
            'is_visible' => 'boolean',
        ]);

        $material->update($validated);

        return redirect()->route('materials.show', [$course, $material])
            ->with('success', 'Material updated successfully.');
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
