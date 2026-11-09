# Agent instructions for EASM

Guidance for AI coding agents (Cursor and others). Setup, tests and releases are in the [README](README.md).

## Milestone versioning

Every milestone, branch, PR title, release note, tag and release uses the milestone's version (`v0.6.1`), never GitHub's milestone number. The rule is [`.cursor/rules/milestone-versioning.mdc`](.cursor/rules/milestone-versioning.mdc).

- `composer milestone:next` prints the next milestone (the lowest open version above the latest tag) and its branch name. `composer milestone:pr-title -- <issue>` prints the PR title.
- `composer milestone:check-title -- "<title>"`, `milestone:check-branch -- "<branch>"` and `milestone:check-release -- vX.Y.Z "<release title>"` fail with the reason. CI runs the title and branch checks on every pull request (`.github/workflows/milestone-version-check.yml`).
- Skills: [`start-next-milestone`](.cursor/skills/start-next-milestone/SKILL.md), [`milestone-release-titles`](.cursor/skills/milestone-release-titles/SKILL.md) and `/next-milestone`. Script: `scripts/milestone-version`.
- Optional local check before each push: `git config core.hooksPath .githooks`.
