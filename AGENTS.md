# EASM: start here

For people and Cursor agents alike. Any word you don't know is in the [glossary](docs/glossary.md).

Cursor's user rules and skills set how work is done here (branches, issues, pull requests, merge, release, deploy, ship gate, decisions and safety). This file describes this repository and fills the named slots below. If anything here disagrees with them, Cursor's rules win and this file is corrected in the same milestone.

## What this repo is

EASM (Exotic Animal Shelter Manager) is web software for one exotic-animal shelter per install: animals, care, CITES and DWA paperwork, people, adoption, finance and reports ([features](docs/features.md)). It is a Laravel app (PHP 8.3) with a MySQL database. APES runs one copy at <https://easm.intranet.apes.org.uk>.

## Start here

[Laravel Herd](https://herd.laravel.com/windows) on Windows is the only local set-up.
1. Install and start Laravel Herd (with its MySQL service), then clone this repository.
2. Once, in PowerShell from the repository folder: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\herd-setup.ps1 -Link -SeedDemo`
3. Before any work: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1` (the live preview gate). In each new PowerShell window, first run `$env:PATH = "$env:USERPROFILE\.config\herd\bin\php83;$env:USERPROFILE\.config\herd\bin;$env:PATH"` so `php` and `composer` use PHP 8.3 (Herd's plain `php` is its global 8.5), or use `herd php` and `herd composer`.
4. Open <https://easm.test:5176>, your local copy, which reloads as you save. Sign in as `admin@easm.test` with password `password` ([fake demo data](docs/local/demo-data.md)).
5. In Cursor, `/local-setup` does steps 3 and 4 for you. Settings: [docs/local/profile.md](docs/local/profile.md).

## Local development and preview

| Item | Value |
| --- | --- |
| Clone | `C:\Users\BambieMurphy\Documents\GitHub\Exotic.Animal.Shelter.Manager.EASM` |
| Herd site | `easm` (linked and secured; PHP 8.3 isolated, through FastCGI) |
| Preview URL | `https://easm.test`, with live reload at <https://easm.test:5176> |
| Preview command | `scripts\preview.ps1` (starts Browsersync, `npm run dev`, on Herd's Node 22) |
| Live reload port | 5176 (Browsersync with Herd's certificate from `bs-config.cjs`; EASM has no Vite) |
| Env file | `.env.herd.example`, copied to `.env` by `scripts\herd-setup.ps1` |
| Database | MySQL, `easm_local` by default (Herd MySQL 8.4 on 127.0.0.1:3306, `root`, no password) |
| Mail | Herd mail catcher (`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=2525`) |

- The tests use SQLite in memory (`phpunit.xml`), so they need no local database.
- Herd's status table can show MySQL as `error` while it is listening on 3306 and working; `scripts\preview.ps1` checks the port instead.
- Herd can start its own `php artisan dev` stack for the site (`artisan serve`, a queue listener and a second `npm run dev`). Stop it in Herd or with `herd processes:stop dev --site=easm`; `scripts\preview.ps1` runs live reload.
- Browsersync proxying an HTTPS site always serves HTTPS itself (`--no-https` is ignored), which is why `bs-config.cjs` hands it Herd's certificate.
- Debug with Herd: `herd site:info`, `herd site:error`, `herd logs:read`, `herd mail:wait`, `herd insights:analyze`.

## How we work

Work ships in versions, such as `v0.6.2`. Each version is one GitHub milestone. Always use the version number, never GitHub's milestone number.
1. **Work:** one issue at a time. Each gets its own branch and a pull request (PR) into the version branch, with Work started, Progress update and Work complete comments on the issue. Close the issue when its PR merges.
2. **Merge:** when every issue is done, bring `main` into the version branch and open one PR into `main`. After Bambie's review it is merged once, as a merge commit.
3. **Release:** one tag (`vX.Y.Z`) and one GitHub Release.
4. **Deploy:** one deploy of that release, with evidence.

Hotfixes only with Bambie's written approval on the issue. The full guide is [docs/shipping.md](docs/shipping.md); a diagram and a worked example are in [CONTRIBUTING.md](CONTRIBUTING.md).

## Everyday commands

| Command | What it does | When to use it |
| --- | --- | --- |
| `/start-next-version` | Finds the next version, names the agent, sets up its branch and plan | "Complete the next version" |
| `/plan-work` | Writes a plan and waits for approval | Before any bigger change |
| `/work-on-issue` | Takes one issue from start to closed | Every issue |
| `/version-status` | Reports what's done and left (read-only) | Any time |
| `/finish-version` | Sets the version, asks the ship gate, merges into `main` once | Every issue is closed |
| `/release-version` | Tags the merge and publishes the Release | After the merge |
| `/deploy-version` | Deploys the release and closes the milestone | After the release |
| `/local-setup` | Runs the live preview gate | Before any work |
| `composer test` | Runs all the tests | Before every PR |
| `composer lint` / `composer format` | Checks / fixes PHP code style (Pint) | Before every PR |
| `composer milestone:next` | Prints the next version, its branch and titles | Starting a version |
| `composer milestone:branch -- --issue <n>`, `milestone:pr-title -- <n>` | Print the branch name and PR title for an issue | Starting an issue |
| `composer milestone:status -- vX.Y.Z` | Lists open issues; fails while any are open | Before closing a milestone |
| `composer milestone:bump -- vX.Y.Z` | Sets the version in every file | Last commit of a version |
| `php artisan demo:reset` | Reloads the fake demo data | Local only |
| `git config core.hooksPath .githooks` | Checks branch names before each push | Once, optional |

## Rules you must follow

- Run the live preview gate before any work, and after every branch switch.
- Never commit straight to `main`. Never squash, rebase or force-push a shared branch.
- Nothing goes into `main`, and nothing is tagged, released or deployed, without Bambie's answer to the ship gate.
- Never write tokens, passwords, `.env` contents or `APP_KEY` into code, docs or comments.
- Security checks use only free GitHub features ([docs/workflows.md](docs/workflows.md)).
- UK English. Commit messages: `fix: what changed (Refs #123)`.
- Published Releases also get a shared-hosting zip from `release-package.yml` (`release: published` only): `easm-X.Y.Z.zip`, `SHA256SUMS` and a minisign signature. Public key: `resources/keys/easm-release.minisign.pub`. Private key: repository secret `MINISIGN_SECRET_KEY` (never in git).
- Pre-install host check: open `/check.php` (plain PHP under `install/requirements/`, no Laravel). After install it locks. Herd serves it via `LocalValetDriver.php`.

## Where to find more

[docs/shipping.md](docs/shipping.md) (how a version ships) · [docs/reports.md](docs/reports.md) (report and comment templates) · [docs/workflows.md](docs/workflows.md) (what CI runs) · [docs/install.md](docs/install.md) · [docs/roadmap.md](docs/roadmap.md) · [CONTRIBUTING.md](CONTRIBUTING.md) · [.cursor/skills/](.cursor/skills/)
