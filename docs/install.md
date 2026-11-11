# Install EASM on a host

How a shelter installs EASM on its own web hosting. Developing EASM locally is different: see [Start here in AGENTS.md](../AGENTS.md#start-here). Terms are in the [glossary](glossary.md).

## Hosting

| Host type | PHP | Database | Document root | Notes |
|-----------|-----|----------|---------------|--------|
| cPanel shared | 8.3+ (select in MultiPHP) | MySQL 8 or MariaDB 10.6+ | `public/` | One `composer install`. Don't point the site at the repository root. |
| Plesk | 8.3+ | MySQL or MariaDB | `public/` | Same document root and Composer step. |
| VPS (Apache or nginx) | 8.3+ | MySQL or MariaDB | `public/` | HTTPS recommended. |
| Cloudron LAMP app | 8.3 (`/app/data/PHP_VERSION`) | Cloudron MySQL addon, or your own | `public/` | The wizard offers Cloudron's database; see [Choosing the database](#choosing-the-database). |
| Cloudron (APES instance) | 8.3 (image) | Cloudron MySQL addon | `public/` | Custom package in this repository; see [The APES Cloudron instance](#the-apes-cloudron-instance). |

Laravel 13 needs PHP 8.3 or newer. Composer runs at install time only, not on every request. One shelter per install; EASM is not multi-tenant software.

PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

## Install

Clone the repository or unzip a release, then run Composer **once**:

```bash
git clone https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM.git
cd Exotic.Animal.Shelter.Manager.EASM
composer install --no-dev --optimize-autoloader
```

From a zip:

```bash
unzip easm.zip
cd easm   # the folder name may match the release archive
composer install --no-dev --optimize-autoloader
```

`composer install` copies `.env.example` to `.env` when needed and fills `APP_KEY` if it is empty. It does not migrate, and does not mark the app installed.

Then:

1. Create an empty MySQL or MariaDB database in the host panel.
2. Point the site's document root at `public/`.
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

## Health and version

`GET /health` returns the app's status, name and version as JSON (`"status":"ok"` and a `version` field). It checks that PHP can open the configured database, and never includes secrets, `.env` values or credentials. It works before and after the installer. Laravel's own `GET /up` check is also available.

## The APES Cloudron instance

APES runs one copy at <https://easm.intranet.apes.org.uk> as a custom Cloudron package. v0.6.3 removes this package ([#142](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/142)), and after v0.9.0 the instance moves to a Cloudron LAMP app installed like any shared host. How releases reach it is in [shipping.md](shipping.md#4-deploy-once-per-version).

- `release.yml` builds `ghcr.io/apescic/easm:<version>` from the multi-stage `Dockerfile` on each release tag, and `deploy-cloudron.yml` deploys that image.
- The GHCR `easm` package must be public, or GHCR must be added as a private registry in Cloudron, so Cloudron can pull the image.
- The first deploy replaces the app's current package and needs a manual run with `first_deploy=true`.
- The database comes from the Cloudron MySQL addon. `cloudron/start.sh` writes `DB_*`, `APP_URL` and the mail settings into `/app/data/env`, and runs the migrations on every start. The install wizard skips the database step (`EASM_MANAGED_DATABASE=true`). Uploads and the install lock live in `/app/data/storage`.

Repository settings the deploy needs (Settings → Secrets and variables → Actions; values are kept in APES Vault):

| Name | Kind | Value |
|------|------|-------|
| `CLOUDRON_APP_ID` | variable | `7afd5173-8880-4e64-9c36-5f978d9c8f64` |
| `CLOUDRON_SERVER` | secret | the Cloudron dashboard domain, such as `my.cloudron.apes.org.uk` |
| `CLOUDRON_TOKEN` | secret | a Cloudron API token (Cloudron profile → API Tokens) |
