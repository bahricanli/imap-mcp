@extends('layouts.app')

@section('content')
    <h1>Merhaba, {{ auth()->user()->name }}</h1>
    <p class="muted">MCP adresi: <code>{{ url('/mcp/mail') }}</code></p>

    <strong>Posta hesapların</strong>
    @if ($accounts->isEmpty())
        <p class="muted">Henüz hesap yok. Sunucuda <code>php artisan mail:add-account {{ auth()->user()->email }}</code> çalıştır.</p>
    @else
        <ul>
            @foreach ($accounts as $account)
                <li><code>{{ $account->label }}</code> · {{ $account->username }} @if ($account->is_default) (varsayılan) @endif · {{ $account->allow_write ? 'okuma + yazma' : 'salt okuma' }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="link">Çıkış yap</button>
    </form>
@endsection
