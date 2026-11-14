# Security policy

## Supported versions

| Version | Supported |
|---|---|
| Latest release | Yes |
| Older releases | No. Please upgrade. |

## Reporting a vulnerability

Please **do not** open a public issue. Report it privately through GitHub:
<https://github.com/bahricanli/imap-mcp/security/advisories/new>

Include the affected version, steps to reproduce and the impact. Once a fix is released, the advisory is published and the reporter is credited unless they prefer otherwise.

## Scope

In scope:

- Authentication and OAuth flow (Passport, Dynamic Client Registration, consent screen).
- Access to another user's mail accounts, messages or stored IMAP credentials.
- Bypassing write-access checks or the server-side safeguards (Trash-first delete, 50-message limit,
  protected folders).
- The Docker image and its start-up scripts.

Out of scope:

- Prompt injection that only works when the user has turned on write access **and** the AI client
  ignores the server instructions, as long as the server-side safeguards hold. Reports that bypass those
  safeguards are in scope.
- Vulnerabilities in your mail server, reverse proxy or AI client.
- Missing hardening headers without a concrete attack.

## Running it safely

- Run it only behind HTTPS and keep `APP_URL` on `https://`.
- Keep `APP_KEY` secret and backed up; it encrypts the stored IMAP passwords.
- Use app passwords for IMAP, not your main password.
- Do not publish the container port; let the reverse proxy reach it over the Docker network. If you must
  publish it, set `TRUSTED_PROXIES` to your proxy's address.
- Enable write access only on accounts that need it.
- Review **Bağlı uygulamalar** (Connected apps) regularly and disconnect apps you no longer use.
