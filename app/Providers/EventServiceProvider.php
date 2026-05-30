<?php

namespace App\Providers;

use App\Events\AssignmentCreated;
use App\Events\AttendanceSessionStarted;
use App\Events\CorrectionRequested;
use App\Events\DeadlineApproaching;
use App\Events\NewDiscussionPosted;
use App\Events\NewMaterialUploaded;
use App\Events\SubmissionApproved;
use App\Events\SubmissionConfirmation;
use App\Events\SubmissionSubmitted;
use App\Events\SystemAnnouncementBroadcast;
use App\Listeners\SendAssignmentCreatedNotification;
use App\Listeners\SendAttendanceStartedNotification;
use App\Listeners\SendCorrectionRequestedNotification;
use App\Listeners\SendDeadlineApproachingNotification;
use App\Listeners\SendDiscussionNotification;
use App\Listeners\SendNewMaterialNotification;
use App\Listeners\SendSubmissionApprovedNotification;
use App\Listeners\SendSubmissionConfirmation;
use App\Listeners\SendSubmissionReceivedNotification;
use App\Listeners\SendSystemAnnouncement;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Submission events
        SubmissionSubmitted::class => [
            SendSubmissionReceivedNotification::class,
        ],

        SubmissionConfirmation::class => [
            SendSubmissionConfirmation::class,
        ],

        CorrectionRequested::class => [
            SendCorrectionRequestedNotification::class,
        ],

        SubmissionApproved::class => [
            SendSubmissionApprovedNotification::class,
        ],

        // Attendance events
        AttendanceSessionStarted::class => [
            SendAttendanceStartedNotification::class,
        ],

        // Deadline events
        DeadlineApproaching::class => [
            SendDeadlineApproachingNotification::class,
        ],

        // Material and assignment events
        NewMaterialUploaded::class => [
            SendNewMaterialNotification::class,
        ],

        AssignmentCreated::class => [
            SendAssignmentCreatedNotification::class,
        ],

        // Discussion events
        NewDiscussionPosted::class => [
            SendDiscussionNotification::class,
        ],

        // System events
        SystemAnnouncementBroadcast::class => [
            SendSystemAnnouncement::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
