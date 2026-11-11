---
name: local-setup
description: Set up EASM in Laragon and run the live preview gate - load the latest code, check the local preview matches the live version, and open both sites in Cursor's browser. Use before any edit, install, test or commit, after every branch switch, or when the user mentions Laragon, the preview, easm.test or local setup.
---

# Local setup and the live preview gate

Laragon is the only local set-up. Settings: `docs/laragon/profile.md`. Demo data and logins: `docs/laragon/demo-data.md`. Terms: `docs/glossary.md`.

## First time only

1. Check Laragon (Full) is installed and running. If not, **stop and ask** Bambie; never install system tools without her approval.
2. From the repository folder, run:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\laragon-setup.ps1 -SeedDemo
   ```

   It links `C:\laragon\www\easm` to the clone, copies `.env.laragon.example` to `.env`, installs dependencies, creates the `easm_local` database, migrates and loads the fake demo data.
3. If it reports the EASM virtual host is missing, **stop and ask** before adding `-InstallVhost`: that changes Laragon's own files and needs an Apache restart.

## Every time (the live preview gate)

1. Commit or set aside any uncommitted work first; the script stops if tracked files have changed.
2. Run:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1
   ```

   It fetches, fast-forwards the branch, merges `origin/main` into it, installs changed dependencies, reports pending migrations, checks `http://easm.test/health` runs this revision on PHP 8.3 through FastCGI, compares the version with <https://easm.intranet.apes.org.uk/health>, and starts live reload on port 5176 in a hidden window.
3. Read the exit code:
   - `0`: ready.
   - `1`: blocked. Report the message and **stop**.
   - `3`: needs a decision, usually a version mismatch. **Stop and ask** with AskQuestion. The only expected difference is a `chore: release vX.Y.Z` commit waiting to ship.
4. If it lists pending migrations, **stop and ask** before running `php artisan migrate`.
5. Open <http://localhost:5176> and <https://easm.intranet.apes.org.uk> in Cursor's browser. Check both loaded, take a screenshot of each, and report the URLs and the version. Then say "Preview ready, starting work". Call `SetActiveBranch` if the branch changed.
6. If either site won't load after two attempts, **stop**, report what you saw, and ask how to go on.

## Good to know

- In a new PowerShell window, put PHP 8.3 first on `PATH` before running `php`, `composer` or the tests, as in Start here step 3 of `AGENTS.md`.
- `scripts\preview.ps1 -StopDevServer` stops live reload; its log is `storage\logs\browsersync.log`.
- `php artisan demo:reset` empties the local database and reloads the demo data. It refuses unless `APP_ENV=local`.
- Keep `public/.htaccess` free of `php_value` and `php_flag` outside `<IfModule php_module>`; FastCGI answers those with an error 500.
