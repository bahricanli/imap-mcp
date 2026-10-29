@extends('layouts.app', ['title' => 'Kullanıcı ekle · Mail MCP'])

@section('content')
    <section class="card" style="max-width:460px">
        <h1>Kullanıcı ekle</h1>
        <p class="muted">Şifre otomatik üretilir ve kaydettikten sonra bir kez gösterilir.</p>

        <form method="POST" action="{{ route('users.store') }}" autocomplete="off">
            @csrf

            <label for="name">Ad soyad</label>
            <input id="name" name="name" type="text" required maxlength="120" value="{{ old('name') }}">
            @error('name') <div class="err">{{ $message }}</div> @enderror

            <label for="email">E-posta</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}">
            <div class="hint">Giriş için kullanılır; posta kutusu adresi olmak zorunda değil.</div>
            @error('email') <div class="err">{{ $message }}</div> @enderror

            <input type="hidden" name="is_admin" value="0">
            <label class="check">
                <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin'))>
                <span>Yönetici (kullanıcı ekleyip silebilir)</span>
            </label>

            <div class="actions" style="margin-top:20px">
                <button type="submit">Oluştur</button>
                <a href="{{ route('users.index') }}" class="btn secondary">Vazgeç</a>
            </div>
        </form>
    </section>
@endsection
