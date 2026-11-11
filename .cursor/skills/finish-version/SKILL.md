---
name: finish-version
description: Finish a version once every issue is closed - merge main into the version branch, set the version with composer milestone:bump, mark the version PR ready, get CI green, ask the ship-gate question, and on "merge" merge it into main once with a merge commit. Use when every issue in the milestone is closed, or when the user asks to merge the version into main.
---

# Finish a version (the one merge into main)

The flow is in `docs/shipping.md`. Terms: `docs/glossary.md`.

1. **Check every issue is closed.** Run `composer milestone:status -- vX.Y.Z`.
   - It exits 1 while the Epic is open. That is expected here, because the Epic closes at close-out.
   - A "[Task] Ship the milestone" issue may also still be open; it closes with the Epic.
   - A parent issue whose sub-issues are all closed should already be closed (`work-on-issue` step 6). If one is still open, post Work complete on it and close it now.
   - If anything else is open, list it and **stop**.
2. **Bring `main` in.** On the version branch, run the `local-setup` skill (it merges `origin/main`), or run `git merge origin/main`. On a conflict, **stop and ask**.
3. **Set the version once.**

   ```powershell
   composer milestone:bump -- vX.Y.Z
   composer milestone:bump -- vX.Y.Z --check
   git commit -am "chore: release vX.Y.Z"
   git push
   ```

   The bump refuses any version that isn't the next milestone's. Never edit the version fields by hand.
4. **Ready.** `gh pr ready <pr>`, then `gh pr checks <pr> --watch`. Fix any failure on the version branch. If it's still red after one fix, **stop and ask**.
5. **Check the release name.** `composer milestone:check-release -- vX.Y.Z "<Release title from composer milestone:next>"`.
6. **Summarise** for Bambie:
   - the milestone and its issues (all closed);
   - the PR link and its checks;
   - the target: `main`, as one merge commit;
   - the version and tag;
   - the deploy target, and that nothing releases or deploys by itself.
7. **Ship gate.** Ask with AskQuestion, using exactly these options:
   - a. Merge
   - b. Release
   - c. Deploy
   - d. Merge, release and deploy
   - e. Stop here

   Append "(Recommended)" to d when everything is green, or to e when something is blocked. **Stop** and wait for the answer.
8. **On "merge"** (a or d): run `gh pr merge <pr> --merge`, never `--squash` or `--rebase`. Check `gh pr view <pr> --json state,mergeCommit` shows `MERGED`, and note the merge commit.
9. **Next.**
   - For d, go straight on to the `release-version` skill.
   - For a, ask again with only the remaining steps, plus "Release and deploy" and "Stop here".
