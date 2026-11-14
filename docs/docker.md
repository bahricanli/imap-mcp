🇬🇧 English | [🇹🇷 Türkçe](docker.tr.md)

[← Back to README](../README.md)

# Docker details

For step-by-step installation see [Docker installation](docker-install.md). This page explains how the image
and the compose files are put together.

## Image

The [`Dockerfile`](../Dockerfile) is based on `serversideup/php:8.4-fpm-nginx`. That base image runs nginx and
PHP-FPM in one container under S6 Overlay, as `www-data`, so there is a single image and a single container.

- Code is copied into `/var/www/html` and `composer install --no-dev --optimize-autoloader` runs at build time.
- nginx listens on `NGINX_HTTP_PORT` (compose sets it from `APP_PORT`, default `8080`).
- `AUTORUN_ENABLED=true`: on start-up the base image runs `migrate --force` and caches config, routes and views.
  Set `AUTORUN_LARAVEL_MIGRATION=false` to skip migrations.
- Health endpoint: `/up`.

### Start-up script

[`docker/entrypoint.d/40-prepare-storage.sh`](../docker/entrypoint.d/40-prepare-storage.sh) runs before the
base image's Laravel automations:

1. Stops with an error if `APP_PORT` is `9000` (PHP-FPM's port).
2. Stops with an error if `APP_KEY` is empty. The code lives in the image, so a generated key would change
   on every re-creation and stored IMAP passwords would become unreadable.
3. Creates the `storage/` subdirectories and the SQLite file if missing.
4. Generates the Passport keys (`storage/oauth-*.key`) on first start.

## Compose files

| File | Use | Image |
|---|---|---|
| [`docker-compose.yml`](../docker-compose.yml) | Build from this checkout | `imap-mcp:local` (`build: .`) |
| [`docker-compose.release.yml`](../docker-compose.release.yml) | Run a published image | `${IMAGE:-ghcr.io/bahricanli/imap-mcp}:${VERSION:-latest}` |

Both define one service, `mail-mcp` (also the container name), with:

- `env_file: .env`, plus SQLite, `LOG_CHANNEL=stderr` and secure cookies forced in `environment:`.
- One named volume, `storage`, mounted at `/var/www/html/storage` (database and Passport keys).
- The external network `${NPM_NETWORK:-npm_default}` so Nginx Proxy Manager can reach `mail-mcp:<APP_PORT>`.
  No port is published by default.

The app has no queue worker or scheduler, so there are no extra services.

A local `docker-compose.override.yml` is picked up automatically by `docker compose` and is ignored by git.
Use it for host-specific changes such as publishing a port.

## Published images

| Registry | Repository |
|---|---|
| GitHub Container Registry | `ghcr.io/bahricanli/imap-mcp` |
| Docker Hub | `bahricanli/imap-mcp` |

Platforms: `linux/amd64`, `linux/arm64`. Tags for release `v1.2.3`: `1.2.3`, `1.2`, `1`, `latest`.

## CI

[`.github/workflows/docker.yml`](../.github/workflows/docker.yml):

- **Pull requests and pushes to `main`:** build the amd64 image and run
  [`scripts/smoke-test.sh`](../scripts/smoke-test.sh). Nothing is published.
- **`v*.*.*` tags:** the same test, then a multi-platform build pushed to GHCR and Docker Hub.
  It can also be started by hand (`workflow_dispatch`) by selecting a tag under "Use workflow from".
- **GitHub release:** after the images are published, a release is created for the tag. Its notes come
  from the matching `## [X.Y.Z]` section of [`CHANGELOG.md`](../CHANGELOG.md); the job fails if that section
  is missing. An existing release is left untouched.
- Docker Hub needs the `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` repository secrets. Without them the
  Docker Hub step is skipped and only GHCR is published.
- Build cache: GitHub Actions cache, scope `imap-mcp`.

[`.github/workflows/mirror-dockerhub.yml`](../.github/workflows/mirror-dockerhub.yml) copies a version that is already
on GHCR to Docker Hub without rebuilding it (same digest). Run it by hand with `version` (for example `1.0.1`);
`aliases` also moves `1.0`, `1` and `latest`.

The smoke test starts the container as if it were behind a proxy and checks `/up`, `/login`, the home page,
the OAuth discovery documents, the `401` + `WWW-Authenticate` answer of the MCP endpoint, migrations, the
Passport key, and that the container refuses to start without `APP_KEY`.

Run it locally:

```bash
docker build -t imap-mcp:test .
```

```bash
APP_PORT=8090 bash scripts/smoke-test.sh imap-mcp:test
```
