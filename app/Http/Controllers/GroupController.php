<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get groups where user is a member or leader
        $groups = Group::where(function ($query) use ($user) {
            $query->where('leader_id', $user->id)
                  ->orWhereHas('members', function ($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
        })
        ->with(['course', 'leader', 'members'])
        ->latest()
        ->paginate(12);

        return view('groups.index', compact('groups'));
    }

    public function create()
    {
        $user = Auth::user();

        // Get courses where user is enrolled as student
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->with('course')
            ->get();

        return view('groups.create', compact('enrollments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'required|integer|min:2|max:10',
        ]);

        $user = Auth::user();

        // Check if user is enrolled in the course
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $validated['course_id'])
            ->where('status', 'enrolled')
            ->first();

        if (!$enrollment) {
            return back()->with('error', 'You are not enrolled in this course.');
        }

        // Check if user is already in a group for this course
        $existingGroup = Group::where('course_id', $validated['course_id'])
            ->where(function ($query) use ($user) {
                $query->where('leader_id', $user->id)
                      ->orWhereHas('members', function ($q) use ($user) {
                          $q->where('user_id', $user->id);
                      });
            })
            ->first();

        if ($existingGroup) {
            return back()->with('error', 'You are already in a group for this course.');
        }

        $semester = Semester::where('is_active', true)->first();

        $group = Group::create([
            'uuid' => Str::uuid(),
            'course_id' => $validated['course_id'],
            'semester_id' => $semester?->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'leader_id' => $user->id,
            'status' => 'forming',
            'max_members' => $validated['max_members'],
            'formed_at' => now(),
        ]);

        // Add leader as first member
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'leader',
        ]);

        return redirect()->route('groups.show', $group)->with('success', 'Group created successfully!');
    }

    public function show(Group $group)
    {
        $group->load(['course', 'leader', 'members.user', 'submissions']);

        return view('groups.show', compact('group'));
    }

    public function edit(Group $group)
    {
        // Check if user is the leader
        if ($group->leader_id !== Auth::id()) {
            abort(403);
        }

        $group->load(['course', 'members.user']);

        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        // Check if user is the leader
        if ($group->leader_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'required|integer|min:2|max:10',
            'status' => 'required|in:forming,complete,archived',
        ]);

        $group->update($validated);

        return redirect()->route('groups.show', $group)->with('success', 'Group updated successfully!');
    }

    public function join(Request $request, Group $group)
    {
        $user = Auth::user();

        // Check if group is still accepting members
        if ($group->status === 'archived' || $group->members->count() >= $group->max_members) {
            return back()->with('error', 'This group is full or archived.');
        }

        // Check if user is enrolled in the course
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $group->course_id)
            ->where('status', 'enrolled')
            ->first();

        if (!$enrollment) {
            return back()->with('error', 'You are not enrolled in this course.');
        }

        // Check if user is already in a group for this course
        $existingGroup = Group::where('course_id', $group->course_id)
            ->where(function ($query) use ($user) {
                $query->where('leader_id', $user->id)
                      ->orWhereHas('members', function ($q) use ($user) {
                          $q->where('user_id', $user->id);
                      });
            })
            ->first();

        if ($existingGroup) {
            return back()->with('error', 'You are already in a group for this course.');
        }

        // Add user to group
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        return back()->with('success', 'Successfully joined the group!');
    }

    public function leave(Group $group)
    {
        $user = Auth::user();

        // Cannot leave if you're the leader
        if ($group->leader_id === $user->id) {
            return back()->with('error', 'Group leaders cannot leave their groups. Transfer leadership first.');
        }

        // Remove user from group
        GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->route('groups.index')->with('success', 'Successfully left the group.');
    }

    public function removeMember(Request $request, Group $group)
    {
        // Check if user is the leader
        if ($group->leader_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Cannot remove yourself
        if ($validated['user_id'] == Auth::id()) {
            return back()->with('error', 'You cannot remove yourself from the group.');
        }

        GroupMember::where('group_id', $group->id)
            ->where('user_id', $validated['user_id'])
            ->delete();

        return back()->with('success', 'Member removed from group.');
    }

    public function transferLeadership(Request $request, Group $group)
    {
        // Check if user is the current leader
        if ($group->leader_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'new_leader_id' => 'required|exists:users,id',
        ]);

        // Check if new leader is a member of the group
        $isMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $validated['new_leader_id'])
            ->exists();

        if (!$isMember) {
            return back()->with('error', 'Selected user is not a member of this group.');
        }

        // Update group leadership
        $group->update(['leader_id' => $validated['new_leader_id']]);

        // Update member roles
        GroupMember::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->update(['role' => 'member']);

        GroupMember::where('group_id', $group->id)
            ->where('user_id', $validated['new_leader_id'])
            ->update(['role' => 'leader']);

        return back()->with('success', 'Leadership transferred successfully.');
    }

    public function destroy(Group $group)
    {
        // Check if user is the leader
        if ($group->leader_id !== Auth::id()) {
            abort(403);
        }

        // Check if group has submissions
        if ($group->submissions->isNotEmpty()) {
            return back()->with('error', 'Cannot delete group with existing submissions.');
        }

        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Group deleted successfully.');
    }
}