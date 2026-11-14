🇬🇧 English | [🇹🇷 Türkçe](docker-install.tr.md)

[← Back to README](../README.md)

# Docker installation

Two ways to run imap-mcp with Docker:

- **A. Pre-built image:** no build, the fastest way.
- **B. Build from source:** clone the repository and build the image yourself.

Both use the same `.env` file and the same Nginx Proxy Manager setup.

## Requirements

- Docker Engine 24+ with the Compose plugin (`docker compose version`).
- A domain name pointing to the server, for example `mail-mcp.example.com`.
- A reverse proxy with HTTPS. The compose files assume [Nginx Proxy Manager](https://nginxproxymanager.com/)
  (NPM) on the same host and attach the container to NPM's Docker network. OAuth clients such as claude.ai
  require HTTPS.
- An IMAP account for every mailbox you want to expose, ideally with an **app password**.

## Keys and settings

imap-mcp calls no paid third-party API. The only secrets are:

| Setting | Required | What it is | Where it comes from |
|---|---|---|---|
| `APP_KEY` | **Yes** | Laravel encryption key. IMAP passwords are encrypted with it. The container refuses to start without it. | Generate it once: `echo "base64:$(openssl rand -base64 32)"`. **Back it up.** If you lose it, stored IMAP passwords cannot be decrypted. |
| `APP_URL` | **Yes** | Public HTTPS address, exactly as entered in NPM. OAuth discovery URLs are built from it. | Your domain, e.g. `https://mail-mcp.example.com` |
| `NPM_NETWORK` | Yes (with NPM) | Name of the Docker network NPM is attached to. | `docker inspect <npm-container> -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}'` |
| `APP_PORT` | No (default `8080`) | Port nginx listens on inside the container. NPM's Forward Port must match. `9000` is not allowed (PHP-FPM uses it). | Any free port |
| `TRUSTED_PROXIES` | No (default `*`) | Proxies whose `X-Forwarded-*` headers are trusted. `*` is safe while the container port is not published. | NPM's IP or subnet if you publish the port |
| `IMAGE`, `VERSION` | No | Only for `docker-compose.release.yml`: which image and tag to run. | `IMAGE=bmericc/imap-mcp`, `VERSION=1.0.0` |
| `AUTORUN_LARAVEL_MIGRATION` | No (default `true`) | Set to `false` to skip automatic migrations at start-up. | — |
| IMAP password | Per account | Entered in the web panel or with `mail:add-account`, stored encrypted. Never sent to the AI. | Your mail provider. Use an app password for Gmail, Outlook and Mailcow. |

Passport OAuth keys (`storage/oauth-*.key`) are generated automatically on first start and kept in the
`storage` volume.

### OAuth callbacks

No callback URL needs to be registered. AI clients register themselves through Dynamic Client Registration
(`/oauth/register`) and send their own redirect URI. Every HTTPS redirect domain is allowed by default.

## A. Pre-built image

Images are published for `linux/amd64` and `linux/arm64`:

- `ghcr.io/bahricanli/imap-mcp`
- `bmericc/imap-mcp` (Docker Hub)

Tags: `1.2.3`, `1.2`, `1`, `latest`.

1. Create a directory:

   ```bash
   mkdir imap-mcp && cd imap-mcp
   ```

2. Download the compose file:

   ```bash
   curl -O https://raw.githubusercontent.com/bahricanli/imap-mcp/main/docker-compose.release.yml
   ```

3. Download the example settings:

   ```bash
   curl -o .env https://raw.githubusercontent.com/bahricanli/imap-mcp/main/.env.example
   ```

4. Generate an `APP_KEY`:

   ```bash
   echo "base64:$(openssl rand -base64 32)"
   ```

5. Edit `.env`: set `APP_KEY`, `APP_URL`, `NPM_NETWORK` and, if needed, `APP_PORT`.
   To pin a version or use Docker Hub, add `VERSION=1.0.0` and/or `IMAGE=bmericc/imap-mcp`.

6. Start it:

   ```bash
   docker compose -f docker-compose.release.yml up -d
   ```

7. Create the first user (automatically an admin):

   ```bash
   docker compose -f docker-compose.release.yml exec mail-mcp php artisan mail:create-user
   ```

8. Set up the [proxy host](#nginx-proxy-manager), sign in at `https://your-domain/panel` and add a mail account.

## B. Build from source

1. Clone the repository:

   ```bash
   git clone https://github.com/bahricanli/imap-mcp.git && cd imap-mcp
   ```

2. Create `.env`:

   ```bash
   cp .env.example .env
   ```

3. Generate an `APP_KEY` and put it in `.env` together with `APP_URL` and `NPM_NETWORK`:

   ```bash
   echo "base64:$(openssl rand -base64 32)"
   ```

4. Build and start (the image is tagged `imap-mcp:local`):

   ```bash
   docker compose up -d --build
   ```

5. Create the first user:

   ```bash
   docker compose exec mail-mcp php artisan mail:create-user
   ```

## Nginx Proxy Manager

The container port is not published. NPM reaches the container over the Docker network as `mail-mcp`.

Create a Proxy Host:

| Field | Value |
|---|---|
| Domain Names | `mail-mcp.example.com` (same as `APP_URL`) |
| Scheme | `http` |
| Forward Hostname / IP | `mail-mcp` |
| Forward Port | `APP_PORT` from `.env` (default `8080`) |
| SSL | Let's Encrypt, **Force SSL**, HTTP/2 |

No extra settings are needed. The app trusts NPM's `X-Forwarded-Proto` / `X-Forwarded-Host` headers and
builds OAuth URLs with `https://`.

If NPM runs on another host, uncomment the `ports:` section in the compose file and point NPM at the server's
IP. Then restrict `TRUSTED_PROXIES` to NPM's address.

Check the result:

```bash
curl https://mail-mcp.example.com/.well-known/oauth-authorization-server
```

Every URL in the output must start with `https://mail-mcp.example.com/`.

## Updating

Pre-built image:

```bash
docker compose -f docker-compose.release.yml pull
```

```bash
docker compose -f docker-compose.release.yml up -d
```

From source:

```bash
git pull --ff-only
```

```bash
docker compose up -d --build
```

Migrations run automatically on start-up. Read the [changelog](../CHANGELOG.md) before a major version upgrade.

## Backups

All state is in the `storage` volume: the SQLite database (users, mail accounts, OAuth tokens) and the
Passport keys. Back up the volume **and** your `.env` (`APP_KEY`).

```bash
docker run --rm -v imap-mcp_storage:/data -v "$PWD":/backup alpine tar czf /backup/imap-mcp-storage.tgz -C /data .
```

The volume name is `<project>_storage`, where the project is the directory name. List volumes with
`docker volume ls` if it differs.

Restore into an empty volume with the container stopped:

```bash
docker run --rm -v imap-mcp_storage:/data -v "$PWD":/backup alpine tar xzf /backup/imap-mcp-storage.tgz -C /data
```

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Container exits with `APP_KEY tanımlı değil` | `APP_KEY` is missing from `.env`. Generate one (see above). Do not change it later. |
| Container exits with `APP_PORT=9000 kullanılamaz` | Port 9000 is PHP-FPM's inside the container. Pick another `APP_PORT`. |
| `network npm_default declared as external, but could not be found` | `NPM_NETWORK` is wrong. Find the real name with the `docker inspect` command above. |
| NPM shows `502 Bad Gateway` | Forward Port differs from `APP_PORT`, or the container is not on NPM's network. |
| OAuth URLs start with `http://` | `APP_URL` does not start with `https://`, or the proxy does not send `X-Forwarded-Proto`. |
| claude.ai: "couldn't connect" | Use the root URL (`https://your-domain/`), not `/panel`. Check the `curl` test above. |
| `Bağlanılamadı` when adding an account | Wrong IMAP host, port or encryption, or the provider requires an app password. Try "Bağlantıyı test et" in the panel. |
| Stored accounts stopped working after a rebuild | `APP_KEY` changed. Restore the old key. |

Logs:

```bash
docker compose logs -f mail-mcp
```
