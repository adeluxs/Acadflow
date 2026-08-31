<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Request unavailable · AcadFlow</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f8fafc;color:#0f172a;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(100%,620px);background:#fff;border:1px solid #e2e8f0;border-radius:28px;padding:34px;box-shadow:0 24px 60px rgba(15,23,42,.09)}.icon{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:#fff7ed;color:#ea580c;font-weight:900;margin-bottom:22px}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.16em;font-weight:800;color:#64748b}.title{font-size:30px;line-height:1.15;margin:8px 0 12px}.message{font-size:16px;line-height:1.7;color:#475569;margin:0}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:26px}.button{border:0;border-radius:12px;padding:12px 17px;font:inherit;font-weight:750;cursor:pointer;text-decoration:none}.primary{background:#0f172a;color:white}.secondary{background:#f1f5f9;color:#334155}.ref{margin-top:24px;padding-top:18px;border-top:1px solid #f1f5f9;color:#94a3b8;font-size:12px;word-break:break-all}.note{margin-top:10px;color:#64748b;font-size:13px;line-height:1.5}
    </style>
</head>
<body>
    <main class="card" role="alert" aria-live="polite">
        <div class="icon" aria-hidden="true">!</div>
        <div class="eyebrow">Temporary request problem</div>
        <h1 class="title">We couldn’t complete this request.</h1>
        <p class="message">{{ $message }}</p>
        @if($retryable)
            <p class="note">This looks temporary. Retrying this page is safe because this was a read-only request.</p>
        @endif
        <div class="actions">
            @if($retryable)
                <button class="button primary" type="button" onclick="window.location.reload()">Try Again</button>
            @endif
            <button class="button secondary" type="button" onclick="history.length > 1 ? history.back() : (window.location.href = '/')">Go Back</button>
        </div>
        <div class="ref">Request ID: {{ $requestId }}</div>
    </main>
</body>
</html>
