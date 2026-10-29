<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(12)],
        ], [
            'current_password.required'         => 'Mevcut şifreni yaz.',
            'current_password.current_password' => 'Mevcut şifre hatalı.',
            'password.required'                 => 'Yeni şifreyi yaz.',
            'password.confirmed'                => 'Yeni şifreler eşleşmiyor.',
            'password.min'                      => 'Yeni şifre en az 12 karakter olmalı.',
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        // Diğer tarayıcı oturumlarını kapat (bu oturum açık kalır).
        Auth::logoutOtherDevices($request->input('password'));

        return redirect()->route('password.edit')->with('status', 'Şifren değiştirildi; diğer tarayıcı oturumları kapatıldı.');
    }
}
