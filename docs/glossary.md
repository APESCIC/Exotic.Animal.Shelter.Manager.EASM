# Glossary

Each term in one plain sentence. Other docs link here instead of explaining terms again.

## GitHub and git

- **Repository (repo):** the project's folder of files and their full history, stored on GitHub.
- **Branch:** a separate line of work, so changes don't touch `main` until they are ready.
- **`main`:** the branch that holds what has been released.
- **Version branch:** the branch that collects every issue for one version, such as `milestone/v0-6-2-repo-standards`.
- **Issue branch:** a short-lived branch for one issue, such as `chore/151-shipping-docs`, merged into the version branch.
- **Commit:** a saved snapshot of changes with a short message.
- **Conventional Commits:** a commit message format that starts with a type, such as `feat:`, `fix:`, `docs:` or `chore:`, and here ends with `(Refs #<issue>)`.
- **Push:** sending your local commits to GitHub.
- **Pull request (PR):** a request to merge one branch into another, where the changes are reviewed and checked first.
- **Draft PR:** a pull request marked "not ready yet"; `gh pr ready` marks it ready.
- **Merge commit:** a commit that joins two branches and keeps every original commit in the history.
- **Squash:** collapsing many commits into one; we don't do it.
- **Rebase:** rewriting a branch's history onto a new starting point; we never do it on a shared branch.
- **Force-push:** overwriting the history on GitHub; never done on a shared branch.
- **Merge conflict:** two branches changed the same lines, so a person has to choose; stop and ask.
- **Issue:** a GitHub page for one piece of work, with comments and a status (open or closed).
- **Sub-issue and parent:** an issue nested under a bigger one, such as a Task under a Chore or Epic.
- **Epic:** the parent issue that sums up a milestone, titled `[Epic] vX.Y.Z ...`.
- **Milestone:** a GitHub group of issues for one version, titled `vX.Y.Z Name`.
- **Milestone number:** GitHub's internal counter for milestones; never use it in names.
- **Label:** a coloured tag on an issue, such as `area:docs`.
- **`gh`:** GitHub's command-line tool, used for issues, PRs, checks and releases.

## Versions and shipping

- **Semantic versioning (semver):** versions written `MAJOR.MINOR.PATCH`; bump MAJOR for breaking changes, MINOR for features and PATCH for fixes.
- **Next version:** the open milestone with the lowest version above the latest tag; `composer milestone:next` prints it.
- **Tag:** a fixed name on one commit, such as `v0.6.2`.
- **Release:** the GitHub page for a tag, with its notes; `release.yml` publishes it.
- **Release notes:** the list of what changed in a release, generated from the merged PRs.
- **Deploy:** putting a release onto the live server.
- **Ship gate:** the a-to-e question Bambie answers before any merge into `main`, release or deploy.
- **Hotfix:** an urgent fix shipped outside the normal version, only with Bambie's written approval on the issue.
- **Evidence:** proof a step happened, such as a run link or `/health` showing the version.

## Checks and automation

- **CI (continuous integration):** checks that GitHub runs automatically on every pull request.
- **Check:** one CI result on a pull request, such as `PHP 8.3` or `milestone-version-check`.
- **Required check:** a check that must pass before a PR can merge into `main`; set in the repository settings.
- **Workflow:** a file in `.github/workflows/` that tells GitHub Actions what to run and when.
- **GitHub Actions:** GitHub's free service that runs the workflows.
- **Secret:** a password or token stored in GitHub's settings for workflows; never printed or written down.
- **Dependabot:** GitHub's free bot that suggests dependency updates, here into the `dependencies` branch.
- **CodeQL and dependency review:** GitHub's free security checks for code and for new dependencies.
- **Pint:** the tool that checks and fixes PHP code style (`composer lint`, `composer format`).

## Local development

- **Laravel Herd:** the Windows app that runs EASM locally with nginx, PHP, MySQL and a mail catcher.
- **nginx:** the web server Herd uses to serve `https://easm.test`.
- **Isolate:** Herd's way of giving one site its own PHP version (`herd isolate 8.3`), so other local sites keep theirs.
- **FastCGI:** the way the web server runs PHP; `/health` reports it as `cgi-fcgi`.
- **Mail catcher:** Herd's local mail server (SMTP port 2525); it keeps every email the app sends, so nothing reaches a real inbox.
- **Browsersync:** the tool that reloads the browser when you save a file (`https://easm.test:5176`).
- **Live preview gate:** `scripts\preview.ps1` plus opening the local and live sites in Cursor's browser, done before any work.
- **Demo data:** fake records for local testing only (`php artisan demo:seed`).
- **`/health`:** a page that reports EASM's status and version, used to prove a deploy worked.
- **Cloudron:** the APES server platform that hosts the live EASM app.

## Cursor

- **Rule:** an instruction in `.cursor/rules/` that Cursor always follows, or follows for certain files.
- **Skill:** a step-by-step recipe in `.cursor/skills/<name>/SKILL.md`; type `/<name>` in chat to run it.
- **Agent:** the Cursor assistant doing the work in a chat.
