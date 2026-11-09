# Contributing to EASM

Please write in UK English. Setup, tests and releases are in the [README](README.md).

## Milestone versioning

Milestones are named and shipped by version, never by GitHub's milestone number: branch `milestone/v0-6-1-pwa-icon-fix`, PR title `[v0.6.1] PWA icons 404 in production (#137)`, tag `v0.6.1`.

- `composer milestone:next` shows the next milestone's version and branch name; `composer milestone:pr-title -- <issue>` gives the PR title.
- `composer milestone:check-title -- "<title>"` and `composer milestone:check-branch -- "<branch>"` tell you what is wrong. The `milestone-version-check` CI job runs them on every pull request.
- To check your branch name before each push, run `git config core.hooksPath .githooks` once.

Cursor agents follow [`.cursor/rules/milestone-versioning.mdc`](.cursor/rules/milestone-versioning.mdc) and the skills in `.cursor/skills/`. See [AGENTS.md](AGENTS.md#milestone-versioning).
