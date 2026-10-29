<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mail MCP' }}</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#1c1f24; --muted:#6b7280; --line:#e5e7eb; --accent:#2563eb; --accent-text:#fff;
                --err:#b91c1c; --ok:#15803d; --warn-bg:#fef3c7; --warn:#92400e; --chip:#eef2ff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#0f1115; --card:#181b21; --text:#e8eaed; --muted:#9aa0a6; --line:#2a2e36; --accent:#60a5fa; --accent-text:#0b1220;
                    --err:#f87171; --ok:#4ade80; --warn-bg:#3a2e12; --warn:#fcd34d; --chip:#1e2433; }
        }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; padding:16px; background:var(--bg); color:var(--text);
               font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; }
        body.center { display:grid; place-items:center; }
        .wrap { max-width:760px; margin:0 auto; }
        .card { width:100%; background:var(--card); border:1px solid var(--line); border-radius:12px; padding:24px; margin-bottom:16px; }
        body.center .card { max-width:420px; padding:28px; }
        nav { display:flex; flex-wrap:wrap; align-items:center; gap:4px 16px; margin:4px 0 16px; }
        nav .brand { font-weight:700; margin-right:auto; }
        nav a { color:var(--muted); text-decoration:none; font-size:14px; }
        nav a.active, nav a:hover { color:var(--text); }
        h1 { font-size:20px; margin:0 0 4px; } h2 { font-size:16px; margin:0 0 12px; }
        p.muted, .muted { color:var(--muted); } p.muted { margin:0 0 16px; }
        label { display:block; font-size:13px; margin:14px 0 6px; color:var(--muted); }
        label.check { display:flex; gap:8px; align-items:flex-start; color:var(--text); font-size:14px; }
        label.check input { margin-top:4px; }
        .hint { font-size:12px; color:var(--muted); margin-top:4px; }
        input[type=email], input[type=password], input[type=text], input[type=number], select {
            width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:transparent; color:inherit; font:inherit; }
        select option { background:var(--card); }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
        .row-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:0 16px; }
        @media (max-width:560px) { .row, .row-3 { grid-template-columns:1fr; } }
        .btn, button { display:inline-block; margin-top:18px; padding:10px 16px; border:0; border-radius:8px; background:var(--accent);
                       color:var(--accent-text); font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
        body.center button { width:100%; }
        .btn.secondary, button.secondary { background:transparent; color:var(--text); border:1px solid var(--line); }
        button.danger { background:transparent; color:var(--err); border:1px solid var(--line); }
        .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .actions > * { margin-top:0; }
        .err { color:var(--err); font-size:13px; margin-top:6px; }
        .flash { padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; border:1px solid var(--line); }
        .flash.ok { color:var(--ok); } .flash.error { color:var(--err); }
        .warn { background:var(--warn-bg); color:var(--warn); padding:10px 14px; border-radius:8px; font-size:13px; margin-top:12px; }
        .chip { display:inline-block; font-size:12px; padding:1px 8px; border-radius:999px; background:var(--chip); color:var(--muted); }
        .chip.write { color:var(--warn); background:var(--warn-bg); }
        .list { list-style:none; margin:0; padding:0; }
        .list li { padding:14px 0; border-top:1px solid var(--line); display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; }
        .list li:first-child { border-top:0; padding-top:0; }
        .list .grow { flex:1 1 240px; min-width:0; }
        .list .title { font-weight:600; overflow-wrap:anywhere; }
        .list .sub { font-size:13px; color:var(--muted); overflow-wrap:anywhere; }
        code { font-size:13px; overflow-wrap:anywhere; }
        .link { background:none; color:var(--accent); padding:0; width:auto; font-weight:400; border:0; margin:0; }
        fieldset { border:1px solid var(--line); border-radius:8px; padding:4px 16px 16px; margin:18px 0 0; }
        legend { font-size:13px; color:var(--muted); padding:0 6px; }
    </style>
</head>
<body class="{{ auth()->check() ? '' : 'center' }}">
    @auth
        <div class="wrap">
            <nav>
                <span class="brand">Mail MCP</span>
                <a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}">Posta hesapları</a>
                <a href="{{ route('connections.index') }}" class="{{ request()->routeIs('connections.*') ? 'active' : '' }}">Bağlı uygulamalar</a>
                <a href="{{ route('password.edit') }}" class="{{ request()->routeIs('password.*') ? 'active' : '' }}">Şifre</a>
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="link" style="font-size:14px">Çıkış</button>
                </form>
            </nav>

            @if (session('status'))
                <div class="flash ok">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="flash error">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    @else
        <main class="card">
            @yield('content')
        </main>
    @endauth
</body>
</html>
