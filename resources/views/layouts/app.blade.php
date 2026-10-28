<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mail MCP' }}</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#1c1f24; --muted:#6b7280; --line:#e5e7eb; --accent:#2563eb; --err:#b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0f1115; --card:#181b21; --text:#e8eaed; --muted:#9aa0a6; --line:#2a2e36; --accent:#60a5fa; --err:#f87171; } }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:16px;
               background:var(--bg); color:var(--text); font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; }
        .card { width:100%; max-width:420px; background:var(--card); border:1px solid var(--line); border-radius:12px; padding:28px; }
        h1 { font-size:20px; margin:0 0 4px; } p.muted { color:var(--muted); margin:0 0 20px; }
        label { display:block; font-size:13px; margin:14px 0 6px; color:var(--muted); }
        input[type=email], input[type=password] { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:transparent; color:inherit; font:inherit; }
        button { margin-top:18px; width:100%; padding:10px; border:0; border-radius:8px; background:var(--accent); color:#fff; font:inherit; font-weight:600; cursor:pointer; }
        .err { color:var(--err); font-size:13px; margin-top:6px; }
        ul { padding-left:18px; } code { font-size:13px; }
        .link { background:none; color:var(--accent); padding:0; width:auto; font-weight:400; }
    </style>
</head>
<body>
    <main class="card">
        @yield('content')
    </main>
</body>
</html>
