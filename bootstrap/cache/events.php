<?php return array (
  'App\\Providers\\EventServiceProvider' => 
  array (
    'App\\Events\\SubmissionSubmitted' => 
    array (
      0 => 'App\\Listeners\\SendSubmissionReceivedNotification',
    ),
    'App\\Events\\SubmissionConfirmation' => 
    array (
      0 => 'App\\Listeners\\SendSubmissionConfirmation',
    ),
    'App\\Events\\CorrectionRequested' => 
    array (
      0 => 'App\\Listeners\\SendCorrectionRequestedNotification',
    ),
    'App\\Events\\SubmissionApproved' => 
    array (
      0 => 'App\\Listeners\\SendSubmissionApprovedNotification',
    ),
    'App\\Events\\AttendanceSessionStarted' => 
    array (
      0 => 'App\\Listeners\\SendAttendanceStartedNotification',
    ),
    'App\\Events\\DeadlineApproaching' => 
    array (
      0 => 'App\\Listeners\\SendDeadlineApproachingNotification',
    ),
    'App\\Events\\NewMaterialUploaded' => 
    array (
      0 => 'App\\Listeners\\SendNewMaterialNotification',
    ),
    'App\\Events\\AssignmentCreated' => 
    array (
      0 => 'App\\Listeners\\SendAssignmentCreatedNotification',
    ),
    'App\\Events\\NewDiscussionPosted' => 
    array (
      0 => 'App\\Listeners\\SendDiscussionNotification',
    ),
    'App\\Events\\SystemAnnouncementBroadcast' => 
    array (
      0 => 'App\\Listeners\\SendSystemAnnouncement',
    ),
    'App\\Events\\SubmissionAiAnalysisRequested' => 
    array (
      0 => 'App\\Listeners\\DispatchSubmissionAiAnalysis',
    ),
  ),
  'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider' => 
  array (
    'App\\Events\\SubmissionSubmitted' => 
    array (
      0 => 'App\\Listeners\\SendSubmissionReceivedNotification@handle',
    ),
    'App\\Events\\SubmissionApproved' => 
    array (
      0 => 'App\\Listeners\\SendSubmissionApprovedNotification@handle',
    ),
    'App\\Events\\NewDiscussionPosted' => 
    array (
      0 => 'App\\Listeners\\SendDiscussionNotification@handle',
    ),
    'App\\Events\\SystemAnnouncementBroadcast' => 
    array (
      0 => 'App\\Listeners\\SendSystemAnnouncement@handle',
    ),
    'App\\Events\\AssignmentCreated' => 
    array (
      0 => 'App\\Listeners\\SendAssignmentCreatedNotification@handle',
    ),
    'App\\Events\\NewMaterialUploaded' => 
    array (
      0 => 'App\\Listeners\\SendNewMaterialNotification@handle',
    ),
    'App\\Events\\DeadlineApproaching' => 
    array (
      0 => 'App\\Listeners\\SendDeadlineApproachingNotification@handle',
    ),
    'App\\Events\\SubmissionAiAnalysisRequested' => 
    array (
      0 => 'App\\Listeners\\DispatchSubmissionAiAnalysis@handle',
    ),
    'App\\Events\\AttendanceSessionStarted' => 
    array (
      0 => 'App\\Listeners\\SendAttendanceStartedNotification@handle',
    ),
    'App\\Events\\CorrectionRequested' => 
    array (
      0 => 'App\\Listeners\\SendCorrectionRequestedNotification@handle',
    ),
    'App\\Events\\SubmissionConfirmation' => 
    array (
      0 => 'App\\Listeners\\SendSubmissionConfirmation@handle',
    ),
  ),
);