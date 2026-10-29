<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Yönetici: kullanıcı ekleme, silme, şifre sıfırlama, yöneticilik.
 * Yönetici başka kullanıcıların IMAP hesaplarını veya şifrelerini görmez;
 * sadece hesap sayısını görür.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->withCount('mailAccounts')->orderByDesc('is_admin')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'is_admin' => ['boolean'],
        ], [
            'email.unique' => 'Bu e-posta ile bir kullanıcı zaten var.',
        ]);

        $password = $this->generatePassword();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => strtolower($data['email']),
            'password' => $password,
        ]);
        $user->forceFill(['is_admin' => $request->boolean('is_admin')])->save();

        return redirect()->route('users.index')
            ->with('status', "{$user->email} oluşturuldu.")
            ->with('generated_password', ['email' => $user->email, 'password' => $password]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $password = $this->generatePassword();

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            // Açık tarayıcı oturumlarını kapat.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return redirect()->route('users.index')
            ->with('status', "{$user->email} için yeni şifre üretildi; kullanıcının açık oturumları kapatıldı. Bağlı uygulamaları (Claude) etkilenmedi.")
            ->with('generated_password', ['email' => $user->email, 'password' => $password]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Kendi yöneticiliğini kaldıramazsın.');
        }

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? "{$user->email} artık yönetici." : "{$user->email} artık yönetici değil.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Kendi hesabını silemezsin.');
        }

        DB::transaction(function () use ($user) {
            $tokenIds = DB::table('oauth_access_tokens')->where('user_id', $user->id)->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->delete();
            DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->delete(); // mail_accounts cascade ile silinir
        });

        return redirect()->route('users.index')
            ->with('status', "{$user->email} silindi; posta hesap tanımları ve tüm bağlantıları kaldırıldı. Posta kutularındaki maillere dokunulmadı.");
    }

    private function generatePassword(): string
    {
        // Okunması/yazılması kolay: karışan karakterler (0/O, 1/l/I) yok.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];
        for ($g = 0; $g < 4; $g++) {
            $groups[] = collect(range(1, 5))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        }

        return implode('-', $groups); // örn. Xk7pQ-mN3vR-...
    }
}
