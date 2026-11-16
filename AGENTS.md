# Agent instructions for EASM

Cursor's user rules and skills set how work is done here (branches, issues, pull requests, merge, release, deploy, ship gate, decisions and safety). This file describes this repository and fills the named slots below. If anything here disagrees with them, Cursor's rules win and this file is corrected in the same milestone.

Cursor/agent layout standard: https://github.com/apescic-director/Cursor/blob/main/docs/cursor-agent-standards.md

The owner is Bambie Murphy. Write UK English (organisation, behaviour, colour, licence) in the product, docs, issues and pull requests. Never print, paste or commit secrets. Secrets live only in APES Vault (Bitwarden). Development is tracked only in GitHub issues, sub-issues and `vMAJOR.MINOR.PATCH` milestones — never Linear.

## About the app

EASM (Exotic Animal Shelter Manager) is web software for one exotic-animal shelter per install: animals, care, CITES and DWA paperwork, people, adoption, finance and reports ([features](docs/features.md)). It is a Laravel app (PHP 8.3) with a MySQL database. APES runs one copy at <https://easm.intranet.apes.org.uk>.

- Repository: `APESCIC/Exotic.Animal.Shelter.Manager.EASM`. Work only in this repository.

## Stack and architecture

- Laravel on PHP 8.3, MySQL, Blade views, Browsersync for local live reload (no Vite).
- Key folders: `app/`, `routes/`, `resources/views/`, `database/`, `public/`, `install/` (browser installer and recovery), `deploy/cloudron/`, `scripts/`.
- Terms: [docs/glossary.md](docs/glossary.md).

## Local development and preview

Laravel Herd on Windows is the only local set-up. Use fake demo data only ([docs/local/demo-data.md](docs/local/demo-data.md)).

