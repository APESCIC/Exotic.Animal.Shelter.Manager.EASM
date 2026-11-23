# Release checklist

How to bump the version number and what must be true before a tag ships. Shipping steps (merge, tag, deploy) follow Cursor's `ship-gate` skill and [shipping.md](shipping.md).

## When the number moves

| Bump | Use when |
| --- | --- |
| **PATCH** (`vX.Y.Z` → `vX.Y.Z+1`) | Bug fixes, docs and release tooling only. No new screens or behaviour. |
| **MINOR** (`vX.Y.Z` → `vX.Y+1.0`) | New features that stay compatible with existing installs. |
| **MAJOR** (`vX.Y.Z` → `vX+1.0.0`) | Removals or breaking changes (schema, config or operator steps that need a migration path). |

The milestone title sets the version. Issues never change it.

## Before the ship gate

1. Every leaf in the milestone has a **Committed** comment on the milestone branch.
2. `CHANGELOG.md` has an entry for this version at the top (Keep a Changelog).
3. Run `composer milestone:bump -- vX.Y.Z` as the **last** commit on the milestone branch so `VERSION` (and the other fields in `scripts/MilestoneVersion/fields.json`) match the milestone.
4. Local Herd preview reports the same version as `VERSION` (`GET /health`).
5. Mark the milestone PR ready and wait for CI to go green.

## After merge

1. Tag the merge commit `vX.Y.Z` and push the tag (`release.yml` publishes the GitHub Release).
2. Confirm `release-package.yml` attached `easm-X.Y.Z.zip`, `SHA256SUMS` and `easm-X.Y.Z.zip.minisig`. The workflow fails if the zip or signature is missing.
3. Deploy only after the ship-gate answer: `gh workflow run deploy-cloudron.yml --ref vX.Y.Z -f tag=vX.Y.Z`.
4. Confirm `https://easm.intranet.apes.org.uk/health` shows `"version":"X.Y.Z"`.
