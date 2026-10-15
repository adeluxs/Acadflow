<?php

namespace App\Providers;

use App\Models\AttendanceSession;
use App\Models\AcademicChallenge;
use App\Models\AcademicEvent;
use App\Models\Group;
use App\Models\KnowledgeCommunity;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Faculty;
use App\Models\Invoice;
use App\Models\KnowledgePublication;
use App\Models\ResearchProject;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\University;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\AcademicChallengePolicy;
use App\Policies\AcademicEventPolicy;
use App\Policies\GroupPolicy;
use App\Policies\KnowledgeCommunityPolicy;
use App\Policies\CourseMaterialPolicy;
use App\Policies\CoursePolicy;
use App\Policies\DiscussionPolicy;
use App\Policies\DiscussionReplyPolicy;
use App\Policies\FacultyPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\KnowledgePublicationPolicy;
use App\Policies\ResearchProjectPolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\SubmissionTaskPolicy;
use App\Policies\UniversityPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Course::class => CoursePolicy::class,
        CourseMaterial::class => CourseMaterialPolicy::class,
        Discussion::class => DiscussionPolicy::class,
        DiscussionReply::class => DiscussionReplyPolicy::class,
        Submission::class => SubmissionPolicy::class,
        SubmissionTask::class => SubmissionTaskPolicy::class,
        AttendanceSession::class => AttendancePolicy::class,
        Invoice::class => InvoicePolicy::class,
        ResearchProject::class => ResearchProjectPolicy::class,
        KnowledgePublication::class => KnowledgePublicationPolicy::class,
        University::class => UniversityPolicy::class,
        Faculty::class => FacultyPolicy::class,
        AcademicEvent::class => AcademicEventPolicy::class,
        AcademicChallenge::class => AcademicChallengePolicy::class,
        KnowledgeCommunity::class => KnowledgeCommunityPolicy::class,
        Group::class => GroupPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('manage-billing', function (User $user) {
            return in_array($user->role, ['super_admin', 'university_admin', 'department_admin']);
        });

        Gate::define('view-analytics', function (User $user) {
            return $user->role !== 'student';
        });

        Gate::define('manage-university', function (User $user) {
            return in_array($user->role, ['super_admin', 'university_admin']);
        });

        Gate::define('manage-department', function (User $user) {
            return in_array($user->role, ['super_admin', 'university_admin', 'department_admin']);
        });

        Gate::define('send-notifications', function (User $user) {
            return in_array($user->role, ['super_admin', 'university_admin', 'department_admin', 'lecturer']);
        });

        Gate::define('system-settings', function (User $user) {
            return $user->role === 'super_admin';
        });

        Gate::define('feature', function (User $user, string $feature) {
            return $user->hasFeature($feature);
        });
    }
}
