---
name: release-version
description: Ship a completed version - set VERSION once with composer milestone:bump, mark the milestone PR ready, get CI green, ask the ship-gate question, then merge once (merge commit), push the vX.Y.Z tag (release.yml builds the image and publishes the Release), deploy that tag with deploy-cloudron.yml and close out. Use when every issue in the milestone is committed, or when the user asks to merge, release, tag or deploy the milestone.
---

# Release a version

Follows `.cursor/rules/version-shipping.mdc` and the `ship-gate` skill.

## Steps

1. **Sync.** Run `scripts\preview.ps1` (it merges `origin/main` into the milestone branch).
2. **Set the version once.** `composer milestone:bump -- vX.Y.Z` (it refuses a version that is not the next milestone's), then `composer milestone:bump -- vX.Y.Z --check`.
3. **Last commit.** `chore: release vX.Y.Z`. Push.
4. **Ready.** `gh pr ready <pr>`, then `gh pr checks <pr> --watch`. Fix failures on the same branch.
5. **Check the names.** `composer milestone:check-release -- vX.Y.Z "<release title>"`, using the "Release title" from `composer milestone:next`.
6. **Ship gate.** Summarise the milestone, PR, checks, merge method, tag, image and deploy target, then ask with exactly: a. Merge, b. Release, c. Deploy, d. Merge, release and deploy, e. Stop here. Run only what was chosen; after each single step ask again with what remains.
7. **Merge.** `gh pr merge <pr> --merge --delete-branch`.
8. **Release.** Tag the merge commit and push the tag; `release.yml` builds `ghcr.io/apescic/easm:<version>` and publishes the Release:

   ```powershell
   git fetch origin; git tag -a vX.Y.Z -m "vX.Y.Z" <merge-sha>; git push origin vX.Y.Z
   gh run watch (gh run list --workflow release.yml --limit 1 --json databaseId --jq '.[0].databaseId') --exit-status
   ```

9. **Deploy.** `gh workflow run deploy-cloudron.yml -f tag=vX.Y.Z`, then `gh run watch <id> --exit-status`. Evidence: the run URL and `https://easm.intranet.apes.org.uk/health` showing the version.
10. **Close out** the issues, the Epic and the milestone, then merge `origin/main` into `dependencies`. Post the completion message with the PR, merge commit, issues, release and deploy evidence.

## Never

- Squash or rebase the milestone PR, tag anything but the merge commit, or deploy `main` or a branch.
- Rewrite a past tag or Release.
- Report a step without its command output.
