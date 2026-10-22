<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4f46e5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ \App\Services\SettingService::get('site_name', 'AcadSyntra') }} Notification</h1>
        </div>
        <div class="content">
            <h2>{{ $title }}</h2>
            <p>{{ $body }}</p>
            @if(isset($data['url']))
                <p><a href="{{ $data['url'] }}" class="button">View Details</a></p>
            @endif
        </div>
        <div class="footer">
            <p>This is an automated message from {{ \App\Services\SettingService::get('site_name', 'AcadSyntra') }}.</p>
            <p>Manage your <a href="{{ route('notifications.settings') }}">notification preferences</a> and quiet hours.</p>
            @if($unsubscribeUrl ?? null)<p><a href="{{ $unsubscribeUrl }}">Unsubscribe from these optional reminders</a></p>@endif
            @if($supportEmail = \App\Services\PublicWebsite::supportEmail())
            <p>Contact us: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
            @endif
        </div>
    </div>
    @if($trackingUrl ?? null)<img src="{{ $trackingUrl }}" width="1" height="1" alt="">@endif
</body>
</html>
