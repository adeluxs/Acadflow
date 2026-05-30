UniAcademic Notification

--------------------------------------------------------------------------------

{{ $body }}

@if(isset($data['url']])
View Details: {{ $data['url'] }}
@endif

--------------------------------------------------------------------------------
This is an automated message from UniAcademic.
To manage your notification preferences, visit your account settings: {{ config('app.url') }}/settings
