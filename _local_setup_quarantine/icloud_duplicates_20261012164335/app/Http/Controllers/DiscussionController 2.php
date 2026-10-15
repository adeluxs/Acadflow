<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\NewDiscussionPosted;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Discussion;
use App\Models\EngagementComment;
use App\Models\DiscussionTag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\AcademicContextService;
use App\Services\EngagementService;

class DiscussionController extends Controller
{
    /**
     * List discussions for a course
     */
    public function index(Course $course, AcademicContextService $academicContext)
    {
        $this->authorize('view', $course);
        $user = Auth::user();
        $semester = $academicContext->activeSemesterForCourse($course);

        $query = Discussion::where('course_id', $course->id)
            ->where('semester_id', $semester?->id)
            ->with(['user', 'material', 'tags', 'engagementThread' => fn ($thread) => $thread->withCount('comments')])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($user->isStudent()) {
            $query->whereIn('status', ['open', 'resolved']);
        }

        // Filter by tag if provided
        if (request()->has('tag')) {
            $query->whereHas('tags', function ($q) {
                $q->where('name', request('tag'));
            });
        }

        // Search
        if (request()->has('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $discussions = $query->paginate(20);

        $tags = DiscussionTag::orderBy('name')->get();

        return view('discussions.index', compact('course', 'discussions', 'tags'));
    }

    /**
     * Show specific discussion
     */
    public function show(Course $course, Discussion $discussion, EngagementService $engagement)
    {
        if ($discussion->course_id !== $course->id) abort(404);
        $this->authorize('view', $discussion);
        $discussion->load(['user', 'material', 'resolver', 'tags', 'engagementThread']);
        $replies = $engagement->commentsFor($discussion, 100);
        return view('discussions.show', compact('course', 'discussion', 'replies'));
    }

    /**
     * Create new discussion (student/lecturer)
     */
    public function create(Request $request, Course $course)
    {
        $this->authorize('addDiscussion', $course);

        $materialId = $request->query('material_id');
        $material = null;

        if ($materialId) {
            $material = CourseMaterial::where('id', $materialId)
                ->where('course_id', $course->id)
                ->first();
        }

        $tags = DiscussionTag::orderBy('name')->get();

        return view('discussions.create', compact('course', 'material', 'tags'));
    }

    /**
 * Store new discussion
 */
public function store(Request $request, Course $course, EngagementService $engagement, AcademicContextService $academicContext)
{
    $user = Auth::user();

    $this->authorize('addDiscussion', $course);

    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'material_id' => 'nullable|exists:course_materials,id',
        'priority' => 'required|in:low,normal,high',
        'tag_ids' => 'nullable|array',
        'tag_ids.*' => 'exists:discussion_tags,id',
    ]);

    // Verify material belongs to course if provided
    $materialId = $validated['material_id'] ?? null;

    if ($materialId) {
        $material = CourseMaterial::findOrFail($materialId);

        if ($material->course_id !== $course->id) {
            abort(403, 'Material does not belong to this course.');
        }
    }

    $semester = $academicContext->activeSemesterForCourse($course);
    abort_unless($semester, 422, 'No active semester is configured. Please contact an administrator.');

    $discussion = Discussion::create([
        'uuid' => Str::uuid(),
        'course_id' => $course->id,
        'semester_id' => $semester->id,
        'user_id' => $user->id,
        'material_id' => $materialId,
        'title' => $validated['title'],
        'content' => $validated['content'],
        'status' => 'open',
        'priority' => $validated['priority'],
        'is_pinned' => false,
    ]);

    // Attach tags
    if (! empty($validated['tag_ids'])) {
        $discussion->tags()->attach($validated['tag_ids']);
    }

    $engagement->threadFor($discussion, $course->department?->faculty?->university_id ?? $user->university_id, 'institution', $discussion->title);
    $engagement->ensureSubscribed($discussion, $user);

    // Notify lecturers and enrolled students (excluding creator)
    $recipients = User::query()
        ->where('university_id', $user->university_id)
        ->where('id', '!=', $user->id)
        ->where(function ($query) use ($course, $semester) {
            $query->whereHas('lecturerAssignments', fn ($sub) => $sub->where('course_id', $course->id))
                ->orWhereHas('enrollments', function ($sub) use ($course, $semester) {
                    $sub->where('course_id', $course->id)
                        ->where('semester_id', $semester->id)
                        ->where('status', 'enrolled');
                });
        })
        ->get();

    if ($recipients->count() > 0) {
        event(new NewDiscussionPosted($discussion, $course, $recipients, false));
    }

    return redirect()->route('discussions.show', [$course, $discussion])
        ->with('success', 'Discussion created successfully.');
}

