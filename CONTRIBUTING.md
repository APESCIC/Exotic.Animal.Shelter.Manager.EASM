# Contributing to EASM

Please write in UK English. Setup, tests and releases are in the [README](README.md). Local development uses Laragon only: run `scripts\laragon-setup.ps1` once, and `scripts\preview.ps1` before any work.

## How work ships

Each milestone is named by the version at the start of its GitHub milestone title (for example `v0.6.2 Repo standards`), never by GitHub's milestone number, and ships once: each issue gets a PR into one `milestone/vX-Y-Z-<slug>` branch, which is merged into `main` once with a merge commit, tagged and released as `vX.Y.Z`, and deployed. A hotfix ships earlier only with Bambie's written approval on its issue.

The names, the version fields, the flow and the hotfix route are in [`docs/shipping.md`](docs/shipping.md).

- `composer milestone:next` shows the next version, its branch and its pull request and release titles.
- `composer milestone:check-title -- "<title>"` and `composer milestone:check-branch -- "<branch>"` tell you what is wrong. The `milestone-version-check` CI job runs them on every pull request.
- To check your branch name before each push, run `git config core.hooksPath .githooks` once.
