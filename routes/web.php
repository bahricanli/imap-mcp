<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('accounts.index')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::resource('accounts', MailAccountController::class)->except('show');
    Route::post('accounts/{account}/test', [MailAccountController::class, 'test'])
        ->middleware('throttle:10,1')->name('accounts.test');

    Route::get('connections', [ConnectionController::class, 'index'])->name('connections.index');
    Route::delete('connections/{client}', [ConnectionController::class, 'destroy'])->name('connections.destroy');

    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});
