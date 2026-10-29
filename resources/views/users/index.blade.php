@extends('layouts.app', ['title' => 'Kullanıcılar · Mail MCP'])

@section('content')
    @if ($generated = session('generated_password'))
        <section class="card" style="border-color:var(--accent)">
            <h2>{{ $generated['email'] }} için şifre</h2>
            <p class="muted" style="margin-bottom:8px">Bu şifre sadece bir kez gösterilir. Kullanıcıya güvenli bir yoldan ilet; giriş yaptıktan sonra Şifre sayfasından değiştirebilir.</p>
            <code style="font-size:18px; letter-spacing:.5px; user-select:all">{{ $generated['password'] }}</code>
            <p class="muted" style="margin:12px 0 0">Giriş adresi: <code>{{ route('login') }}</code></p>
        </section>
    @endif

    <section class="card">
        <div class="actions" style="justify-content:space-between">
            <div>
                <h1>Kullanıcılar</h1>
                <p class="muted" style="margin:0">Mail MCP'ye giriş yapabilen kişiler. Her kullanıcı sadece kendi posta hesaplarını görür.</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn">Kullanıcı ekle</a>
        </div>
    </section>

    <section class="card">
        <ul class="list">
            @foreach ($users as $user)
                @php($self = $user->is(auth()->user()))
                <li>
                    <div class="grow">
                        <div class="title">
                            {{ $user->name }}
                            @if ($user->is_admin) <span class="chip write">yönetici</span> @endif
                            @if ($self) <span class="chip">sen</span> @endif
                        </div>
                        <div class="sub">{{ $user->email }} · {{ $user->mail_accounts_count }} posta hesabı · {{ $user->created_at?->timezone('Europe/Istanbul')->format('d.m.Y') }}</div>
                    </div>
                    @unless ($self)
                        <div class="actions">
                            <form method="POST" action="{{ route('users.reset-password', $user) }}"
                                  onsubmit="return confirm('{{ $user->email }} için yeni şifre üretilsin mi? Mevcut şifresi çalışmayı bırakır.')">
                                @csrf
                                <button type="submit" class="secondary">Şifre sıfırla</button>
                            </form>
                            <form method="POST" action="{{ route('users.toggle-admin', $user) }}">
                                @csrf
                                <button type="submit" class="secondary">{{ $user->is_admin ? 'Yöneticiliği kaldır' : 'Yönetici yap' }}</button>
                            </form>
                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                  onsubmit="return confirm('{{ $user->email }} silinsin mi? Posta hesap tanımları ve bağlı uygulamaları da kaldırılır.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger">Sil</button>
                            </form>
                        </div>
                    @endunless
                </li>
            @endforeach
        </ul>
    </section>
@endsection
