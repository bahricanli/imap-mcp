@extends('layouts.app', ['title' => 'Şifre · Mail MCP'])

@section('content')
    <section class="card" style="max-width:460px">
        <h1>Şifre değiştir</h1>
        <p class="muted">Bu, Mail MCP'ye giriş şifren ({{ auth()->user()->email }}). Posta hesaplarının şifreleri ayrı.</p>

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <label for="current_password">Mevcut şifre</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
            @error('current_password') <div class="err">{{ $message }}</div> @enderror

            <label for="password">Yeni şifre</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <div class="hint">En az 12 karakter.</div>
            @error('password') <div class="err">{{ $message }}</div> @enderror

            <label for="password_confirmation">Yeni şifre (tekrar)</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

            <button type="submit">Şifreyi değiştir</button>
        </form>
    </section>
@endsection
