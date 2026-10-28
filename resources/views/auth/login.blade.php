@extends('layouts.app', ['title' => 'Giriş · Mail MCP'])

@section('content')
    <h1>Mail MCP</h1>
    <p class="muted">Bir yapay zeka istemcisine posta kutularına erişim vermek için giriş yap.</p>

    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <label for="email">E-posta</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email') <div class="err">{{ $message }}</div> @enderror

        <label for="password">Şifre</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <label><input type="checkbox" name="remember" value="1"> Beni hatırla</label>

        <button type="submit">Giriş yap</button>
    </form>
@endsection
