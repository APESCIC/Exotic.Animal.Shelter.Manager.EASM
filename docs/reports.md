# Reports and issue comments

One set of templates for everything Cursor (or a person) writes about work: the end of a task, a pull request description, release notes, and the comments on each issue. Write in plain UK English. Link to logs; don't paste them unless asked. Words you may not know are in the [glossary](glossary.md).

## Report template

Use it at the end of every task, in pull request descriptions (`.github/pull_request_template.md`) and in release notes.

```markdown
## What changed
Plain words: what is different now.

## Why
One or two lines.

## How it was checked
- `composer test`: passed (link to the CI run)
- Checked in the preview at https://easm.test: what you looked at

## What you need to do
Nothing / what Bambie or the IT Administrator needs to do.

## Explained
- **<term or decision>:** one plain sentence.
```

## Issue comments

Post these with `gh issue comment <n> --body-file <file>`. Use the agent's own GitHub sign-in; never put a token in a command, file or comment. Assign the issue to `apescic-director` first if nobody is assigned: `gh issue edit <n> --add-assignee apescic-director`.

### 1. Work started

Post before writing any code.

```markdown
**Work started**
- Branch: <branch name>
- Plan:
  - <3 to 5 short steps>
- Expected checks: <tests or manual checks that will prove it's done>
```

### 2. Progress update

Post at each meaningful point: the plan changed, the PR opened, CI failed (and what you're doing about it), CI fixed, blocked (and what you need from Bambie), ready for review. Don't comment on every commit.

```markdown
**Progress update**
- Done so far: …
- Next: …
- Blockers: none / what's needed
- Links: PR, CI run
```

### 3. Work complete

Post once the issue's PR has merged into the version branch and its checks passed. Then close the issue with `gh issue close <n> --reason completed`, and check that `gh issue view <n> --json state` shows `CLOSED` before starting the next issue.

```markdown
**Work complete**
- What changed: plain-English summary
- How it was checked: commands or tests and results
- PR: <link> (merged into milestone/vX-Y-Z-<slug>)
- Anything you need to know or do: … / nothing
```

## Release summary

Post on the Epic after the version is merged, released and deployed, then close the Epic and the milestone (see [shipping.md](shipping.md#close-out)).

```markdown
**Release summary**
- Version: vX.Y.Z <milestone name>
- Release: <link to the GitHub Release>
- Merge: <PR link>, merge commit <short sha>
- Deploy evidence: <deploy run link> and /health showing X.Y.Z, or "No deploy stage for this version"
- Issues shipped: #…, #…
```
