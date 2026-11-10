# Laragon profile for EASM

EASM is its own Laragon project. These are the values it needs. [`profile.json`](profile.json) holds the same values for `scripts/laragon-setup.ps1` and `scripts/preview.ps1`, and a test keeps them in step with `.env.laragon.example` and `package.json`.

| Setting | Value |
| --- | --- |
| Project folder | `C:\laragon\www\easm` (a junction to your clone) |
| Hostname | `http://easm.test` |
| Document root | `public/` |
| Web server | Apache 2.4 |
| PHP | 8.3, as in the Cloudron image (`php-8.3.33-Win32-vs16-x64`) |
| PHP handler | FastCGI (mod_fcgid) in EASM's own vhost, [`easm.test.conf`](easm.test.conf), so Laragon's other sites keep their PHP |
| PHP extensions | bcmath, curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, pdo_sqlite, zip |
| Database | MySQL 8.4, `easm_local` on `127.0.0.1:3306`, user `root`, no password, `utf8mb4_unicode_ci` |
| Node | 22, with npm |
| Live reload | Browsersync on port 5176 (`npm run dev`), proxying `http://easm.test` |
| Env file | `.env.laragon.example`, copied to `.env` by the setup script |

`.env.example` stays production-safe, because self-hosting shelters and the Cloudron image start from it. Laragon's values live in `.env.laragon.example` instead.

## Why its own vhost and FastCGI

Laragon runs one global PHP for Apache. With an NTS PHP selected, it runs PHP through FastCGI (`C:\laragon\etc\apache2\fcgid.conf`) with separate PHP processes per site, so one project's `.env` cannot leak into another. EASM's vhost adds its own `FcgidWrapper` for PHP 8.3, so it matches the Cloudron image while the other APES sites keep PHP 8.4.

Laragon writes `auto.easm.test.conf` itself. A vhost file without the `auto.` prefix is left alone ([Laragon: Pretty URLs](https://laragon.org/docs/pretty-urls)), so `scripts/laragon-setup.ps1 -InstallVhost` copies `easm.test.conf` into `sites-enabled` and removes the `auto.` file. Apache must then restart. Checked on this PC: `/health` reports PHP 8.3.33 through `cgi-fcgi` for EASM while the other sites report PHP 8.4.

The setup script also checks that `public/.htaccess` has no `php_value` or `php_flag` outside `<IfModule php_module>` (FastCGI answers those with a 500 error) and still passes the `Authorization` header.

On the Laragon preview only (`APP_ENV=local`), `/health` adds a `local` block with the PHP version, the PHP handler and the checked-out git revision. `scripts/preview.ps1` reads it.
