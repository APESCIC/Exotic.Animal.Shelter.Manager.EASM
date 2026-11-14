# What runs automatically

Every automatic check and job in this repository, what starts it, and what to do if it fails. Terms are in the [glossary](glossary.md). Workflow files are in `.github/workflows/`; the rules for changing them are in `.cursor/rules/github-workflows.mdc`.

## Workflows

| Workflow (check names) | Runs when | What it does | If it fails |
| --- | --- | --- | --- |
| `ci.yml`: `PHP 8.3`, `PHP 8.4` | Every pull request, and `main` after the version merge | Validates `composer.json`, installs dependencies (cached), runs Pint and all PHPUnit tests | Open the failed step. Run `composer lint` or `composer test` locally, fix, push. `composer format` fixes style. |
| `ci.yml`: `MySQL migrate` | Same | Runs every migration on an empty MySQL 8.0 database | A migration doesn't work on MySQL. Run `php artisan migrate:fresh` on your local Herd database to reproduce. |
| `milestone-version-check.yml`: `milestone-version-check` | Every pull request (opened, edited or pushed) | Checks the PR title and branch name carry the right version and no milestone number | Use `composer milestone:pr-title -- <issue>` for the title, and `composer milestone:branch -- --issue <issue>` for the branch name. Edit the title; the check re-runs. |
| `dependency-review.yml`: `dependency-review` | Every pull request | Fails if the PR adds a dependency with a known high or critical vulnerability | Pick a fixed version of that dependency, or ask Bambie. |
| `release.yml`: `Build image and publish the release` (name kept from the image build, removed in v0.6.3) | Only when a `vX.Y.Z` tag is pushed | Checks the tag matches `VERSION` and is on `main`, and publishes the GitHub Release | Stop and ask. Never move or re-push a tag. Usually `VERSION` wasn't bumped in the version PR. |
| `release-package.yml`: `Build and attach release package` | Only when a GitHub Release is published (`release: published`), never on ordinary push or pull request | Builds `easm-X.Y.Z.zip` with `composer install --no-dev` and static `public/` assets (no Node build), writes `SHA256SUMS`, signs with minisign (`MINISIGN_SECRET_KEY`), verifies with `resources/keys/easm-release.minisign.pub`, and attaches all three to the Release | Open the failed step. If the secret is missing, ask Bambie (`apescic-director` holds the private key). Never put the private key in the repository. |
| `deploy-cloudron.yml`: `Update the Cloudron app to the release image` (name kept from the image deploy) | Only by hand, from the tag: `gh workflow run deploy-cloudron.yml --ref vX.Y.Z -f tag=vX.Y.Z`. Runs in the `production` environment (only `v*` tags) | Refuses a tag without a published Release, builds the release with Composer, backs up the APES Cloudron LAMP app, uploads and activates the release (`deploy/cloudron/`), restarts, checks `/health` and `REVISION`, and switches back to the previous release otherwise | It has already switched back. Read the log and stop and ask. Migrations are not undone; the pre-deploy backup is in Cloudron. |

Nothing releases or deploys on an ordinary push or pull request. Every workflow starts with read-only permissions, widens them only for the job that needs them, pins each action to a commit, and doesn't keep the checkout's credentials.

## GitHub features without a workflow file

| Feature | What it does | Notes |
| --- | --- | --- |
| CodeQL (check `CodeQL`, `Analyze (...)`) | Scans JavaScript and the workflows for security problems on every pull request | Runs as GitHub's default setup, enforced by the organisation. A CodeQL workflow file would fail alongside it, so the repo has none. CodeQL has no PHP support; PHP is covered by the tests and Pint. |
| Dependabot (`.github/dependabot.yml`) | Weekly grouped update PRs for Composer, npm and GitHub Actions | PRs go into the `dependencies` branch, never `main`. Each version branch merges `origin/dependencies` when work starts. |

Extra checks from third-party apps installed across the APESCIC organisation are not set up in this repository; the APES IT Administrator manages them.

## Other automation and config

| File | What it is for | Kept because |
| --- | --- | --- |
| `.githooks/pre-push` | Optional: blocks a push whose branch name uses a milestone number or the wrong version | Catches the mistake before CI does. Turn it on with `git config core.hooksPath .githooks`. |
| `.github/pull_request_template.md` | The report layout for every PR description | One format for PRs, issues and release notes (`docs/reports.md`) |
| `.github/ISSUE_TEMPLATE/` | Task and Bug forms | Plain-English issues with the right title prefix and label |
| `.editorconfig` | UTF-8, LF line endings, 4-space indents (2 for YAML) | Editors follow it automatically |
| `.gitattributes` | Line endings and export rules | Consistent files across Windows and Linux |
| `composer.json` scripts | `test`, `lint`, `format`, `setup`, `dev`, `demo:*`, `milestone:*` | Listed in `AGENTS.md` under Everyday commands |
| `package.json` script `dev` | Browsersync live reload on port 5176 | Started by `scripts\preview.ps1` |
| `deploy/cloudron/` | `activate-release.sh`, `rollback-release.sh`, `run.sh`, `apache-app.conf` and `env.example` for the APES Cloudron LAMP app | Run on the app by `deploy-cloudron.yml`; see [install.md](install.md#the-apes-cloudron-instance) |
| `scripts/` | `preview.ps1` (live preview gate), `herd-setup.ps1` (first-time set-up), `milestone-version` (version tool), `ensure-env.php` (run by `composer install`) | Each is used by a documented step |

The repository has no CODEOWNERS, labeler, auto-assign or release-drafter config. With one maintainer and release notes generated by `release.yml`, they would add nothing.

## The `production` environment

`deploy-cloudron.yml` is the only workflow that uses Cloudron credentials, and it gets them only from the `production` environment (Settings → Environments → production). The repository itself holds no Cloudron secrets or variables.

| Setting | Value |
| --- | --- |
| Deployment rule | Tags matching `v*` only, so the deploy must run from a release tag (`--ref vX.Y.Z`); a run from a branch is refused before any secret is read |
| `CLOUDRON_SERVER` (secret) | `my.cloudron.apes.org.uk` |
| `CLOUDRON_TOKEN` (secret) | A Cloudron API token named `github-actions-easm`, used only here. Bambie creates it in the Cloudron dashboard and pastes it into the environment; it is kept in APES Vault |
| `CLOUDRON_APP_ID` (variable) | The APES Cloudron LAMP app (`cloudron list`) |

To replace the token: create a new one in Cloudron (profile → API Tokens), paste it over `CLOUDRON_TOKEN` in the environment, then revoke the old one in Cloudron. `gh secret list --env production` and `gh variable list --env production` show the names (never the values).

## Release package signing

`release-package.yml` signs `easm-X.Y.Z.zip` with [minisign](https://jedisct1.github.io/minisign/). The private key is the repository Actions secret `MINISIGN_SECRET_KEY` (held by `apescic-director`). The public key ships in the app at `resources/keys/easm-release.minisign.pub` (key id `0263FE993EE2A87C`). Shelters verify a download with:

```bash
minisign -Vm easm-X.Y.Z.zip -p resources/keys/easm-release.minisign.pub
sha256sum -c SHA256SUMS
```

## Required checks on `main`

These are set in the repository settings by the APES IT Administrator, not in this repository. They match the job names above, so job names must never be renamed: `PHP 8.3`, `PHP 8.4`, `MySQL migrate`, `milestone-version-check`, `dependency-review` and CodeQL.
