@php($editing = $account->exists)
@extends('layouts.app', ['title' => ($editing ? 'Hesabı düzenle' : 'Hesap ekle').' · Mail MCP'])

@section('content')
    <section class="card">
        <h1>{{ $editing ? "'{$account->label}' hesabını düzenle" : 'Posta hesabı ekle' }}</h1>
        <p class="muted">Kaydetmeden önce IMAP bağlantısı test edilir. Şifre sunucuda şifreli saklanır ve yapay zekaya hiç gönderilmez.</p>

        <form method="POST" action="{{ $editing ? route('accounts.update', $account) : route('accounts.store') }}" autocomplete="off">
            @csrf
            @if ($editing) @method('PUT') @endif

            <label for="label">Label</label>
            <input id="label" name="label" type="text" required maxlength="40" placeholder="kalehost"
                   value="{{ old('label', $account->label) }}">
            <div class="hint">Kısa bir ad; yapay zeka hesabı bununla seçer (küçük harf, rakam, - . _).</div>
            @error('label') <div class="err">{{ $message }}</div> @enderror

            <fieldset>
                <legend>Sunucu</legend>
                <div class="row-3">
                    <div>
                        <label for="host">IMAP sunucusu</label>
                        <input id="host" name="host" type="text" required placeholder="mail.kalehost.net"
                               value="{{ old('host', $account->host) }}">
                        @error('host') <div class="err">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="port">Port</label>
                        <input id="port" name="port" type="number" required min="1" max="65535"
                               value="{{ old('port', $account->port) }}">
                        @error('port') <div class="err">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="encryption">Şifreleme</label>
                        <select id="encryption" name="encryption">
                            @foreach (['ssl' => 'SSL/TLS (993)', 'starttls' => 'STARTTLS (143)', 'tls' => 'TLS', 'none' => 'Yok'] as $value => $text)
                                <option value="{{ $value }}" @selected(old('encryption', $account->encryption) === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <input type="hidden" name="validate_cert" value="0">
                <label class="check">
                    <input type="checkbox" name="validate_cert" value="1" @checked(old('validate_cert', $account->validate_cert))>
                    <span>TLS sertifikasını doğrula <span class="hint">(sadece kendinden imzalı iç sunucularda kapat)</span></span>
                </label>
            </fieldset>

            <fieldset>
                <legend>Giriş</legend>
                <div class="row">
                    <div>
                        <label for="username">Kullanıcı adı</label>
                        <input id="username" name="username" type="text" required autocomplete="off" placeholder="bahri@bahri.info"
                               value="{{ old('username', $account->username) }}">
                        @error('username') <div class="err">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="password">Şifre</label>
                        <input id="password" name="password" type="password" autocomplete="new-password"
                               @if (! $editing) required @endif
                               placeholder="{{ $editing ? 'Değiştirmek için yaz' : 'Uygulama şifresi önerilir' }}">
                        @error('password') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="hint">Mailcow / Gmail / Outlook'ta ana şifre yerine bu iş için ayrı bir uygulama şifresi oluştur.</div>
            </fieldset>

            <fieldset>
                <legend>Yetkiler</legend>
                <input type="hidden" name="allow_write" value="0">
                <label class="check">
                    <input type="checkbox" name="allow_write" value="1" id="allow_write" @checked(old('allow_write', $account->allow_write))>
                    <span>Yazma izni: klasör oluşturma, mail taşıma, işaretleme ve silme</span>
                </label>
                <div class="warn">
                    Silme önce Çöp'e taşır; kalıcı silme sadece Çöp klasöründen yapılır. Sistem klasörleri ve dolu klasörler silinemez.
                    Gelen bir mail yapay zekaya talimat vermeye çalışabilir; yazma iznini sadece gerçekten kullanacağın hesapta aç.
                </div>

                <label for="trash_folder">Çöp klasörü <span class="hint">(boş bırakılırsa Trash / Çöp / Deleted Items aranır)</span></label>
                <input id="trash_folder" name="trash_folder" type="text" placeholder="INBOX/Trash"
                       value="{{ old('trash_folder', $account->trash_folder) }}">
                @error('trash_folder') <div class="err">{{ $message }}</div> @enderror

                <input type="hidden" name="is_default" value="0">
                <label class="check">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $account->is_default))>
                    <span>Varsayılan hesap (yapay zeka hesap belirtmezse bu kullanılır)</span>
                </label>
            </fieldset>

            <div class="actions" style="margin-top:20px">
                <button type="submit">{{ $editing ? 'Kaydet' : 'Test et ve kaydet' }}</button>
                <a href="{{ route('accounts.index') }}" class="btn secondary">Vazgeç</a>
            </div>
        </form>
    </section>
@endsection
