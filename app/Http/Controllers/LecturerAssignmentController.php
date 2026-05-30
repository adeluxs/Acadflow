<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LecturerAssignmentController extends Controller
{
    /**
     * Store a newly created lecturer assignment.
     */
    public function store(Request $request, Course $course)
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'is_coordinator' => 'boolean',
        ]);

        // Check if user is actually a lecturer
        $user = User::findOrFail($validated['user_id']);
        if (! $user->isLecturer()) {
            return back()->with('error', 'Selected user is not a lecturer.');
        }

        // Check for existing assignment in current/active semester
        $activeSemester = Semester::where('is_active', true)->first();
        $existing = LecturerCourseAssignment::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->when($activeSemester, function ($query) use ($activeSemester) {
                return $query->where('semester_id', $activeSemester->id);
            })
            ->exists();

        if ($existing) {
            return back()->with('error', 'This lecturer is already assigned to the course.');
        }

        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'semester_id' => $activeSemester?->id,
            'is_coordinator' => $validated['is_coordinator'] ?? false,
        ]);

        return back()->with('success', 'Lecturer assigned to course successfully.');
    }

    /**
     * Remove a lecturer assignment.
     */
    public function destroy(LecturerCourseAssignment $assignment)
    {
        $this->authorize('update', $assignment->course);

        $assignment->delete();

        return back()->with('success', 'Lecturer unassigned from course successfully.');
    }

    /**
     * Update coordinator status.
     */
    public function updateCoordinator(Request $request, LecturerCourseAssignment $assignment)
    {
        $this->authorize('update', $assignment->course);

        $validated = $request->validate([
            'is_coordinator' => 'required|boolean',
        ]);

        $assignment->update([
            'is_coordinator' => $validated['is_coordinator'],
        ]);

        return back()->with('success', 'Coordinator status updated.');
    }
}
