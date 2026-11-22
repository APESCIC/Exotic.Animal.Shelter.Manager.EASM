---
name: work-on-issue
description: Work one GitHub issue on the milestone branch - Started comment, implement and commit on the version branch, Committed comment, leave the issue open until the milestone PR merges. Use for every issue in a version, or when the user says "work on issue #n" or "next issue".
---

# Work on one issue

One issue at a time, in the planned order. Shipping follows Cursor's `github-milestone-completion` and `github-closeout` skills (Started / Note / Committed comments). Terms: `docs/glossary.md`.

## 1. Check before starting

1. The live preview gate has passed on the version branch (`local-setup` skill).
2. The previous planned leaf has a **Committed** comment (issues stay open until the milestone PR merges into `main`).
3. The issue is in this version's milestone: `gh issue view <n> --json milestone,assignees,title`. If it isn't, **stop and ask**.

## 2. Start

1. If nobody is assigned: `gh issue edit <n> --add-assignee apescic-director`.
2. Stay on the version branch (`composer milestone:next` prints it). Run `repo-sync`. Commit on that branch only; the single milestone PR into `main` already covers every leaf.
3. Post the **Started** comment *before* writing any code (`Add-IssueUpdate.ps1 -Kind Started`): branch, PR number, and a short step list.

## 3. Do the work

1. Make small commits on the milestone branch: `<type>: <what changed> (Refs #<n>)`.
2. Push so the draft milestone PR's CI runs.
3. Run `composer test` and `composer lint` (`composer format` fixes style), and check the change in the preview.
4. If the plan or approach changes, post a **Note** or **Decision** comment.

## 4. Committed (do not close)

1. Post **Committed** (`Add-IssueUpdate.ps1 -Kind Committed`) with SHAs and what was verified. Update the milestone status block.
2. Do **not** close the issue here. It closes when the one milestone PR merges into `main` (`Closes #<n>`).
3. Mark the plan todo completed, then take the next leaf.

## 5. Next

- Go back to step 1 with the next issue in the plan.
- If an issue should move to a later version, **stop and ask**.
- When every leaf is committed and only the Epic is left open as a parent, run the `finish-version` skill (version bump, PR ready, ship gate).
