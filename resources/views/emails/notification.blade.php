<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
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
            <h1>{{ \App\Services\SettingService::get('site_name', 'AcadFlow') }} Notification</h1>
        </div>
        <div class="content">
            <h2>{{ $title }}</h2>
            <p>{{ $body }}</p>
            @if(isset($data['url']))
                <p><a href="{{ $data['url'] }}" class="button">View Details</a></p>
            @endif
        </div>
        <div class="footer">
            <p>This is an automated message from {{ \App\Services\SettingService::get('site_name', 'AcadFlow') }}.</p>
            <p>To manage your notification preferences, visit your <a href="{{ config('app.url') }}/settings">account settings</a>.</p>
            <p>Contact us: <a href="mailto:{{ \App\Services\SettingService::get('support_email', 'support@example.com') }}">{{ \App\Services\SettingService::get('support_email', 'support@example.com') }}</a></p>
        </div>
    </div>
</body>
</html>