| Item | Value |
| --- | --- |
| Clone | `C:\Users\BambieMurphy\Documents\GitHub\Exotic.Animal.Shelter.Manager.EASM` |
| Herd site | `easm` (linked and secured; PHP 8.3 isolated, through FastCGI) |
| Preview URL | `https://easm.test`, with live reload at <https://easm.test:5176> |
| Preview command | `scripts\preview.ps1` (starts Browsersync, `npm run dev`, on Herd's Node 22) |
| Live reload port | 5176 (Browsersync with Herd's certificate from `bs-config.cjs`; EASM has no Vite) |
| Env file | `.env.herd.example`, copied to `.env` by `scripts\herd-setup.ps1` (key names only in git) |
| Database | MySQL, `easm_local` by default (Herd MySQL 8.4 on 127.0.0.1:3306, `root`, empty password) |
| Mail | Herd mail catcher (`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=2525`) |
| Set up | `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\herd-setup.ps1 -Link -SeedDemo` |
| Profile | [docs/local/profile.md](docs/local/profile.md) |

### Local development with Laravel Herd

1. Install and start Laravel Herd (with its MySQL service), then clone this repository.
2. Once: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\herd-setup.ps1 -Link -SeedDemo`.
3. Before any work: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1` (the live preview gate). In each new PowerShell window, put PHP 8.3 first on `PATH`: `$env:PATH = "$env:USERPROFILE\.config\herd\bin\php83;$env:USERPROFILE\.config\herd\bin;$env:PATH"` (Herd's plain `php` is its global 8.5), or use `herd php` and `herd composer`.
4. Open <https://easm.test:5176>. Sign in as `admin@easm.test` with the fake demo password documented in [docs/local/demo-data.md](docs/local/demo-data.md).
5. In Cursor, `/local-setup` runs the gate for you.

- The tests use SQLite in memory (`phpunit.xml`), so they need no local database.
- Herd's status table can show MySQL as `error` while it is listening on 3306; `scripts\preview.ps1` checks the port instead.
- Stop Herd's own `php artisan serve` / `dev` stack for this site if it is running; `scripts\preview.ps1` owns live reload.
- Debug with Herd: `herd site:info`, `herd site:error`, `herd logs:read`, `herd mail:wait`, `herd insights:analyze`.

## Tests and checks

| Check | Command |
| --- | --- |
| Tests | `composer test` |
| Lint / format | `composer lint` / `composer format` (Pint) |
| Milestone helpers | `composer milestone:next`, `milestone:branch`, `milestone:pr-title`, `milestone:status`, `milestone:bump` |
| Demo data | `composer demo:seed` / `composer demo:reset` (alias of `demo:seed --fresh`); local Herd only — see [docs/local/demo-data.md](docs/local/demo-data.md) |
| Developer QA login | `QA_LOGIN_ENABLED=true` with `APP_ENV=local` (Herd); amber panel on `/login`, `/qa`, in-app banner; off in production; never set by the installer |
| CI workflows | `.github/workflows/` (`ci`, `milestone-version-check`, `dependency-review`, `release`, `release-package`, `deploy-cloudron`) |

## How we work

Work ships in versions such as `v0.6.2`. Always use the version number, never GitHub's milestone number. Detail: [docs/shipping.md](docs/shipping.md); skills under `.cursor/skills/`.

1. **Work:** one issue at a time. Each gets its own branch and a PR into the version branch `milestone/vX-Y-Z-<slug>`, with Work started, Progress update and Work complete comments. Close the issue when its PR merges.
2. **Merge:** when every issue is done, bring `main` into the version branch and open one PR into `main`. After Bambie's ship-gate answer it merges once as a merge commit.
3. **Release:** one tag `vX.Y.Z` and one GitHub Release.
4. **Deploy:** one deploy of that release tag, with evidence.

Hotfixes only with Bambie's written approval on the issue. Worked example: [CONTRIBUTING.md](CONTRIBUTING.md).

### Everyday Cursor skills

| Command / skill | What it does |
| --- | --- |
| `/start-next-version` | Finds the next version, names the agent, sets up its branch and plan |
| `/plan-work` | Writes a plan and waits for approval |
| `/work-on-issue` | Takes one issue from start to closed |
| `/version-status` | Reports what's done and left (read-only) |
| `/finish-version` | Sets the version, asks the ship gate, merges into `main` once |
| `/release-version` | Tags the merge and publishes the Release |
| `/deploy-version` | Deploys the release and closes the milestone |
| `/local-setup` | Runs the live preview gate |

## Workflow slots

| Slot | Value |
| --- | --- |
| Milestone branch pattern | `milestone/vX-Y-Z-<slug>` (from `composer milestone:next`) |
| Issue branch pattern | from `composer milestone:branch -- --issue <n>` (for example `chore/151-shipping-docs`) |
| Version files | set by `composer milestone:bump -- vX.Y.Z` |
| Release tooling | push `vX.Y.Z` tag; `release.yml` publishes the Release; `release-package.yml` builds the shared-hosting zip |
| Area labels | `area:ui`, `area:animals`, `area:husbandry`, `area:compliance`, `area:people`, `area:adoption`, `area:finance`, `area:mobile`, `area:security`, `area:docs`, `area:install` |
| Issue title prefix | `[Type] Short title`; assignee `apescic-director` |

## Deploy and health

| Item | Value |
| --- | --- |
| Target | Cloudron LAMP app (APES instance) |
| Domain | <https://easm.intranet.apes.org.uk> |
| Deploy command | `gh workflow run deploy-cloudron.yml --ref vX.Y.Z -f tag=vX.Y.Z` |
| Deploys | The published release tag only (never a branch) |
| Auto-deploy couplings | None: merging and releasing do not deploy |
| Health URL | <https://easm.intranet.apes.org.uk/health> |
| Version field | JSON health payload includes version / `REVISION` |
| Secrets live in | APES Vault; GitHub `production` environment holds `CLOUDRON_*` (names only in docs) |

Operator Cloudron LAMP guide: [docs/hosting/cloudron-lamp.md](docs/hosting/cloudron-lamp.md). APES layout: [docs/install.md](docs/install.md#the-apes-cloudron-instance).

## Rules you must follow

- Run the live preview gate before any work, and after every branch switch.
- Never commit straight to `main`. Never squash, rebase or force-push a shared branch.
- Nothing goes into `main`, and nothing is tagged, released or deployed, without Bambie's ship-gate answer in the current chat.
- Never write tokens, passwords, `.env` contents or `APP_KEY` into code, docs or comments.
- Security checks use only free GitHub features ([docs/workflows.md](docs/workflows.md)).
- UK English. Commit messages: `fix: what changed (Refs #123)`.
- Published Releases also get a shared-hosting zip from `release-package.yml` (`release: published` only): `easm-X.Y.Z.zip`, `SHA256SUMS` and a minisign signature. Public key: `resources/keys/easm-release.minisign.pub`. Private key: repository secret `MINISIGN_SECRET_KEY` (never in git).
- Pre-install host check: `/check.php` (plain PHP under `install/requirements/`). Herd serves it via `LocalValetDriver.php` (must extend `Valet\Drivers\LaravelValetDriver`).
- Update recovery: `/recover.php?token=…` (plain PHP under `install/recovery/`). Token lives in `storage/app/update/recovery.token` only while an update is in progress.
- Shared hosting: prefer document root `public/`. Browser installer at `/install`.

## Decisions and gotchas

- Laravel Herd is the only local stack; do not document Laragon as current practice.
- Herd MySQL can show as `error` in the status table while port 3306 answers; trust the preview script's port check.
- Do not run `php artisan config:cache` on Cloudron (MySQL credentials can rotate).
- Minisign public key id `0263FE993EE2A87C`.

## Where to find things

- Shipping: [docs/shipping.md](docs/shipping.md) · Reports: [docs/reports.md](docs/reports.md) · Workflows: [docs/workflows.md](docs/workflows.md)
- Install: [docs/install.md](docs/install.md) · [INSTALL.md](INSTALL.md) · [UPGRADING.md](UPGRADING.md)
- Roadmap: [docs/roadmap.md](docs/roadmap.md) · Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
- Cursor skills: [.cursor/skills/](.cursor/skills/)
