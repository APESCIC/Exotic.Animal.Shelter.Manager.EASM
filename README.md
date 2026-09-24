# Exotic Animal Shelter Manager (EASM)

Self-hosted software for one exotic-animal shelter per install. Stack: **Laravel + PHP 8 + MySQL/MariaDB** on ordinary web hosting. One `composer install` at setup, not on every request. It is a new application, not a fork of Animal Shelter Manager / sheltermanager.com. Species stay free-text — there is no dog/cat vocabulary.

UK-first defaults: locale `en_GB`, timezone `Europe/London`. Display dates as `dd/mm/yyyy` land with settings (#15).

See [AGENTS.md](AGENTS.md) for product constraints and implement order (v0.1.0 through v1.0.0).

Tracked against [issue #13](https://github.com/APESCIC/Exotic-Animal-Shelter-Manager-EASM/issues/13) under [epic #3](https://github.com/APESCIC/Exotic-Animal-Shelter-Manager-EASM/issues/3) / [plan #1](https://github.com/APESCIC/Exotic-Animal-Shelter-Manager-EASM/issues/1).

## Hosting matrix

| Host type | PHP | Database | Document root | Notes |
|-----------|-----|----------|---------------|--------|
| cPanel shared | 8.3+ (select in MultiPHP) | MySQL 8 or MariaDB 10.6+ | `public/` | One `composer install`. Do not point the site at the repo root. |
| Plesk | 8.3+ | MySQL or MariaDB | `public/` | Same document-root and Composer step. |
| VPS (Apache or nginx) | 8.3+ | MySQL or MariaDB | `public/` | HTTPS recommended. No Cloudron packaging or OIDC. |

Laravel 13 needs PHP 8.3 or newer. Composer runs at install time only — not on every HTTP request. One shelter per install; this is not multi-tenant SaaS. No Cloudron OIDC or staff login in v0.1.

### PHP extensions

`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

## Install

Unzip a release or clone the repository, then run Composer **once**. The web wizard collects database credentials, the first admin user, organisation name, and timezone (default Europe/London).

```bash
git clone https://github.com/APESCIC/Exotic-Animal-Shelter-Manager-EASM.git
cd Exotic-Animal-Shelter-Manager-EASM

composer install --no-dev --optimize-autoloader
```

From a zip:

```bash
unzip easm.zip
cd easm   # directory name may match the release archive
composer install --no-dev --optimize-autoloader
```

`composer install` copies `.env.example` to `.env` when needed and fills `APP_KEY` if it is empty. It does not migrate and does not mark the app installed.

Then:

1. Create an empty MySQL or MariaDB database in the host panel.
2. Point the vhost document root at `public/`.
3. Make `storage/` and `bootstrap/cache/` writable by the web server.
4. Run `php artisan storage:link` so primary animal photos and extra media under `storage/app/public` are publicly reachable.
5. Open the site in a browser. You are redirected to `/install`.
6. Enter database credentials, organisation name, timezone, and the first admin user.

The wizard writes `.env`, runs migrations, creates that admin (role `admin`), and writes `storage/app/installed`. After that, `/install` will not run again.

Sign in at `/login` with the admin email and password. Roles are admin, staff, volunteer, and readonly. Only admins can open `/admin` and change organisation settings (name, locale, timezone). Dates display as `dd/mm/yyyy`. Successful logins are written to `login_events`.

Admin and staff can manage animal records at `/animals` (free-text species, primary photo, enclosure/CITES/DWA, find/filter, shelter view by location). Run `php artisan storage:link` once so primary photos and animal media are served.

Admin and staff can manage people/contacts at `/people` (categories, banned and homechecked flags, find/filter).

Admin and staff can record movements on an animal (intake, hold, quarantine, foster, trial adoption, adoption, reclaim, transfer, deceased) with history on the animal record. A deceased movement updates the animal’s deceased date and death reason. Optional contact links use people from `/people`.

Admin and staff can record medical care on an animal (vaccinations, tests, treatments, diets, daily observations) from the animal record.

Admin and staff can create and complete diary tasks from an animal record or the shared inbox at `/diary`.

Admin and staff can upload extra photos and PDFs onto an animal (Media section). This is separate from the primary photo.

Admins define custom husbandry fields under `/admin/custom-fields` (text, number, date, yes/no). Staff fill them on animal create/edit; animal search also matches those values.

Open `/health`. You should see JSON with `"status":"ok"` and a `version` field.

Development install uses `composer install` (with dev packages) instead of `--no-dev`.

## Health and version

`GET /health` returns application status, name, and version. It checks that PHP can open the configured database and does not include secrets, `.env` values, or credentials. It stays available before and after the installer.

Laravel's built-in `GET /up` probe remains available.

## Local development

On Windows, prefer **[Laragon](https://laragon.org/)** (Apache + MySQL + PHP) instead of `php artisan serve` or a separate Winget PHP.

### Laragon (recommended on Windows)

1. Start Laragon (Apache and MySQL).
2. From a PowerShell session in the repo root, load Laragon’s PHP/Composer onto `PATH`:

```powershell
. .\scripts\local\use-laragon.ps1
composer install
```

3. Point a site at this clone’s `public/` directory. Easiest: a directory junction so Laragon’s auto-host works:

```powershell
cmd /c mklink /J C:\laragon\www\easm "%CD%"
```

Open **http://easm.test** (document root must be `public/`). If the auto-host serves the repo root instead of `public/`, add a Laragon/Apache virtual host with `DocumentRoot` …`/easm/public`, or use Laragon’s “Quick add” for a site whose root is `public/`.

After the first junction or vhost setup, **restart Laragon Apache** so `easm.test` resolves. Ensure `127.0.0.1 easm.test` is in your hosts file (Laragon usually adds it; otherwise add manually).

4. Local `.env` (never commit it) should use Laragon MySQL, for example:

```env
APP_URL=http://easm.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=easm
DB_USERNAME=root
DB_PASSWORD=
```

Create an empty `easm` database in HeidiSQL / MySQL if needed, then open http://easm.test/install (or `/login` if already installed).

### Without Laragon

```bash
composer install
php artisan serve
```

Open http://127.0.0.1:8000/install against a local MySQL/MariaDB database, then sign in at `/login`. If `php artisan serve` reloads when the wizard writes `.env`, refresh the home page — install has already finished.

Node and Vite are optional and are not required to boot, install, or hit `/health`.

### Tests and lint

```bash
composer test
composer lint
```

PHPUnit treats the app as already installed (`APP_INSTALLED=true`) except in installer tests. Agents must not run Pint while editing; lint is for CI and the pre-commit verification gate (see AGENTS.md / ship-gate).

## Releases

There is no CI or agent deploy to customer hosting. Shelters install from a clone or zip (Composer + `/install`) as above.

GitHub Releases are optional and operator-gated: after a merge to `main`, Cursor agents ask before creating a release (see [AGENTS.md](AGENTS.md) ship-gate). CI (`.github/workflows/ci.yml`) runs tests only.

## What this does not include

- Public site, Cloudron OIDC / MyAPES-Account auth copy

The repository is maintained by [APES CIC](https://github.com/APESCIC).
