@extends('layouts.app', ['title' => 'Posta hesapları · Mail MCP'])

@section('content')
    <section class="card">
        <div class="actions" style="justify-content:space-between">
            <div>
                <h1>Posta hesapları</h1>
                <p class="muted" style="margin:0">Yapay zekanın erişebileceği IMAP kutuları.</p>
            </div>
            <a href="{{ route('accounts.create') }}" class="btn">Hesap ekle</a>
        </div>
    </section>

    <section class="card">
        @if ($accounts->isEmpty())
            <p class="muted" style="margin:0">Henüz hesap yok. <a href="{{ route('accounts.create') }}">İlk hesabını ekle</a>; kaydetmeden önce bağlantı test edilir.</p>
        @else
            <ul class="list">
                @foreach ($accounts as $account)
                    <li>
                        <div class="grow">
                            <div class="title">
                                {{ $account->label }}
                                @if ($account->is_default) <span class="chip">varsayılan</span> @endif
                                @if ($account->allow_write)
                                    <span class="chip write">okuma + yazma</span>
                                @else
                                    <span class="chip">salt okuma</span>
                                @endif
                            </div>
                            <div class="sub">{{ $account->username }} · {{ $account->host }}:{{ $account->port }} ({{ $account->encryption }})</div>
                        </div>
                        <div class="actions">
                            <form method="POST" action="{{ route('accounts.test', $account) }}">
                                @csrf
                                <button type="submit" class="secondary">Bağlantıyı test et</button>
                            </form>
                            <a href="{{ route('accounts.edit', $account) }}" class="btn secondary">Düzenle</a>
                            <form method="POST" action="{{ route('accounts.destroy', $account) }}"
                                  onsubmit="return confirm('{{ $account->label }} hesabı Mail MCP\'den kaldırılsın mı? Posta kutusundaki maillere dokunulmaz.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger">Sil</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2>Claude'a bağlama</h2>
        <p class="muted" style="margin-bottom:8px">claude.ai → Ayarlar → Connectors → Add custom connector bölümüne şu adresi ekle:</p>
        <code>{{ url('/mcp/mail') }}</code>
    </section>
@endsection