    /**
     * Add reply to discussion
     */
    public function addReply(Request $request, Course $course, Discussion $discussion, EngagementService $engagement)
    {
        if ($discussion->course_id !== $course->id) abort(404);
        $this->authorize('view', $discussion);
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
            'parent_reply_id' => ['nullable', 'integer', 'exists:engagement_comments,id'],
            'type' => ['nullable', 'in:answer,comment,clarification'],
        ]);
        $thread = $engagement->threadFor($discussion, $course->department?->faculty?->university_id ?? $request->user()->university_id, 'institution', $discussion->title);
        if ($validated['parent_reply_id'] ?? null) abort_unless($thread->comments()->whereKey($validated['parent_reply_id'])->exists(), 422, 'Parent reply does not belong to this discussion.');
        $engagement->ensureSubscribed($discussion, $request->user());
        $engagement->comment($discussion, $request->user(), $validated['content'], [
            'university_id' => $thread->university_id, 'visibility' => 'institution',
            'parent_id' => $validated['parent_reply_id'] ?? null, 'comment_type' => $validated['type'] ?? 'answer',
        ]);
        return back()->with('success', 'Reply added successfully.');
    }

    public function acceptReply(Request $request, Course $course, Discussion $discussion, EngagementComment $comment): mixed
    {
        if ($discussion->course_id !== $course->id) abort(404);
        $this->authorize('pin', $discussion);
        $thread = $discussion->engagementThread;
        abort_unless($thread && $comment->engagement_thread_id === $thread->id, 404);
        $thread->comments()->update(['is_verified_response' => false]);
        $comment->update(['is_verified_response' => true, 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        $discussion->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $request->user()->id]);
        return back()->with('success', 'Accepted answer recorded through the shared engagement service.');
    }

    public function reactReply(Request $request, Course $course, Discussion $discussion, EngagementComment $comment, EngagementService $engagement): mixed
    {
        if ($discussion->course_id !== $course->id) abort(404);
        $this->authorize('view', $discussion);
        abort_unless($discussion->engagementThread?->id === $comment->engagement_thread_id, 404);
        $data = $request->validate(['reaction' => ['required', 'in:like,helpful,insightful,agree']]);
        $engagement->react($comment, $request->user(), $data['reaction']);
        return back()->with('success', 'Reaction updated.');
    }

    public function report(Request $request, Course $course, Discussion $discussion, EngagementService $engagement): mixed
    {
        if ($discussion->course_id !== $course->id) abort(404);
        $this->authorize('view', $discussion);
        $data = $request->validate(['reason' => ['required', 'in:spam,harassment,unsafe,academic_integrity,misinformation,other'], 'details' => ['nullable', 'string', 'max:5000']]);
        $engagement->report($discussion, $request->user(), $data['reason'], $data['details'] ?? null);
        return back()->with('success', 'Report submitted for human moderation.');
    }

    public function subscribe(Request $request, Course $course, Discussion $discussion, EngagementService $engagement): mixed
    {
        if ($discussion->course_id !== $course->id) abort(404);
        $this->authorize('view', $discussion);
        $active = $engagement->subscribe($discussion, $request->user());
        return back()->with('success', $active ? 'Discussion notifications enabled.' : 'Discussion notifications disabled.');
    }

    /**
     * Update discussion (only owner or admin)
     */
    public function edit(Course $course, Discussion $discussion)
    {
        if ($discussion->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $discussion);

        $tags = DiscussionTag::orderBy('name')->get();

        return view('discussions.edit', compact('course', 'discussion', 'tags'));
    }

    /**
     * Update discussion
     */
    public function update(Request $request, Course $course, Discussion $discussion)
    {
        if ($discussion->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $discussion);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'required|in:low,normal,high',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:discussion_tags,id',
        ]);

        $discussion->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'priority' => $validated['priority'],
        ]);
        $discussion->engagementThread?->update(['title' => $validated['title']]);

        // Update tags
        if (isset($validated['tag_ids'])) {
            $discussion->tags()->sync($validated['tag_ids'] ?? []);
        }

        return redirect()->route('discussions.show', [$course, $discussion])
            ->with('success', 'Discussion updated successfully.');
    }

    /**
     * Close/Archive discussion (lecturer/admin)
     */
    public function close(Course $course, Discussion $discussion)
    {
        if ($discussion->course_id !== $course->id) {
            abort(404);
        }

        $user = Auth::user();
        if (! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403);
        }

        $discussion->update(['status' => 'closed']);
        $discussion->engagementThread?->update(['status' => 'closed', 'is_locked' => true]);

        return back()->with('success', 'Discussion closed.');
    }

    /**
     * Pin discussion (lecturer/admin)
     */
    public function pin(Course $course, Discussion $discussion)
    {
        if ($discussion->course_id !== $course->id) {
            abort(404);
        }

        $user = Auth::user();
        if (! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403);
        }

        $discussion->update(['is_pinned' => ! $discussion->is_pinned]);

        return back()->with('success', $discussion->is_pinned ? 'Discussion pinned.' : 'Discussion unpinned.');
    }
}
