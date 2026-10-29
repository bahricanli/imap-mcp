<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Proxy başlığı eksik gelse bile APP_URL https ise tüm URL'ler https üretilsin.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // MCP istemcisi bağlanırken gösterilen onay ekranı (laravel/mcp paketinin hazır görünümü).
        Passport::authorizationView(fn (array $parameters) => view('mcp::authorize', $parameters));

        Passport::tokensExpireIn(now()->addHours(12));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
