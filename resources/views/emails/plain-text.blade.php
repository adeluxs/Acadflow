{{ \App\Services\SettingService::get('site_name', 'UniAcademic') }} Notification

--------------------------------------------------------------------------------

{{ $body }}

@if(isset($data['url']))
View Details: {{ $data['url'] }}
@endif

--------------------------------------------------------------------------------
This is an automated message from {{ \App\Services\SettingService::get('site_name', 'UniAcademic') }}.
To manage your notification preferences, visit your account settings: {{ config('app.url') }}/settings
Contact us: {{ \App\Services\SettingService::get('support_email', 'support@example.com') }}
