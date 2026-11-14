# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-14

First public release.

### Added

- MCP server at the root URL, protected with OAuth 2.1 (Passport, PKCE, Dynamic Client Registration).
- Read tools: `list-accounts`, `list-folders`, `search-emails`, `get-email`. Reading never marks messages as seen.
- Write tools, per account and off by default: `create-folder`, `move-emails`, `flag-emails`,
  `delete-emails`, `delete-folder`, and `move_to` / `mark_seen` on `get-email`.
- Server-side safeguards: delete moves to Trash, permanent delete only inside Trash, system and non-empty
  folders cannot be deleted, at most 50 messages per call, every write is logged.
- Web panel under `/panel`: mail accounts with connection test, connected apps with disconnect, password
  change, user administration for admins.
- Consent screen that needs no Vite build.
- Introduction page for browser visitors at the root URL.
- MIME-encoded subjects and sender names are decoded; search results are sorted newest first.
- Artisan commands: `mail:create-user`, `mail:add-account`, `mail:set-write`.
- Docker image (`serversideup/php` with nginx + PHP-FPM) for amd64 and arm64, published to GHCR and Docker Hub
  on version tags, with a smoke test in CI.
- `docker-compose.yml` (build from source) and `docker-compose.release.yml` (pre-built image).
- The container refuses to start without `APP_KEY` or with `APP_PORT=9000`.
- English and Turkish documentation.

[Unreleased]: https://github.com/bahricanli/imap-mcp/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bahricanli/imap-mcp/releases/tag/v1.0.0
