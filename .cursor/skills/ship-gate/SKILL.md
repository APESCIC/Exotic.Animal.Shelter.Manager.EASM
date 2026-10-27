---
name: ship-gate
description: EASM ship flow — GitHub issue/milestone tracking, pre-commit verification, commit, PR, merge, GitHub Release and deploy to the APES Cloudron instance, with next-options menus. Use when starting product work, when work is ready to commit, when PR checks finish, after merge, or when the user asks to ship, release or deploy.
---

# Ship flow

Run this end to end without asking **while every check is green**. Stop at the first failure or open decision and end the turn with the next-actions menu (below).

Deploy target: the APES-owned Cloudron app, id in repo variable `CLOUDRON_APP_ID` (`7afd5173-8880-4e64-9c36-5f978d9c8f64`, `https://easm.intranet.apes.org.uk`). Shelters still self-host from a clone or zip; agents never deploy to shelter hosting.

## Next-actions menu

End every turn that stops, finishes, or needs a choice with:

```markdown
## Next actions
**Recommended:** <one clear choice and why>

1. ...
2. ...
3. ...
```

## 1. Start: issue, milestone, branch

```powershell
gh issue list --state open --search "<keywords>"
gh api repos/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestones --jq '.[] | [.number,.title,.open_issues] | @tsv'
```

- Reuse an open issue when one fits; otherwise create one (`[Chore]`, `[Bug]`, `[Story]`… title prefix, `area:` labels, assignee `apescic-director`, milestone from AGENTS.md implement order).
- Split larger work into sub-issues and link them to the parent:

```powershell
$child = gh api repos/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/<child> --jq .id
gh api -X POST repos/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/<parent>/sub_issues -F sub_issue_id=$child
```

- Branch off `main`: `git switch -c cursor/{feature|fix|chore}/<n>-<slug>`.

## 2. While working

- Comment on the issue at each meaningful step (`gh issue comment <n> --body ...`): decisions, blockers, scope changes.
- Tick acceptance criteria in the issue body as they land (`gh issue edit <n> --body-file ...`).
- Follow `.cursor/rules/local-dev.mdc` (Laragon, no Pint mid-work).

## 3. Pre-commit verification (required)

Source `scripts/local/use-laragon.ps1` first.

| Step | Command / check | Pass criteria |
|------|-----------------|---------------|
| Tests | `composer test` | Exit 0 (full suite) |
| Lint | `composer lint` | Exit 0; if fail → one `composer format` → re-lint once |
| UI smoke | Laragon `http://easm.test` | When HTTP/views/routes changed: `/health` ok; changed path loads |
| Image | `docker build -t easm-cloudron:local .` | When `Dockerfile`, `cloudron/` or `CloudronManifest.json` changed |

Any failure: report it, fix or stop with the menu. Never commit broken work unless the user asked for a WIP checkpoint. No `.env` or credentials staged.

## 4. Commit, PR, CI

```powershell
git status; git diff; git log -5 --oneline
git add <files>; git commit -m "<why-focused message>"
git push -u origin HEAD
gh pr create --title "<title>" --body "Fixes #<n>`n`n<summary and test plan>"
gh pr checks --watch --fail-fast
```

Bump `APP_VERSION` in `.env.example` and the fallback in `config/app.php` to the release version as part of the PR.

CI red → fix on the branch and push again. Stop with the menu if the fix is not clear.

## 5. Merge

```powershell
gh pr merge --merge --delete-branch
git switch main; git pull --ff-only
gh run list --branch main --workflow CI --limit 1
```

Confirm the merge commit is on `main` and `main` CI is green. Close any finished sub-issues and comment the PR link on the parent.

## 6. Release

- **Patch** (`vX.Y.Z+1`) for each merged PR.
- **Minor** (`vX.Y+1.0`) when the merge closes the last open issue in a milestone; then close the milestone:

```powershell
gh api -X PATCH repos/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestones/<number> -f state=closed
```

```powershell
gh release list --limit 3
gh release create "vX.Y.Z" --target main --title "vX.Y.Z <short public title>" --notes "<public-safe summary, Fixes #n>"
```

Release notes: public-safe only (no credentials, personal data or exploitable detail). Stop with the menu if the version is ambiguous.

## 7. Deploy to Cloudron

Publishing the release triggers `.github/workflows/deploy-cloudron.yml`: build `ghcr.io/apescic/easm:<version>`, `cloudron update --app $CLOUDRON_APP_ID --image ...`, then check `/health` reports the version.

```powershell
gh run list --workflow "Deploy to Cloudron" --limit 1
gh run watch <run-id> --exit-status
```

Manual re-deploy of a tag: `gh workflow run "Deploy to Cloudron" -f tag=vX.Y.Z`.

Required repo settings (stop and ask the user to add any that are missing; never invent values):

| Name | Kind | Value |
|------|------|-------|
| `CLOUDRON_APP_ID` | variable | `7afd5173-8880-4e64-9c36-5f978d9c8f64` |
| `CLOUDRON_SERVER` | secret | Cloudron dashboard domain (e.g. `my.cloudron.apes.org.uk`) |
| `CLOUDRON_TOKEN` | secret | Cloudron API token (Profile → API Tokens) |

The image is pushed to GHCR with the workflow's `GITHUB_TOKEN`; the `easm` container package must be public (or the GHCR registry configured in Cloudron) so Cloudron can pull it.

The first deploy switches the app from its current LAMP package to EASM. The workflow refuses until run manually with `first_deploy=true` (Cloudron takes a backup first). Treat that as a decision point: stop and offer it as an option.

## 8. Completion

Report: PR link, short merge SHA, target branch, release URL, deploy run URL, `/health` version, and issues/milestone closed. Offer to archive the agent; never archive silently. Finish with the next-actions menu (usually the next issue in the AGENTS.md implement order).
