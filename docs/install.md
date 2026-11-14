# Install EASM on a host

How a shelter installs EASM on its own web hosting. Developing EASM locally is different: see [Start here in AGENTS.md](../AGENTS.md#start-here). Terms are in the [glossary](glossary.md).

## Hosting

| Host type | PHP | Database | Document root | Notes |
|-----------|-----|----------|---------------|--------|
| cPanel shared | 8.3+ (select in MultiPHP) | MySQL 8 or MariaDB 10.6+ | `public/` | One `composer install`. Don't point the site at the repository root. |
| Plesk | 8.3+ | MySQL or MariaDB | `public/` | Same document root and Composer step. |
| VPS (Apache or nginx) | 8.3+ | MySQL or MariaDB | `public/` | HTTPS recommended. |
| Cloudron LAMP app | 8.3 (`/app/data/PHP_VERSION`) | Cloudron MySQL addon, or your own | `public/` | The wizard offers Cloudron's database; see [Choosing the database](#choosing-the-database). |
Laravel 13 needs PHP 8.3 or newer. Composer runs at install time only, not on every request. One shelter per install; EASM is not multi-tenant software.

PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

## Install

Before installing, open `/check.php` in the browser. It is plain PHP (no Laravel) and reports PHP version, extensions, writable folders, document root, and other host limits, with UK English fixes. After install it locks and will not run again on that shelter. On Laravel Herd locally, `LocalValetDriver.php` serves `/check.php` outside the front controller.

The updater uses `install/package/` to download a Release zip over HTTPS in chunks, verify its SHA-256 and minisign signature with libsodium (public key `resources/keys/easm-release.minisign.pub`), then extract it safely. Extraction prefers `ZipArchive` and falls back to a pure-PHP reader; both paths reject zip-slip, absolute paths, symbolic links and oversized entries.

Prefer a published GitHub Release zip (includes `vendor/` and static assets; no Composer or Node on the host):

```bash
# optional: verify the signature and checksum from the Release assets
minisign -Vm easm-X.Y.Z.zip -p resources/keys/easm-release.minisign.pub
sha256sum -c SHA256SUMS
unzip easm-X.Y.Z.zip
cd easm-X.Y.Z   # or the folder name from the archive
```

The Release package workflow attaches `easm-X.Y.Z.zip`, `SHA256SUMS` and the `.minisig` when the Release is published (see [workflows.md](workflows.md#release-package-signing)).

Or clone and run Composer **once**:

```bash
git clone https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM.git
cd Exotic.Animal.Shelter.Manager.EASM
composer install --no-dev --optimize-autoloader
```

`composer install` copies `.env.example` to `.env` when needed and fills `APP_KEY` if it is empty. The zip path skips that step until the wizard writes `.env`. Neither path migrates or marks the app installed by itself.

Then:

1. Create an empty MySQL or MariaDB database in the host panel.
2. Point the site's document root at `public/`. Apache uses `public/.htaccess`. nginx needs `try_files` (see the [README nginx section](../README.md#document-root-and-nginx)).
3. Make `storage/` and `bootstrap/cache/` writable by the web server.
4. Run `php artisan storage:link`, so animal photos and media under `storage/app/public` can be reached.
5. Open the site in a browser. You are sent to `/install`.
6. Enter the database details (or choose Cloudron's database, below), organisation name, timezone (default Europe/London) and the first admin user.

The wizard writes `.env`, runs the migrations, creates that admin (role `admin`) and writes `storage/app/installed`. After that, `/install` won't run again. Sign in at `/login`; see [features.md](features.md) for what each role can do.

Shelters install and update from a clone or a zip like this. Nothing is ever deployed to a shelter's hosting.

### Choosing the database

- **Most hosts:** the wizard asks for the database details and saves them as `DB_*` in `.env`.
- **A Cloudron LAMP app:** Cloudron gives the app its MySQL database as `CLOUDRON_MYSQL_*` variables. The wizard then offers two choices:
  - **Use the database Cloudron provides** (selected first). The wizard checks it can connect, sets `DB_CONNECTION=mysql` and removes any `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` lines from `.env`. EASM then reads Cloudron's variables on every request, so it keeps working when Cloudron changes them after a restore or clone. Nothing about the database is written to `.env`.
  - **Enter my own database details:** the same form as other hosts. `DB_*` in `.env` always takes priority over Cloudron's variables.
- **Hosts that write `DB_*` themselves:** set `EASM_MANAGED_DATABASE=true` and the wizard skips the database step.

### Behind an HTTPS proxy

When something in front of EASM ends HTTPS and passes the request on over plain HTTP (Cloudflare, a load balancer, many shared hosts), EASM sees `http` and builds `http://` links and redirects. Set `EASM_TRUSTED_PROXIES` in `.env` so it believes the proxy's `X-Forwarded-*` headers:

| Value | Use it when |
| --- | --- |
| empty (the default) | Nothing is in front of EASM, or the web server already tells PHP the request was HTTPS (a Cloudron LAMP app does, through Apache's `rpaf.conf`) |
| `*` | The proxy is the only way in, so whatever connects is the proxy |
| `10.0.0.5,173.245.48.0/20` | The site can also be reached directly; list the proxy addresses or ranges (for Cloudflare, its published IP ranges) |

Run `php artisan config:clear` after changing it if the config is cached.

## Health and version

`GET /health` returns the app's status, name and version as JSON (`"status":"ok"`, a `version` field from the `VERSION` file, and `schema` as the last applied migration name). It checks that PHP can open the configured database, and never includes secrets, `.env` values or credentials. On MySQL/MariaDB the check uses a 2-second connect timeout so a silent packet drop returns 503 instead of holding a PHP worker for the driver default. It works before and after the installer. Laravel's own `GET /up` check is also available.

## The APES Cloudron instance

APES runs one copy at <https://easm.intranet.apes.org.uk> on a Cloudron LAMP app (since v0.6.1). `deploy-cloudron.yml` deploys each release tag to it; how and when is in [shipping.md](shipping.md#4-deploy-once-per-version).

Layout on the app, all under `/app/data` (Cloudron backs it up):

| Path | What it holds |
| --- | --- |
| `releases/<tag>-<commit>-<run>/` | One folder per deployed release; the newest five are kept |
| `easm` | Link to the live release. Apache serves `easm/public` (`apache/app.conf`) |
| `shared/.env` | Settings shared by every release. The first deploy creates it from `deploy/cloudron/env.example` with a new `APP_KEY`; the install wizard writes the rest |
| `shared/storage/` | Uploads, logs, sessions and the install lock |
| `run.sh` | Starts the queue worker and scheduler once EASM is installed |
| `PHP_VERSION` | Pinned to 8.3 by the first deploy |

- **Database and mail:** EASM reads Cloudron's `CLOUDRON_MYSQL_*` and `CLOUDRON_MAIL_*` variables, so `shared/.env` has no `DB_*` or `MAIL_*` lines. In the wizard, choose "Use the database Cloudron provides".
- **Config is never cached**, so the wizard's changes to `.env` and any new Cloudron credentials apply at once. Routes, events and views are cached per release.
- **HTTPS:** Cloudron's `rpaf.conf` tells Apache the request came over HTTPS, so Laravel builds `https://` links.
- **First deploy:** after it, open `/install` and complete the wizard, then restart the app so `run.sh` starts the queue worker and scheduler. Copy `APP_KEY` from `shared/.env` (Cloudron File Manager) into the password manager: without it, encrypted data can't be recovered from a backup.
- **Rollback:** the workflow switches back by itself when a deploy fails. To do it by hand: `cloudron exec --app <id> -- bash /app/data/releases/<old>/deploy/cloudron/rollback-release.sh /app/data/releases/<old>`, then restart the app. Migrations are not undone; restore the pre-deploy backup if needed.

The `production` environment the deploy needs (Settings → Environments → production; secret values are kept in APES Vault):

| Name | Kind | Value |
|------|------|-------|
| `CLOUDRON_APP_ID` | variable | the LAMP app's ID (`cloudron list`) |
| `CLOUDRON_SERVER` | secret | the Cloudron dashboard domain, `my.cloudron.apes.org.uk` |
| `CLOUDRON_TOKEN` | secret | a Cloudron API token named `github-actions-easm` (Cloudron profile → API Tokens) |
