<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

// Kök adres (/) MCP sunucusuna ayrılmış (routes/ai.php); web arayüzü /panel altında.
Route::prefix('panel')->group(function () {
    Route::get('/', fn () => auth()->check()
        ? redirect()->route('accounts.index')
        : redirect()->route('login'))->name('panel');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::resource('accounts', MailAccountController::class)->except('show');
        Route::post('accounts/{account}/test', [MailAccountController::class, 'test'])
            ->middleware('throttle:10,1')->name('accounts.test');

        Route::get('connections', [ConnectionController::class, 'index'])->name('connections.index');
        Route::delete('connections/{client}', [ConnectionController::class, 'destroy'])->name('connections.destroy');

        Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    });
});

// Eski yollar (ilk sürüm) → yeni yerleri.
Route::redirect('/login', '/panel/login');
Route::redirect('/accounts', '/panel/accounts');
