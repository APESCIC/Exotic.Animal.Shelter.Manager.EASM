---
name: start-next-version
description: Start or resume the next version - run the preview gate, find the next milestone with composer milestone:next, name the agent after it, create or resume its version branch and draft PR into main, plan the issues, then work them one at a time. Use when the user says "complete the next version", "next version", "next milestone", "start the milestone" or "resume the milestone".
---

# Start (or resume) the next version

The flow is in `docs/shipping.md`. Terms: `docs/glossary.md`.

1. **Preview gate.** Run the `local-setup` skill. Don't go on until it says "Preview ready, starting work".
2. **Find the version.**

   ```powershell
   composer milestone:next
   ```

   **Stop and report** without guessing if it fails, prints a warning, or names a different milestone from the one the user asked for.
3. **Name the agent.** Rename this chat to the milestone title plus " – EASM", for example "v0.6.2 Repo standards – EASM". Keep it under 60 characters, using the version number and never the milestone number. If Cursor won't let you rename the chat, make the first line of your reply "Suggested agent name: …".
4. **Version branch.**
   - If the printed branch is missing on GitHub: `git switch -c <branch> origin/main`, then `git push -u origin HEAD`.
   - Otherwise: `git switch <branch>`, `git pull --ff-only`, then `git merge origin/main`.
   - Then `git merge origin/dependencies`. Both merges are merge commits.
   - On any conflict, **stop and ask**; never rebase.
   - Call `SetActiveBranch`.
5. **Draft PR into `main`.** If none exists:
   - List the issues: `gh issue list --milestone "<milestone title>" --state open --json number,title --limit 100`.
   - Open it: `gh pr create --draft --base main --head <branch> --title "<Milestone PR title>" --body-file <file>`.
   - Fill the body from `.github/pull_request_template.md`, with a `Closes #<n>` line for every issue including the Epic.
6. **Plan.** Run the `plan-work` skill for the whole version: the issue order, dependencies, and any issue that is too big and should be split. **Stop** and wait for Bambie's approval of the plan. When resuming in a new chat, continue the existing plan instead of making a new one.
7. **Work.** Run the `work-on-issue` skill for each issue in the approved order, one at a time.
8. **Finish.** When `composer milestone:status -- vX.Y.Z` shows only the Epic left open, run the `finish-version` skill.
