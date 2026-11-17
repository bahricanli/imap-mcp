@php($mcpUrl = rtrim(url('/'), '/').'/')
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>imap-mcp</title>
    @include('partials.favicon')
    <meta name="description" content="IMAP posta kutularını Claude, ChatGPT ve Cursor gibi yapay zeka istemcilerine güvenle açan, OAuth 2.1 korumalı MCP sunucusu.">
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#1c1f24; --muted:#6b7280; --line:#e5e7eb; --accent:#2563eb; --accent-text:#fff;
                --warn-bg:#fef3c7; --warn:#92400e; --chip:#eef2ff; --code:#f1f3f6; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#0f1115; --card:#181b21; --text:#e8eaed; --muted:#9aa0a6; --line:#2a2e36; --accent:#60a5fa; --accent-text:#0b1220;
                    --warn-bg:#3a2e12; --warn:#fcd34d; --chip:#1e2433; --code:#11141a; }
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif; }
        a { color:var(--accent); }
        .wrap { max-width:920px; margin:0 auto; padding:0 16px; }
        header.top { display:flex; align-items:center; gap:16px; padding:20px 0; }
        header.top .brand { font-weight:700; margin-right:auto; display:flex; align-items:center; gap:8px; }
        header.top a { color:var(--muted); text-decoration:none; font-size:14px; }
        header.top a:hover { color:var(--text); }
        .brand img { width:28px; height:28px; display:block; }
        .mcp { color:#1a73e8; }
        @media (prefers-color-scheme: dark) { .mcp { color:var(--accent); } }
        .hero { padding:48px 0 40px; }
        .hero h1 { font-size:clamp(30px,5vw,46px); line-height:1.15; margin:0 0 16px; letter-spacing:-0.02em; max-width:18ch; }
        .hero p.lead { font-size:18px; color:var(--muted); max-width:60ch; margin:0 0 28px; }
        .btn { display:inline-block; padding:11px 18px; border-radius:8px; background:var(--accent); color:var(--accent-text);
               font-weight:600; text-decoration:none; border:1px solid transparent; font-size:15px; }
        .btn.secondary { background:transparent; color:var(--text); border-color:var(--line); }
        .actions { display:flex; flex-wrap:wrap; gap:10px; }
        .urlbox { margin-top:28px; display:flex; align-items:center; gap:8px; max-width:560px; background:var(--card);
                  border:1px solid var(--line); border-radius:10px; padding:6px 6px 6px 14px; }
        .urlbox span.lbl { font-size:12px; color:var(--muted); white-space:nowrap; }
        .urlbox code { flex:1; min-width:0; overflow-x:auto; white-space:nowrap; font-size:14px; }
        .urlbox button { border:1px solid var(--line); background:transparent; color:var(--text); border-radius:6px;
                         padding:6px 12px; font:inherit; font-size:13px; cursor:pointer; }
        section { padding:36px 0; border-top:1px solid var(--line); }
        section h2 { font-size:22px; margin:0 0 6px; }
        section > p.muted { color:var(--muted); margin:0 0 20px; max-width:65ch; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px 20px; }
        .card h3 { font-size:16px; margin:0 0 6px; }
        .card p { margin:0; color:var(--muted); font-size:14px; }
        .tools { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media (max-width:640px) { .tools { grid-template-columns:1fr; } }
        .tools ul { list-style:none; margin:0; padding:0; }
        .tools li { padding:9px 0; border-top:1px solid var(--line); font-size:14px; }
        .tools li:first-child { border-top:0; padding-top:0; }
        .tools li code { font-weight:600; }
        .tools li span { display:block; color:var(--muted); }
        .chip { display:inline-block; font-size:12px; padding:1px 8px; border-radius:999px; background:var(--chip); color:var(--muted); margin-left:6px; vertical-align:2px; }
        .chip.write { background:var(--warn-bg); color:var(--warn); }
        code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:13px; }
        pre { background:var(--code); border:1px solid var(--line); border-radius:8px; padding:12px 14px; overflow-x:auto; margin:8px 0 0; }
        ol.steps { margin:0; padding-left:20px; }
        ol.steps li { margin-bottom:14px; }
        ol.steps li:last-child { margin-bottom:0; }
        .example { font-style:italic; color:var(--text); }
        footer { padding:28px 0 40px; border-top:1px solid var(--line); color:var(--muted); font-size:14px;
                 display:flex; flex-wrap:wrap; gap:8px 20px; }
        footer a { color:var(--muted); }
    </style>
</head>
<body>
<div class="wrap">
    <header class="top">
        <span class="brand"><img src="{{ asset('logo.svg') }}" alt="" width="28" height="28"> <span>imap<span class="mcp">-mcp</span></span></span>
        <a href="#araclar">Araçlar</a>
        <a href="#baglan">Bağlan</a>
        @auth
            <a href="{{ route('panel') }}">Panel</a>
        @else
            <a href="{{ route('login') }}">Giriş</a>
        @endauth
    </header>

    <div class="hero">
        <h1>Posta kutun, yapay zeka asistanının elinin altında.</h1>
        <p class="lead">
            imap-mcp, IMAP hesaplarını Claude, ChatGPT ve Cursor gibi istemcilere
            Model Context Protocol üzerinden açar. OAuth 2.1 ile korunur, varsayılan olarak salt-okunurdur ve asla mail göndermez.
        </p>
        <div class="actions">
            @auth
                <a class="btn" href="{{ route('accounts.index') }}">Posta hesaplarım</a>
            @else
                <a class="btn" href="{{ route('login') }}">Giriş yap</a>
            @endauth
            <a class="btn secondary" href="#baglan">Nasıl bağlanırım?</a>
        </div>

        <div class="urlbox">
            <span class="lbl">MCP adresi</span>
            <code id="mcp-url">{{ $mcpUrl }}</code>
            <button type="button" id="copy" data-url="{{ $mcpUrl }}">Kopyala</button>
        </div>
    </div>

    <section>
        <h2>Neden imap-mcp?</h2>
        <p class="muted">Asistanına posta kutunu okuma, özetleme ve düzenleme yetkisi ver; kontrol sende kalsın.</p>
        <div class="grid">
            <div class="card">
                <h3>Varsayılan salt-okunur</h3>
                <p>Taşıma, işaretleme ve silme hesap bazında açılır. Okuma araçları mesajları okundu işaretlemez.</p>
            </div>
            <div class="card">
                <h3>OAuth 2.1 ile bağlantı</h3>
                <p>PKCE ve Dynamic Client Registration hazır. claude.ai'de adresi eklemek yeterli; bağlantıyı panelden istediğin an kesersin.</p>
            </div>
            <div class="card">
                <h3>Birden çok hesap</h3>
                <p>Her kullanıcı birden fazla IMAP hesabı ekleyebilir. Şifreler uygulama anahtarıyla şifreli saklanır.</p>
            </div>
            <div class="card">
                <h3>Sunucu tarafı güvenceler</h3>
                <p>Silme önce Çöp'e taşır, sistem ve dolu klasörler silinemez, tek çağrıda en fazla 50 mesaj işlenir, tüm yazmalar loglanır.</p>
            </div>
        </div>
    </section>

    <section id="araclar">
        <h2>Araçlar</h2>
        <p class="muted">
            Örnek istek: <span class="example">"Okunmamış faturaları oku, özetle ve hepsini Faturalar/2026 klasörüne taşı."</span>
        </p>
        <div class="tools">
            <div class="card">
                <h3>Okuma <span class="chip">her hesapta</span></h3>
                <ul>
                    <li><code>list-accounts</code><span>Hesaplar ve yazma izinleri</span></li>
                    <li><code>list-folders</code><span>Klasörler, toplam ve okunmamış sayısı</span></li>
                    <li><code>search-emails</code><span>Gönderen, alıcı, konu, metin, tarih ve okunmamış filtresiyle arama</span></li>
                    <li><code>get-email</code><span>Başlıklar, düz metin gövde ve ek listesi</span></li>
                </ul>
            </div>
            <div class="card">
                <h3>Yazma <span class="chip write">izin gerekir</span></h3>
                <ul>
                    <li><code>move-emails</code><span>Mesajları başka klasöre taşır</span></li>
                    <li><code>flag-emails</code><span>Okundu / yıldızlı işaretler</span></li>
                    <li><code>create-folder</code><span>Klasör oluşturur (Türkçe karakter destekli)</span></li>
                    <li><code>delete-emails</code><span>Çöp'e taşır; kalıcı silme yalnızca Çöp'ten</span></li>
                    <li><code>delete-folder</code><span>Yalnızca boş, sistem dışı klasörleri siler</span></li>
                </ul>
            </div>
        </div>
    </section>

    <section id="baglan">
        <h2>Bağlan</h2>
        <p class="muted">Hesabın yoksa yöneticinden iste; açık kayıt yok.</p>
        <ol class="steps">
            <li><strong>Giriş yap ve posta hesabını ekle.</strong>
                Gmail, Outlook veya Mailcow için ana şifre yerine uygulama şifresi kullan.
                <a href="{{ route('panel') }}">Panele git →</a></li>
            <li><strong>claude.ai:</strong> Ayarlar → Connectors → Add custom connector → <code>{{ $mcpUrl }}</code>.
                Tarayıcıda giriş ve onay ekranı açılır.</li>
            <li><strong>Claude Code:</strong>
                <pre><code>claude mcp add --transport http mail {{ $mcpUrl }}</code></pre></li>
        </ol>
    </section>

    <footer>
        <span>imap-mcp · GPL-3.0 lisansı</span>
        <a href="https://github.com/bahricanli/imap-mcp">GitHub</a>
        <a href="{{ url('/.well-known/oauth-authorization-server') }}">OAuth keşif</a>
    </footer>
</div>
<script>
    document.getElementById('copy').addEventListener('click', function () {
        var btn = this;
        navigator.clipboard.writeText(btn.dataset.url).then(function () {
            btn.textContent = 'Kopyalandı';
            setTimeout(function () { btn.textContent = 'Kopyala'; }, 1500);
        });
    });
</script>
</body>
</html>
