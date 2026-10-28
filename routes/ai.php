<?php

use App\Mcp\Servers\MailServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth 2.1 keşif + Dynamic Client Registration rotaları (Passport).
// claude.ai custom connector bunu kullanır.
Mcp::oauthRoutes();

Mcp::web('/mcp/mail', MailServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);

// Claude Code / API için sabit token istersen Passport yerine:
// Mcp::web('/mcp/mail', MailServer::class)->middleware('auth:sanctum');
