---
name: work-on-issue
description: Work one GitHub issue from start to close - assign it, post Work started, branch from the version branch, commit, open a PR into the version branch, post Progress updates, merge when CI is green, post Work complete, close the issue and confirm it is closed before the next one. Use for every issue in a version, or when the user says "work on issue #n" or "next issue".
---

# Work on one issue

One issue at a time, in the planned order. The comment templates are in `docs/reports.md`; use them word for word. Post each comment with `gh issue comment <n> --body-file <file>`. Terms: `docs/glossary.md`.

## 1. Check before starting

1. The live preview gate has passed on the version branch (`local-setup` skill).
2. The previous issue in the plan is closed: `gh issue view <previous> --json state` shows `CLOSED`. If it isn't, finish that one first.
3. The issue is in this version's milestone: `gh issue view <n> --json milestone,assignees,title`. If it isn't, **stop and ask**.

## 2. Start

1. If nobody is assigned: `gh issue edit <n> --add-assignee apescic-director`.
2. Get the branch name: `composer milestone:branch -- --issue <n>`, for example `chore/151-shipping-docs`.
3. Branch from the up-to-date version branch:

   ```powershell
   git switch <version branch>; git pull --ff-only; git switch -c <issue branch>
   ```

4. Post the **Work started** comment *before* writing any code: the branch, a 3 to 5 step plan, and the expected checks.

## 3. Do the work

1. Make small commits: `<type>: <what changed> (Refs #<n>)`, for example `fix: keep the animal filter on reload (Refs #123)`.
2. Run `composer test` and `composer lint` (`composer format` fixes style), and check the change in the preview.
3. If the plan changes, post a **Progress update** saying how and why.

## 4. Pull request into the version branch

1. Push: `git push -u origin HEAD`.
2. Get the title: `composer milestone:pr-title -- <n>`, which gives `[vX.Y.Z] <summary> (#<n>)`.
3. Open the PR. Fill the body from `.github/pull_request_template.md`, with `Closes #<n>`.

   ```powershell
   gh pr create --base <version branch> --title "<title>" --body-file <file> --assignee apescic-director
   ```

4. Post a **Progress update**: PR opened, with its link.
5. Watch the checks: `gh pr checks <pr> --watch`.
   - **Red:** post a Progress update saying what failed and what you're doing, fix it on the same branch, push, then post another when it's green.
   - **Still red after one fix, or blocked:** post a Progress update saying what you need, then **stop and ask**.
6. When every check is green, post a **Progress update**: ready for review.

## 5. Merge into the version branch

1. Merge with a merge commit:

   ```powershell
   gh pr merge <pr> --merge --delete-branch
   ```

   This merges into the version branch only. **Never** merge into `main` from this skill.
2. Check it merged: `gh pr view <pr> --json state,mergeCommit` shows `MERGED`.

## 6. Complete and close

1. Post the **Work complete** comment: what changed, how it was checked, the PR link (merged into the version branch), and anything Bambie needs to know or do.
2. Close the issue. GitHub only closes issues by itself on merges into `main`.

   ```powershell
   gh issue close <n> --reason completed
   ```

3. Check: `gh issue view <n> --json state,stateReason` shows `CLOSED` and `COMPLETED`. Don't start the next issue until it does.
4. Roll up to the parent.
   - Run `gh issue view <n> --json parent`. If there is a parent other than the Epic, list its sub-issues with `gh issue view <parent> --json subIssues`.
   - If every sub-issue is now `CLOSED`, post a Work complete comment on the parent (listing its sub-issues and their PRs), close it with `--reason completed`, and repeat for its own parent.
   - Never close the Epic here; it closes at close-out.

## 7. Next

- Go back to step 1 with the next issue in the plan.
- If an issue should move to a later version, **stop and ask**. With Bambie's approval, comment why, then run `gh issue edit <n> --milestone "<later milestone title>"`.
- When only the Epic is left open, run the `finish-version` skill.
