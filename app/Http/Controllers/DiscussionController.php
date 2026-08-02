<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\NewDiscussionPosted;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\DiscussionTag;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DiscussionController extends Controller
{
    /**
     * List discussions for a course
     */
    public function index(Course $course)
    {
        $user = Auth::user();
        $semester = Semester::where('is_active', true)->first();

        $query = Discussion::where('course_id', $course->id)
            ->where('semester_id', $semester?->id)
            ->with(['user', 'material', 'replies', 'tags'])
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
    public function show(Course $course, Discussion $discussion)
    {
        if ($discussion->course_id !== $course->id) {
            abort(404);
        }

        $discussion->load([
            'user',
            'material',
            'resolver',
            'replies.user',
            'replies.parent',
            'tags',
        ]);

        return view('discussions.show', compact('course', 'discussion'));
    }

    /**
     * Create new discussion (student/lecturer)
     */
    public function create(Request $request, Course $course)
    {
        
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
public function store(Request $request, Course $course)
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

    $semester = Semester::where('is_active', true)->first();

    $discussion = Discussion::create([
        'uuid' => Str::uuid(),
        'course_id' => $course->id,
        'semester_id' => $semester?->id,
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

    // Notify lecturers and enrolled students (excluding creator)
    $recipients = User::where(function ($q) use ($course) {
        // Lecturers of the course
        $q->whereHas('lecturerAssignments', fn ($sub) => $sub->where('course_id', $course->id));
    })
        ->orWhere(function ($q) use ($course, $semester) {
            $q->whereHas('enrollments', function ($sub) use ($course, $semester) {
                $sub->where('course_id', $course->id)
                    ->where('semester_id', $semester?->id)
                    ->where('status', 'enrolled');
            });
        })
        ->where('id', '!=', $user->id)
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
    public function addReply(Request $request, Course $course, Discussion $discussion)
    {
        if ($discussion->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('view', $discussion);

        $validated = $request->validate([
            'content' => 'required|string',
            'parent_reply_id' => 'nullable|exists:discussion_replies,id',
            'type' => 'in:answer,comment,clarification',
        ]);

        $reply = DiscussionReply::create([
            'uuid' => Str::uuid(),
            'discussion_id' => $discussion->id,
            'user_id' => Auth::id(),
            'parent_reply_id' => $validated['parent_reply_id'] ?? null,
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'answer',
        ]);

        // If this is an accepted answer, update discussion
        if ($request->has('accept') && $request->accept) {
            $discussion->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => Auth::id(),
            ]);

            // Mark this reply as accepted
            $reply->update(['is_accepted' => true, 'accepted_at' => now()]);
        }

        // Notify discussion author and previous repliers (excluding current replier)
        $recipients = collect([$discussion->user])
            ->merge($discussion->replies()->with('user')->get()->pluck('user'))
            ->unique('id')
            ->filter(fn ($u) => $u && $u->id !== Auth::id())
            ->values();

        if ($recipients->count() > 0) {
            event(new NewDiscussionPosted($discussion, $course, $recipients, true));
        }

        return back()->with('success', 'Reply added successfully.');
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
