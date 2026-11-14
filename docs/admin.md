🇬🇧 English | [🇹🇷 Türkçe](admin.tr.md)

[← Back to README](../README.md)

# Administration

Most tasks can be done in the [web panel](features.md). The Artisan commands below cover the first set-up and
scripted changes.

In Docker, prefix every command with `docker compose exec mail-mcp` (add `-f docker-compose.release.yml` when
using the pre-built image):

```bash
docker compose exec mail-mcp php artisan mail:create-user
```

## Commands

| Command | What it does |
|---|---|
| `php artisan mail:create-user [--admin]` | Asks for name, email and password (at least 12 characters) and creates a panel user. The first user is always an admin; `--admin` makes later users admins too. |
| `php artisan mail:add-account <user-email>` | Adds an IMAP account to a user. Asks for label, host, port, encryption, certificate check, write access, username and password, then tests the connection before saving. The user's first account becomes the default. |
| `php artisan mail:set-write <user-email> <label> on\|off [--trash=PATH]` | Turns write access on or off for one account. `--trash` sets the trash folder path; an empty value means auto-detect. |
| `php artisan mcp:inspector /` | Opens the MCP Inspector against the local server for testing. |

Examples:

```bash
php artisan mail:set-write you@example.com work on
```

```bash
php artisan mail:set-write you@example.com work on --trash="INBOX/Trash"
```

```bash
php artisan mail:set-write you@example.com work off
```

The trash folder is detected automatically from names such as `Trash`, `Deleted Items`, `Çöp` and `Çöp Kutusu`.

## Users and roles

- **Users** see and manage only their own mail accounts and connected apps.
- **Admins** can also add users, reset passwords, grant or remove the admin role and delete users in
  **Kullanıcılar** (Users). Admins never see other users' IMAP details.
- There is no open sign-up.

## Revoking access

- One app: **Bağlı uygulamalar** (Connected apps) → **Bağlantıyı kes** (Disconnect) revokes all of its tokens.
- One user: an admin deletes the user, which also removes their accounts and connected apps.
- Everyone: rotating the Passport keys (delete `storage/oauth-*.key` and restart) invalidates every token.
  Do **not** rotate `APP_KEY` for this; it would make stored IMAP passwords unreadable.

## Logs

Logs go to stderr in Docker:

```bash
docker compose logs -f mail-mcp
```

Every write action is logged with the user, account, folder and UIDs, for example
`mail-mcp: move {"user_id":1,"account":"work","from":"INBOX","to":"Invoices/2026","uids":[…]}`.
