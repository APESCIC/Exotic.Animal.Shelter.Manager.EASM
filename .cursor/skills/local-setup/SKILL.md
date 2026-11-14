---
name: local-setup
description: Set up EASM in Laravel Herd and run the live preview gate - load the latest code, check the local preview matches the live version, and open both sites in Cursor's browser. Use before any edit, install, test or commit, after every branch switch, or when the user mentions Herd, the preview, easm.test or local setup.
---

# Local setup and the live preview gate

Laravel Herd is the only local set-up. Settings: `docs/local/profile.md`. Demo data and logins: `docs/local/demo-data.md`. Terms: `docs/glossary.md`. The gate's steps follow Cursor's `live-preview-gate` and `herd-local-preview` skills; this skill fills in EASM's commands and URLs.

## First time only

1. Check Laravel Herd is installed and running, with its MySQL service started. If not, **stop and ask** Bambie; never install system tools without her approval.
2. From the repository folder, run:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\herd-setup.ps1 -SeedDemo
   ```

   It checks Herd's PHP 8.3, Node 22 and MySQL 8.4, copies `.env.herd.example` to `.env`, installs dependencies, creates the `easm_local` database, migrates and loads the fake demo data.
3. If it reports Herd does not serve `easm.test` yet, **stop and ask** before adding `-Link`: that changes Herd's sites (`herd link easm`, `herd isolate 8.3`).

## Every time (the live preview gate)

1. Commit or set aside any uncommitted work first; the script stops if tracked files have changed.
2. Run:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1
   ```

   It fetches, fast-forwards the branch, merges `origin/main` into it, installs changed dependencies, and reports pending migrations. It checks `http://easm.test/health` runs this revision on PHP 8.3 through FastCGI, compares the version with <https://easm.intranet.apes.org.uk/health>, and starts live reload on port 5176 in a hidden window.
3. Read the exit code:
   - `0`: ready.
   - `1`: blocked. Report the message and **stop**.
   - `3`: needs a decision, usually a version mismatch. **Stop and ask** with AskQuestion. The only expected difference is a `chore: release vX.Y.Z` commit waiting to ship.
4. If it lists pending migrations, **stop and ask** before running `php artisan migrate`.
5. Open <http://localhost:5176> and <https://easm.intranet.apes.org.uk> in Cursor's browser. Check both loaded, take a screenshot of each, and report the URLs and the version. Then say "Preview ready, starting work". Call `SetActiveBranch` if the branch changed.
6. If either site won't load after two attempts, **stop**, report what you saw, and ask how to go on.

## Good to know

- In a new PowerShell window, put PHP 8.3 first on `PATH` before running `php`, `composer` or the tests, as in Start here step 3 of `AGENTS.md`. Herd's plain `php` is its global 8.5; `herd php` uses the site's 8.3.
- `scripts\preview.ps1 -StopDevServer` stops live reload; its log is `storage\logs\browsersync.log`.
- `php artisan demo:reset` empties the local database and reloads the demo data. It refuses unless `APP_ENV=local`.
- Mail goes to Herd's mail catcher (SMTP 2525); read it in the Herd app or with `herd mail:wait`.
- Keep `public/.htaccess` free of `php_value` and `php_flag` outside `<IfModule php_module>`; the Apache servers EASM deploys to answer those with an error 500 under FastCGI.
