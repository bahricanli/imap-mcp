<?php

namespace App\Providers;

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
        // MCP istemcisi bağlanırken gösterilen onay ekranı (laravel/mcp paketinin hazır görünümü).
        Passport::authorizationView(fn (array $parameters) => view('mcp::authorize', $parameters));

        Passport::tokensExpireIn(now()->addHours(12));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
