{{ \App\Services\SettingService::get('site_name', 'AcadSyntra') }} Notification

--------------------------------------------------------------------------------

{{ $body }}

@if(isset($data['url']))
View Details: {{ $data['url'] }}
@endif

--------------------------------------------------------------------------------
This is an automated message from {{ \App\Services\SettingService::get('site_name', 'AcadSyntra') }}.
To manage your notification preferences, visit your account settings: {{ config('app.url') }}/settings
@if($supportEmail = \App\Services\PublicWebsite::supportEmail())
Contact us: {{ $supportEmail }}
@endif
