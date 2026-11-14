# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `mirror-dockerhub` workflow: copies a published GHCR version to Docker Hub without rebuilding.

### Changed

- New logo and favicon; the home page, panel, login and consent screens show the logo and the name `imap-mcp`
  instead of "Mail MCP".

### Fixed

- Docker Hub repository is `bahricanli/imap-mcp`; CI no longer derives it from the login user name.

## [1.0.1] - 2026-11-14

### Changed

- `composer.lock` is now committed, so every build of a tag installs the same dependency versions.
- PHP 8.4 or newer is required (`config.platform.php` = 8.4.1), matching the Docker image.
- Updated dependencies to their latest versions and raised the minimum constraints in `composer.json`:
  Laravel 13.42, laravel/mcp 1.2, laravel/tinker 3.1, Symfony 8.1, PHPUnit 13.4 (dev).

### Security

- `phpseclib/phpseclib` must be 4.0.3 or newer (`conflict` in `composer.json`, GHSA-w3p9-g4p2-h3rr).
  This keeps `laravel/passport` on 13.10.0, because 13.11.0 requires exactly the vulnerable phpseclib 4.0.1.

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

[Unreleased]: https://github.com/bahricanli/imap-mcp/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/bahricanli/imap-mcp/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bahricanli/imap-mcp/releases/tag/v1.0.0
