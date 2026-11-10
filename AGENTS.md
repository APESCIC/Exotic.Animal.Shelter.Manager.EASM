# Agent instructions for EASM

Guidance for AI coding agents (Cursor and others). Setup, tests and releases are in the [README](README.md).

## Local development (Laragon)

Laragon on Windows is the only supported local set-up for developing EASM: <http://easm.test>, PHP 8.3 through FastCGI in its own vhost, MySQL 8.4 (`easm_local`), Browsersync on port 5176. Before any development or coding work, run the live preview gate in [`.cursor/rules/laragon-local-dev.mdc`](.cursor/rules/laragon-local-dev.mdc) (`scripts\preview.ps1`, then open the preview in Cursor's browser). Profile: [docs/laragon/profile.md](docs/laragon/profile.md). Demo data and logins: [docs/laragon/demo-data.md](docs/laragon/demo-data.md). The `Dockerfile` and `CloudronManifest.json` are only the Cloudron package; never use them for local development.

## Shipping and versioning

Every milestone ships once, by its version (`v0.6.2`), never by GitHub's milestone number: every issue on one `milestone/vX-Y-Z-<slug>` branch behind one draft pull request, one merge commit into `main`, one tag, one Release and one Cloudron deploy of that tag. The single rule is [`.cursor/rules/version-shipping.mdc`](.cursor/rules/version-shipping.mdc). Agents run it with the [`complete-next-version`](.cursor/skills/complete-next-version/SKILL.md) and [`release-version`](.cursor/skills/release-version/SKILL.md) skills, or `/complete-next-version`.

- `VERSION` is the single source of the version; `composer milestone:bump -- vX.Y.Z` syncs every other field.
- `composer milestone:next`, `milestone:check-title`, `milestone:check-branch` and `milestone:check-release` (`scripts/milestone-version`). CI runs the title and branch checks on every pull request.
- Dependabot opens grouped weekly pull requests into the `dependencies` branch, never `main`.
- Security checks use only free GitHub features: Dependabot, dependency review and CodeQL (JavaScript and Actions; CodeQL has no PHP support, so PHP is covered by the tests and Pint).
- Optional local check before each push: `git config core.hooksPath .githooks`.
