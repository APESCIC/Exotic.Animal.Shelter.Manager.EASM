# Agent instructions for EASM

Guidance for AI coding agents (Cursor and others). Setup, tests and releases are in the [README](README.md).

## Local development (Laragon)

Laragon on Windows is the only supported local set-up for developing EASM: <http://easm.test>, PHP 8.3 through FastCGI in its own vhost, MySQL 8.4 (`easm_local`), Browsersync on port 5176. Before any development or coding work, run the live preview gate in the [`local-setup`](.cursor/skills/local-setup/SKILL.md) skill (`scripts\preview.ps1`, then open the preview in Cursor's browser). Profile: [docs/laragon/profile.md](docs/laragon/profile.md). Demo data and logins: [docs/laragon/demo-data.md](docs/laragon/demo-data.md). The `Dockerfile` and `CloudronManifest.json` are only the Cloudron package; never use them for local development.

## Shipping and versioning

Every milestone ships once, by its version (`v0.6.2`), never by GitHub's milestone number: one PR per issue into the `milestone/vX-Y-Z-<slug>` branch, one merge commit into `main`, one tag, one Release and one deploy of that tag. The detail is [`docs/shipping.md`](docs/shipping.md). Agents run it with the skills in [`.cursor/skills/`](.cursor/skills/), starting with `/start-next-version`.

- `VERSION` is the single source of the version; `composer milestone:bump -- vX.Y.Z` syncs every other field.
- `composer milestone:next`, `milestone:check-title`, `milestone:check-branch` and `milestone:check-release` (`scripts/milestone-version`). CI runs the title and branch checks on every pull request.
- Dependabot opens grouped weekly pull requests into the `dependencies` branch, never `main`.
- Security checks use only free GitHub features: Dependabot, dependency review and CodeQL (JavaScript and Actions; CodeQL has no PHP support, so PHP is covered by the tests and Pint). CodeQL runs as GitHub's default setup, enforced by the organisation; a CodeQL workflow file would fail alongside it, so the repo has none.
- Optional local check before each push: `git config core.hooksPath .githooks`.
