🇬🇧 English | [🇹🇷 Türkçe](development.tr.md)

[← Back to README](../README.md)

# Development

## Requirements

- PHP 8.3+ with `mbstring`, `pdo_sqlite`, `openssl`
- Composer 2
- Docker (only for the image and the smoke test)

The IMAP extension is not needed: webklex/php-imap speaks IMAP over sockets.

## Local set-up

```bash
composer install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

```bash
php artisan passport:keys
```

```bash
touch database/database.sqlite && php artisan migrate
```

```bash
php artisan mail:create-user
```

```bash
php artisan serve
```

For local work set `APP_ENV=local`, `APP_DEBUG=true` and `APP_URL=http://localhost:8000` in `.env`, and remove
`SESSION_SECURE_COOKIE=true`, otherwise the login cookie is not sent over plain HTTP.

## Tests

```bash
php artisan test
```

The tests use an in-memory SQLite database and a mocked `ImapService` (Mockery); no mail server is needed. CI runs them on
every push and pull request ([`tests.yml`](../.github/workflows/tests.yml)).

| File | Covers |
|---|---|
| `tests/Feature/MailToolsTest.php` | Read tools |
| `tests/Feature/WriteToolsTest.php` | Write tools, limits, trash rules |
| `tests/Feature/OAuthFlowTest.php` | Discovery, registration, authorize, token, MCP call |
| `tests/Feature/AccountsUiTest.php`, `UserAdminTest.php`, `WebAuthTest.php` | Web panel |
| `tests/Unit/ImapServiceRulesTest.php` | IMAP service rules |

Docker image smoke test:

```bash
docker build -t imap-mcp:test .
```

```bash
APP_PORT=8090 bash scripts/smoke-test.sh imap-mcp:test
```

## Code style

[Laravel Pint](https://laravel.com/docs/pint):

```bash
vendor/bin/pint
```

## Project layout

| Path | Contents |
|---|---|
| `app/Mcp/Servers/MailServer.php` | MCP server, instructions, tool list |
| `app/Mcp/Tools/` | One class per tool; `Concerns/UsesImap.php` shared connection handling |
| `app/Services/ImapService.php` | All IMAP logic and safety rules |
| `app/Http/Controllers/` | Web panel |
| `app/Console/Commands/` | `mail:*` commands |
| `resources/views/` | Blade views |
| `routes/ai.php` | MCP and OAuth routes |
| `routes/web.php` | Panel routes |
| `docker/`, `Dockerfile`, `docker-compose*.yml` | Container set-up |

## Releasing

1. Update [CHANGELOG.md](../CHANGELOG.md): move `[Unreleased]` items under a new version.
2. Commit and push to `main`.
3. Tag and push: `git tag -a v1.2.3 -m v1.2.3` then `git push origin v1.2.3`.
4. CI publishes `1.2.3`, `1.2`, `1` and `latest` to GHCR and Docker Hub.
5. Check that every tag points to the same multi-platform digest:

   ```bash
   docker buildx imagetools inspect ghcr.io/bahricanli/imap-mcp:1.2.3
   ```
