# Install and update EASM on a Cloudron LAMP app

Operator guide for a Cloudron LAMP app using the published Release zip and the browser installer/updater.

> **Status:** Outline for issue #146. Steps below summarise what the APES instance already uses; #146 replaces this page with the full verified checklist (PHP 8.3 file, `app.conf` DocumentRoot and health rewrite, `.env` / `EASM_MANAGED_DATABASE`, web updates, backups, turning off phpMyAdmin and SFTP).

## Quick path (until #146 lands)

1. Install Cloudron’s LAMP app; set `/app/data/PHP_VERSION` to `8.3`.
2. Upload and extract the Release zip under `/app/data/easm` (File Manager or SFTP).
3. Point Apache DocumentRoot at `/app/data/easm/public`; keep the `rpaf.conf` include; ensure the Cloudron health check receives a 2xx (see the APES notes in [docs/install.md](../install.md#the-apes-cloudron-instance)).
4. Prefer Cloudron’s MySQL variables in the `/install` wizard (“Use the database Cloudron provides”) or set `EASM_MANAGED_DATABASE=true` when `DB_*` are managed for you. Do not `config:cache` on Cloudron (the database password can rotate).
5. Complete `/install`, then use **Administration → Update** for later releases ([UPGRADING.md](../../UPGRADING.md)).
6. Rely on Cloudron backups; store `APP_KEY` from `.env` in your password manager.

Linked from [INSTALL.md](../../INSTALL.md) and the [README](../../README.md).
