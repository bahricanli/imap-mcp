🇬🇧 English | [🇹🇷 Türkçe](architecture.tr.md)

[← Back to README](../README.md)

# Architecture

```
Claude ──OAuth 2.1 (PKCE + DCR)──▶ Passport ──▶ / (auth:api)
                                                  │
                                    MailServer ── tools ── ImapService ──IMAP/TLS──▶ mail server
                                                  │
                                    mail_accounts (per user, encrypted)
```

## Routes

| Path | What | Defined in |
|---|---|---|
| `POST /` | MCP endpoint (`auth:api`, 60 requests/minute) | `routes/ai.php` |
| `GET /` | Browser: introduction page. `Accept: text/event-stream`: `405` + `Allow: POST` (SSE is not supported) | `routes/ai.php` |
| `/.well-known/oauth-*`, `/oauth/*` | OAuth discovery, Dynamic Client Registration, authorize, token | `Mcp::oauthRoutes()` (Passport) |
| `/login`, `/logout` | Panel login (5 attempts/minute) | `routes/web.php` |
| `/panel/...` | Web panel: accounts, connected apps, password, users | `routes/web.php` |
| `/up` | Health check | Laravel |

## OAuth

- [Laravel Passport](https://laravel.com/docs/passport) is the authorization server.
  `Mcp::oauthRoutes()` from laravel/mcp adds the discovery documents and Dynamic Client Registration.
- Clients register themselves at `/oauth/register`; no manual client setup is needed.
- Access tokens live 12 hours, refresh tokens 30 days (`AppServiceProvider`).
- The consent screen is a plain Blade view (`resources/views/auth/authorize.blade.php`), so no Vite build is
  needed in production.
- Behind a proxy, `trustProxies` (`TRUSTED_PROXIES`, default `*`) and `URL::forceScheme('https')` (when
  `APP_URL` is `https://`) keep every OAuth URL on `https://`.

## MCP server

`App\Mcp\Servers\MailServer` registers the tools in `app/Mcp/Tools`. Its instructions tell the model to
write only on the user's explicit request and to treat email content as untrusted data.

Write tools (`create-folder`, `move-emails`, `flag-emails`, `delete-emails`, `delete-folder`) are only
registered when the user has at least one account with write access (`UsesImap::shouldRegister`). Users
without write access never see them.

## IMAP

`App\Services\ImapService` wraps [webklex/php-imap](https://github.com/Webklex/php-imap):

- Every tool call opens its own IMAP connection and closes it afterwards. Simple and stateless.
- Folder names are handled in UTF-8 for the AI and in UTF7-IMAP towards the server.
- MIME-encoded subjects and sender names (RFC 2047) are decoded; search results are sorted newest first.
- Without criteria, a search covers the last 7 days. Bodies are cut at 20,000 characters.
- At most 50 messages per write call (`MAX_BATCH`).
- Deleting moves to Trash; permanent deletion only works on messages already in Trash. System folders and
  non-empty folders cannot be deleted.
- Every write is logged as `mail-mcp: <action>` with user, account, folder and UIDs.

## Data

SQLite in `storage/database.sqlite`:

| Table | Contents |
|---|---|
| `users` | Panel users; `is_admin` flag |
| `mail_accounts` | Per user: label, host, port, encryption, certificate check, username, **encrypted** password (`APP_KEY`), write access, trash folder, default flag |
| `oauth_*` | Passport clients, auth codes, access and refresh tokens |
| `sessions`, `cache`, `jobs` | Laravel defaults (database drivers) |

## Panel

Plain Blade views with inline CSS (`resources/views/layouts/app.blade.php`), light and dark mode, no
JavaScript build step. Admin-only pages are protected by the `admin` gate.
