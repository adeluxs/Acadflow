<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\Notification;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CourseMembershipController extends Controller
{
    public function selfAssign(Request $request, Course $course, AcademicContextService $academicContext): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isLecturer(), 403);
        abort_unless((bool) SettingService::get('lecturer_self_assignment_enabled', true, $user->university_id), 403, 'Lecturer self-assignment is disabled.');

        $this->assertSameAcademicScope($user, $course, requireDepartment: true);
        abort_unless($course->is_active, 422, 'This course is not active.');

        $semester = $academicContext->requireActiveSemesterForCourse($course);

        LecturerCourseAssignment::query()->firstOrCreate([
            'course_id' => $course->id,
            'user_id' => $user->id,
            'semester_id' => $semester->id,
        ], [
            'is_coordinator' => false,
        ]);

        return back()->with('success', 'You have been added to the course teaching team.');
    }

    public function enrollStudent(Request $request, Course $course, AcademicContextService $academicContext): RedirectResponse
    {
        $lecturer = $request->user();
        $semester = $academicContext->requireActiveSemesterForCourse($course);
        $this->assertLecturerCanManage($lecturer, $course, $semester->id);

        $data = $request->validate([
            'student' => ['required', 'string', 'max:255'],
        ]);

        $student = User::query()
            ->where('role', 'student')
            ->where(function ($query) use ($data): void {
                $query->where('email', $data['student'])
                    ->orWhere('student_id', $data['student'])
                    ->orWhere('username', $data['student']);
            })
            ->first();

        if (! $student) {
            return back()->withErrors(['student' => 'No student account matches that email, student ID, or username.']);
        }

        $this->assertSameAcademicScope($student, $course, requireDepartment: $this->restrictStudentsToDepartment($student));
        $this->assertCapacity($course, $semester->id);

        Enrollment::query()->updateOrCreate([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ], [
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->notifyStudent($student, $course, 'You were enrolled in '.$course->code.' by '.$lecturer->full_name.'.');

        return back()->with('success', $student->full_name.' has been enrolled.');
    }

    public function inviteStudent(Request $request, Course $course, AcademicContextService $academicContext): RedirectResponse
    {
        $lecturer = $request->user();
        $semester = $academicContext->requireActiveSemesterForCourse($course);
        $this->assertLecturerCanManage($lecturer, $course, $semester->id);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $existing = User::query()->where('email', $data['email'])->first();
        if ($existing) {
            if (! $existing->isStudent()) {
                return back()->withErrors(['email' => 'That email belongs to a non-student account.']);
            }

            $this->assertSameAcademicScope($existing, $course, requireDepartment: $this->restrictStudentsToDepartment($existing));
        }

        $rawToken = Str::random(64);
        $expiresInDays = max(1, min(30, (int) SettingService::get('course_invitation_expiry_days', 7, $lecturer->university_id)));

        CourseInvitation::query()
            ->where('course_id', $course->id)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->delete();

        CourseInvitation::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'invited_by' => $lecturer->id,
            'email' => $data['email'],
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        $url = route('courses.invitation.accept', ['token' => $rawToken]);

        if ($existing) {
            $this->notifyStudent($existing, $course, 'You have been invited to join '.$course->code.'.', $url);
        }

        if ((bool) SettingService::get('email_notifications_enabled', true, $lecturer->university_id)) {
            try {
                Mail::raw(
                    "You have been invited to join {$course->code} — {$course->name} on AcadFlow.\n\nAccept invitation: {$url}\n\nThis invitation expires in {$expiresInDays} day(s).",
                    fn ($message) => $message->to($data['email'])->subject('AcadFlow course invitation: '.$course->code)
                );
            } catch (\Throwable) {
                // Invitation remains valid even when outbound mail is unavailable.
            }
        }

        return back()->with('success', 'Invitation created.')->with('course_invitation_url', $url);
    }

    public function acceptInvitation(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403, 'Only student accounts can accept course invitations.');

        $invitation = CourseInvitation::query()
            ->with('course.department.faculty')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        if (! hash_equals(strtolower($invitation->email), strtolower($user->email))) {
            abort(403, 'This invitation was issued to a different email address.');
        }

        if (! $invitation->isUsable()) {
            return redirect()->route('courses.index')->with('error', 'This invitation has expired or was already used.');
        }

        $course = $invitation->course;
        $this->assertSameAcademicScope($user, $course, requireDepartment: $this->restrictStudentsToDepartment($user));
        $this->assertCapacity($course, $invitation->semester_id);

        DB::transaction(function () use ($invitation, $user, $course): void {
            Enrollment::query()->updateOrCreate([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'semester_id' => $invitation->semester_id,
            ], [
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);
        });

        return redirect()->route('courses.show', $course)->with('success', 'Course invitation accepted.');
    }

    private function assertLecturerCanManage(?User $lecturer, Course $course, int $semesterId): void
    {
        abort_unless($lecturer?->isLecturer(), 403);
        $this->assertSameAcademicScope($lecturer, $course, requireDepartment: true);

        abort_unless(
            $course->lecturerAssignments()
                ->where('user_id', $lecturer->id)
                ->where('semester_id', $semesterId)
                ->exists(),
            403,
            'You must be on this course teaching team for the active semester to manage its students.'
        );
    }

    private function assertSameAcademicScope(User $user, Course $course, bool $requireDepartment): void
    {
        $course->loadMissing('department.faculty');
        $courseUniversityId = $course->department?->faculty?->university_id;

        abort_unless($courseUniversityId && $user->university_id === $courseUniversityId, 403, 'The account and course must belong to the same institution.');

        if ($requireDepartment) {
            abort_unless($user->department_id && $user->department_id === $course->department_id, 403, 'The account and course must belong to the same department.');
        }
    }

    private function restrictStudentsToDepartment(User $student): bool
    {
        return (bool) SettingService::get('restrict_course_membership_to_department', true, $student->university_id);
    }

    private function assertCapacity(Course $course, int $semesterId): void
    {
        if (! $course->max_capacity) {
            return;
        }

        $count = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('semester_id', $semesterId)
            ->where('status', 'enrolled')
            ->count();

        abort_if($count >= $course->max_capacity, 422, 'Course is at maximum capacity.');
    }

    private function notifyStudent(User $student, Course $course, string $message, ?string $url = null): void
    {
        try {
            Notification::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $student->id,
                'type' => 'course_membership',
                'title' => $course->code.' course update',
                'message' => $message,
                'data' => ['course_uuid' => $course->uuid, 'url' => $url ?: route('courses.show', $course)],
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Membership changes must not fail just because notifications are unavailable.
        }
    }
}
