# Contributing

Thanks for helping improve imap-mcp. Issues and pull requests in English or Turkish are welcome.

## Reporting bugs

Open an issue at <https://github.com/bahricanli/imap-mcp/issues> with:

- what you did, what you expected and what happened,
- the version or image tag, and how you run it (Docker image, source, other),
- relevant log lines (`docker compose logs mail-mcp`). **Remove** email addresses, message content,
  passwords and tokens first.

Security problems must not be reported in public issues. See [SECURITY.md](SECURITY.md).

## Setting up

See [Development](docs/development.md) for local set-up and the project layout.

## Before sending a pull request

Run the tests:

```bash
php artisan test
```

Format the code:

```bash
vendor/bin/pint
```

If you changed the Dockerfile, the entrypoint or the routes, also run the smoke test:

```bash
docker build -t imap-mcp:test .
```

```bash
APP_PORT=8090 bash scripts/smoke-test.sh imap-mcp:test
```

## Guidelines

- Keep pull requests focused on one change and add tests for new behaviour.
- **Commit messages in English**, short imperative subject line (`Add attachment download tool`).
- The web panel and the MCP server instructions are in Turkish. Keep new user-facing text consistent with
  the existing wording.
- **Documentation is bilingual.** When you change `README.md` or a file in `docs/`, update the matching
  `.tr.md` file too (and the other way round).
- New write tools must stay off unless the account has write access, respect the 50-message limit and
  log the action.
- Add a line under `[Unreleased]` in [CHANGELOG.md](CHANGELOG.md).

## License

By contributing you agree that your contributions are licensed under the [MIT License](LICENSE).
