---
name: version-status
description: Read-only report on where the next version stands - its milestone, open and closed issues, the version branch, issue PRs, the version PR into main and its checks. Use when the user asks for status, progress, "where are we", "what's left" or "what's next" on a version.
---

# Version status (read-only)

Changes nothing. Terms: `docs/glossary.md`.

1. **Find the version.** `composer milestone:next`. Note the milestone title, version, version branch and PR title. If the user named a different version, use that one instead.
2. **Issues.**

   ```powershell
   gh issue list --milestone "<milestone title>" --state all --json number,title,state,assignees --limit 100
   ```

3. **Open issue PRs into the version branch.** `gh pr list --base <version branch> --json number,title,url`
4. **The version PR into `main`.** `gh pr list --base main --head <version branch> --json number,title,isDraft,url`, then `gh pr checks <pr>`.
5. **Ready to close?** `composer milestone:status -- vX.Y.Z` lists the open issues; exit 1 means some are still open.
6. **Report** in the template in `docs/reports.md`. Cover:
   - the version and milestone;
   - issues done and left, in plan order;
   - open PRs and red checks, with links;
   - the next step, naming the skill to run (`work-on-issue`, `finish-version`, `release-version` or `deploy-version`).
7. If anything is red or blocked, say what is needed. **Stop:** don't fix it from this skill.
