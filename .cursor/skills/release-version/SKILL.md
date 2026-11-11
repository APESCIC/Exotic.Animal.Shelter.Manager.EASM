---
name: release-version
description: Release a merged version - tag the merge commit vX.Y.Z on main and push the tag, so release.yml checks it and publishes the one GitHub Release. Use after the version PR has merged into main and the ship-gate answer includes release, or when the user asks to tag or release the version.
---

# Release a version

Only after a ship-gate answer in this chat that includes release. Steps 3 and 4 of `docs/shipping.md`. Terms: `docs/glossary.md`.

1. **Check.** The version PR has merged into `main`: `gh pr view <pr> --json state,mergeCommit`. If it hasn't, **stop**.
2. **Find the merge commit.**

   ```powershell
   git fetch origin
   gh pr view <pr> --json mergeCommit --jq .mergeCommit.oid
   ```

3. **Tag it and push the tag.**

   ```powershell
   git tag -a vX.Y.Z -m "vX.Y.Z" <merge sha>
   git push origin vX.Y.Z
   ```

4. **Watch `release.yml`.** It checks the tag matches `VERSION` and is on `main`, builds and pushes the image (until v0.6.3), and publishes the Release.

   ```powershell
   gh run list --workflow release.yml --limit 1 --json databaseId,url
   gh run watch <id> --exit-status
   ```

5. **Check the Release.** `gh release view vX.Y.Z --json name,url,isDraft`. The name must be `vX.Y.Z <milestone name>`, and `isDraft` must be false.
6. **If it failed, stop and ask.** Never delete, move or re-push a tag, and never edit a published Release.
7. **Next.**
   - For "Merge, release and deploy", go straight on to the `deploy-version` skill.
   - Otherwise, ask the ship-gate question again with only "Deploy" and "Stop here".
