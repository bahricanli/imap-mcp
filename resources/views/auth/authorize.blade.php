{{--
    OAuth onay ekranı (Passport). laravel/mcp'nin hazır görünümü Vite derlemesi istediği için
    kendi layout'umuzla yeniden yazıldı. Değişkenler: $client, $user, $scopes, $request, $authToken.
--}}
@extends('layouts.app', ['title' => 'Erişim izni · Mail MCP', 'bare' => true])

@section('content')
    @php($redirectUri = request()->query('redirect_uri', $client->redirect_uris[0] ?? null))
    @php($redirectHost = $redirectUri ? parse_url($redirectUri, PHP_URL_HOST) : null)

    <h1>{{ $client->name }} erişim istiyor</h1>
    <p class="muted">
        <strong>{{ $client->name }}</strong> uygulaması, Mail MCP üzerinden posta hesaplarına erişmek istiyor.
    </p>

    <div class="flash" style="margin-bottom:12px">
        <div class="muted" style="font-size:13px">Hesap</div>
        <div>{{ $user->name }} · {{ $user->email }}</div>
    </div>

    <ul style="margin:0 0 12px; padding-left:18px; font-size:14px">
        <li>Posta klasörlerini ve maillerini okuyabilir.</li>
        @if ($user->mailAccounts()->where('allow_write', true)->exists())
            <li>Yazma izni olan hesaplarda mail taşıyabilir, işaretleyebilir, klasör oluşturup silebilir ve Çöp'e atabilir.</li>
        @endif
        <li>IMAP şifrelerini ve Mail MCP şifreni <strong>göremez</strong>.</li>
        <li>Erişimi istediğin zaman Panel → Bağlı uygulamalar'dan kesebilirsin.</li>
    </ul>

    @if ($redirectHost)
        <p class="muted" style="font-size:13px; margin-bottom:0">Onaylarsan <code>{{ $redirectHost }}</code> adresine yönlendirileceksin.</p>
    @endif

    <div class="actions" style="margin-top:18px; flex-wrap:nowrap">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}" style="flex:1; margin:0">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="secondary" style="width:100%; margin:0">Reddet</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" style="flex:1; margin:0">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" style="width:100%; margin:0">İzin ver</button>
        </form>
    </div>
@endsection
