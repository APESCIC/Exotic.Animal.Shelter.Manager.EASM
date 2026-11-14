# Local profile for EASM (Laravel Herd)

EASM runs locally as its own Laravel Herd site. These are the values it needs. [`profile.json`](profile.json) holds the same values for `scripts/herd-setup.ps1` and `scripts/preview.ps1`, and a test keeps them in step with `.env.herd.example` and `package.json`.

| Setting | Value |
| --- | --- |
| Herd site | `easm`, linked from your clone (`herd link easm`) |
| Hostname | `https://easm.test`, secured with Herd's certificate (`herd secure easm`); `http://` redirects to it |
| Document root | `public/` |
| Web server | Herd's nginx |
| PHP | 8.3, as on the live server, isolated to this site (`herd isolate 8.3`); Herd runs it through FastCGI (`cgi-fcgi`) |
| PHP extensions | bcmath, curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, pdo_sqlite, zip |
| Database | Herd's MySQL 8.4, `easm_local` on `127.0.0.1:3306`, user `root`, no password, `utf8mb4_unicode_ci` |
| Mail | Herd's mail catcher, SMTP on `127.0.0.1:2525`; read the mail in the Herd app or with `herd mail:wait` |
| Node | 22, from Herd's nvm (`%USERPROFILE%\.config\herd\bin\nvm\v22.*`) |
| Live reload | Browsersync at `https://easm.test:5176` (`npm run dev`), proxying `https://easm.test`; [`bs-config.cjs`](../../bs-config.cjs) gives it Herd's `easm.test` certificate so browsers trust it. `scripts/preview.ps1` starts it in a hidden window and `-StopDevServer` stops it |
| Live app | `https://easm.intranet.apes.org.uk` (Cloudron); the preview gate checks its `/health` version matches the preview's |
| Env file | `.env.herd.example`, copied to `.env` by the setup script; no `APP_VERSION`, so the version comes from `VERSION` |

`.env.example` stays production-safe, because self-hosting shelters and the Cloudron image start from it. Herd's values live in `.env.herd.example` instead.

## PHP on the command line

Herd's `php` shim picks the global PHP, not the site's. In each new PowerShell window, put PHP 8.3 and Herd's tools first on `PATH` before running `php`, `composer` or the tests:

```powershell
$env:PATH = "$env:USERPROFILE\.config\herd\bin\php83;$env:USERPROFILE\.config\herd\bin;$env:PATH"
```

`herd php` and `herd composer` also use the site's isolated PHP when run from the repository folder.

## The local block in /health

On the local preview only (`APP_ENV=local`), `/health` adds a `local` block with the PHP version, the PHP handler and the checked-out git revision. `scripts/preview.ps1` reads it.

`public/.htaccess` is for the Apache servers EASM is deployed to; Herd's nginx ignores it. A test keeps it free of `php_value` and `php_flag` outside `<IfModule php_module>` (PHP through FastCGI answers those with a 500 error) and checks it still passes the `Authorization` header.
