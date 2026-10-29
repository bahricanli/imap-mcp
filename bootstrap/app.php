<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nginx Proxy Manager / Traefik arkasında: X-Forwarded-Proto/Host başlıklarına güven,
        // böylece OAuth keşif adresleri https:// ve doğru alan adıyla üretilir.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // MCP istemcileri (kök adres) her durumda 401 + WWW-Authenticate almalı (login'e yönlendirme değil),
            // yoksa OAuth keşfi başlamaz.
            fn (Request $request) => $request->path() === '/' || $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
