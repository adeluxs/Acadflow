<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - {{ \App\Services\SettingService::get('site_name', 'UniAcademic') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 480px;
            width: 100%;
        }
        .error-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #fef3c7 0%, #f59e0b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        .error-icon svg { width: 50px; height: 50px; color: #fff; }
        h1 { color: #1f2937; font-size: 28px; font-weight: 700; margin-bottom: 16px; }
        p { color: #6b7280; font-size: 16px; line-height: 1.6; margin-bottom: 32px; }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            margin-left: 12px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <h1>Access Denied</h1>
        <p>You do not have permission to access this page. Please contact your administrator if you believe this is an error.</p>
        <a href="{{ url()->previous() }}" class="btn">Go Back</a>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Dashboard</a>
    </div>
</body>
</html>