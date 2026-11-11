---
name: deploy-version
description: Deploy a released version once with evidence (or record that this version has no deploy stage), then close out - check every issue is closed, post the release summary, close the Epic and the milestone. Use after the release is published and the ship-gate answer includes deploy, or when the user asks to deploy or close out the version.
---

# Deploy a version and close out

Only after a ship-gate answer in this chat that includes deploy. Deploy a published release tag only, never `main` or a branch. Which deploy applies is in `docs/shipping.md` under "4. Deploy". Terms: `docs/glossary.md`.

## Deploy

1. **Check the release is published.** `gh release view vX.Y.Z --json isDraft,url` shows `isDraft` false. If it doesn't, **stop**.
2. **Pick the deploy that applies:**
   - **`.github/workflows/deploy-cloudron.yml` exists (up to v0.6.2):**

     ```powershell
     gh workflow run deploy-cloudron.yml -f tag=vX.Y.Z
     gh run list --workflow deploy-cloudron.yml --limit 1 --json databaseId,url
     gh run watch <id> --exit-status
     (Invoke-RestMethod https://easm.intranet.apes.org.uk/health).version
     ```

     Evidence: the run link, and `/health` showing `X.Y.Z`.
   - **The workflow is gone (from v0.6.3, until the Cloudron LAMP move):** don't deploy anything. The evidence is the line "No deploy stage for this version" in the release summary.
   - **After the move to a Cloudron LAMP app:** **stop**. The assistant updates the app by hand with the web updater, with Bambie's approval. Evidence: `/health` showing the new version.
3. **If the deploy fails, stop and ask.** The workflow rolls back to the previous image by itself; report what it says. Never retry with `first_deploy=true` without Bambie's approval.

## Close out

1. **Check every issue is closed.** `composer milestone:status -- vX.Y.Z`. If it lists anything other than the Epic and a "[Task] Ship the milestone" issue, list it and **stop**: never close a milestone with open issues.
2. **Release summary.** Post the Release summary comment from `docs/reports.md` on the Epic.
   - If there is a "[Task] Ship the milestone" issue, post the same summary on it and close it: `gh issue close <task> --reason completed`.
   - Then close the Epic: `gh issue close <epic> --reason completed`.
3. **Run the check again.** `composer milestone:status -- vX.Y.Z` must say "Ready to close".
4. **Close the milestone.** Get its number from the title, then close it:

   ```powershell
   gh api "repos/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestones?state=open" | ConvertFrom-Json | Where-Object title -like "vX.Y.Z *" | Select-Object number,title
   gh api -X PATCH repos/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestones/<number> -f state=closed
   ```

5. **Bring `main` into `dependencies`.**

   ```powershell
   git switch dependencies; git pull --ff-only; git merge origin/main; git push; git switch -
   ```

6. **Final report** in the template in `docs/reports.md`, including:
   - the milestone, PR and merge commit;
   - the issues shipped;
   - the release link;
   - the deploy evidence;
   - confirmation that the Epic and the milestone are closed.
