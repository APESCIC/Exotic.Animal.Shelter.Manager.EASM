# Security policy

## Supported versions

Only the latest published `vX.Y.Z` release on [GitHub Releases](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases) is supported for security fixes. Older tags may not receive patches.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for a security vulnerability.

Email the APES IT Administrator (or use the private contact published on [apes.org.uk](https://apes.org.uk)) with:

- a short description of the issue
- steps to reproduce (or a proof of concept)
- the affected version / commit if known

We aim to acknowledge reports within a few working days and to ship a fix or mitigation in a patch release when confirmed.

## Production hardening (self-host)

- Serve only the `public/` directory as the web root.
- Use HTTPS in production; keep Laravel CSRF protection enabled (default).
- Store `APP_KEY` offline (password manager / vault). Without it, encrypted data cannot be recovered from a backup.
- Limit PHP upload size and execution time to what your host allows; the constrained-host CI matrix documents shared-hosting limits.
- Keep Administration → Backups working, and download a backup before every update ([UPGRADING.md](UPGRADING.md#backup-and-restore)).
- Prefer turning off unused host tools (phpMyAdmin, SFTP) when you can.

Further install and Cloudron notes: [INSTALL.md](INSTALL.md), [docs/install.md](docs/install.md), [docs/hosting/cloudron-lamp.md](docs/hosting/cloudron-lamp.md).
