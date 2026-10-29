<?php

use App\Mcp\Servers\MailServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 keşif + Dynamic Client Registration rotaları (Passport).
Mcp::oauthRoutes();

// MCP sunucusu kök adreste: https://alanadin/
// Web arayüzü /panel altında (routes/web.php).
Mcp::web('/', MailServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);

// Mcp::web GET / için 405 kaydeder (SSE dinleme desteklenmiyor). Bunu ezip:
// MCP istemcisi SSE isterse yine 405, tarayıcıdan gelen ziyaretçiyi panele yönlendir.
Route::get('/', function (Request $request) {
    if (str_contains((string) $request->header('Accept'), 'text/event-stream')) {
        return response('', 405)->header('Allow', 'POST');
    }

    return redirect('/panel');
});

// Claude Code / API için sabit token istersen Passport yerine:
// Mcp::web('/', MailServer::class)->middleware('auth:sanctum');
