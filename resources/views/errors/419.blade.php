<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Expired - {{ \App\Services\SettingService::get('site_name', 'UniAcademic') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .error-icon svg {
            width: 50px;
            height: 50px;
            color: #fff;
        }
        
        h1 {
            color: #1f2937;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            margin-left: 12px;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            box-shadow: none;
        }
        
        .reason {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .reason-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .reason ul {
            color: #6b7280;
            font-size: 14px;
            padding-left: 20px;
        }
        
        .reason li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        
        <h1>Session Expired</h1>
        
        <p>Your session has expired due to inactivity. Please log in again to continue.</p>
        
        <div class="reason">
            <div class="reason-title">Why did this happen?</div>
            <ul>
                <li>You were inactive for too long</li>
                <li>You logged in from another device</li>
                <li>Your browser cookies were cleared</li>
            </ul>
        </div>
        
        <a href="{{ route('login') }}" class="btn">
            Log In Again
        </a>
        <a href="{{ route('home') }}" class="btn btn-secondary">
            Go Home
        </a>
    </div>
</body>
</html>