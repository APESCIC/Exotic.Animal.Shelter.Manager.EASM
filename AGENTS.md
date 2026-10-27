# AGENTS.md

Instructions for Cursor agents working on **Exotic Animal Shelter Manager (EASM)**. Do not invent extra product constraints.

## Product constraints

- Self-hosted PHP/MySQL app for ordinary web hosting. One Composer install step. One shelter per install.
- Stack: **Laravel + PHP 8 + MySQL/MariaDB**.
- APES runs its own instance on Cloudron (packaging: `Dockerfile`, `CloudronManifest.json`, `cloudron/`). That package must not change how ordinary hosting installs work.
- No Cloudron OIDC/staff login. Local admin must work on day one everywhere, Cloudron included. Do **not** copy MyAPES-Account Cloudron auth.
- Do **not** fork Animal Shelter Manager (ASM / [sheltermanager.com](https://sheltermanager.com)). Use it as a feature baseline only.
- Exotic species stay free-text. Animal records get a **primary photo** plus enclosure/CITES/DWA on #12 (do not wait for v0.3 media / #18 for the primary photo).
- Animal control (#24) is municipal ASM; it must **not** block v1.0.0.
- UK locale/timezone defaults.

## Implement order

Finish a milestone before starting the next.

**App Developer owns [#9](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/9)** (Laravel + PHP 8 + MySQL scaffold). Other Cursor runs must not open a second scaffold PR, touch #9, or start a scaffold branch.

### v0.1.0 Foundation: generic hosting ([milestone/1](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/1), epic #2)

1. #9 scaffold — App Developer only
2. #10 installer wizard
3. #11 auth/roles (admin, staff, volunteer, readonly)
4. #15 settings (org, UK locale, timezone)

### v0.2.0 Animals, people and movements ([milestone/2](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/2), epic #3)

1. #12 animal records (photo, enclosure, CITES, DWA)
2. #14 people/contacts
3. #13 movements (intake, hold, quarantine, foster, trial adoption, adopt, reclaim, transfer, deceased — first movements; quarantine for exotic intake)
4. #28 lost/found matching

### v0.3.0 Medical, diary and care ([milestone/3](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/3), epic #4)

1. #16 vaccinations/tests/treatments/diets
2. #17 staff diary/tasks
3. #18 media attachments
4. #31 custom fields (after #12 enclosure/CITES/DWA; does not block first animal records)

### v0.4.0 Public adoption and applications ([milestone/4](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/4), epic #5)

1. #19 adoptable website and public API
2. #20 online applications

### v0.4.5 UI and UX modernisation ([milestone/8](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/8), epic #56)

1. #56 design tokens, light/dark, sidebar and top bar layout, badges (plain CSS/JS under `public/`; no npm build for shelters)
2. #60 dashboard alerts and counters
3. #58 global search and command palette
4. #59 animal record tabs and sticky save

### v0.5.0 Documents, publishing, finance and stock ([milestone/5](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/5), epic #6)

1. #21 document templates/e-sign
2. #22 publish to partner sites
3. #23 donations/fees/Gift Aid/sponsorship
4. #65 stock control for food and medical
5. #66 waiting list with urgency

### v0.5.5 Enclosures, husbandry and compliance ([milestone/9](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/9))

1. #61 enclosures as a first-class entity
2. #62 environment and equipment logs
3. #63 CITES register and Article 10 tracking
4. #64 DWA licence and inspection tracking

### v0.6.0 Reporting, rota and mobile ([milestone/6](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/6), epic #8)

1. #25 reports/timeline
2. #26 touch-friendly tasks/rota

### v0.9.0 Install and update system ([milestone/11](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/11), epic #76)

Browser-only install and update on shared hosting (no shell, Composer, cron or Node for shelters).

1. #77 release package workflow (manual publish only)
2. #78 version and schema tracking
3. #79 standalone requirements checker (`check.php`)
4. #80 safe download, verification and pure-PHP extraction
5. #81 shared-hosting compatibility layer
6. #82 improved web installer
7. #83 update check and poor-man's scheduler
8. #84 resumable web updater
9. #85 install and upgrade documentation
10. #86 constrained-host test matrix in CI
11. #89 update state machine and lock
12. #90 manual release upload fallback
13. #91 rollback
14. #92 standalone recovery page (`recover.php`)
15. #93 progress UI, redacted logs and maintenance bypass

Optional CLI layers (not blocking): #87 `easm:install`, #88 `easm:update` / `easm:check`.

### v1.0.0 Production-ready self-host ([milestone/7](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/7), epic #7)

1. #27 backup/security/first production install pack
2. #34 bounded /health DB timeout and nginx front-controller docs
3. #68 per-record audit log

### v1.1.0 Optional: animal control ([milestone/10](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/10))

1. #24 animal control (municipal ASM; does not block v1.0.0)

Plan epic is #1 (unmiled).

## Local development (Windows)

Prefer **Laragon** for preview and CLI. See README “Local development” and `.cursor/rules/local-dev.mdc`.

- Source `scripts/local/use-laragon.ps1` before `php` / `composer` / tests.
- Preview: **http://easm.test** (Apache document root `public/`). Do not start `php artisan serve` when Laragon can serve the app.
- Interactive DB: Laragon MySQL on `127.0.0.1`. Do not use Docker hostnames (e.g. `easm-mysql`) for local smoke.
- Do **not** run Pint / `composer format` / `composer lint` mid-work. Lint once only in the ship-gate pre-commit verification block.

## Ship flow

Work starts and finishes from a Cursor agent request. Follow `.cursor/rules/ship-gate.mdc` and `.cursor/skills/ship-gate/SKILL.md`. While every check is green, run the whole flow without stopping:

1. **Track**: find or create the issue (and sub-issues), set milestone, labels and assignee; comment progress, decisions and blockers as work proceeds.
2. **Verify**: full `composer test`, one `composer lint`, Laragon UI smoke when HTTP changed, `docker build` when Cloudron packaging changed.
3. **Commit and PR** on the `cursor/...` branch with `Fixes #<n>`; watch CI until green.
4. **Merge** to `main` and confirm `main` CI.
5. **Release**: patch release per merged PR; minor release when a milestone closes (then close the milestone). Bump `APP_VERSION` in the PR.
6. **Deploy**: the published release triggers `.github/workflows/deploy-cloudron.yml`, which updates Cloudron app `CLOUDRON_APP_ID` (`7afd5173-8880-4e64-9c36-5f978d9c8f64`, `https://easm.intranet.apes.org.uk`). Confirm `/health` shows the new version.

Stop at the first failure, missing secret, first-time Cloudron switch (`first_deploy=true`), or unclear decision. Agents never deploy to shelter hosting.

**Always end with options:** whenever a choice is needed, or the turn ends, give a numbered list of the best next actions and mark **one recommended** option with a short reason.

Deploy needs repo secrets `CLOUDRON_SERVER` and `CLOUDRON_TOKEN` plus variable `CLOUDRON_APP_ID`. If any are missing, stop and ask the user to add them; never invent values.

## GitHub issues

- Search **this repo** for duplicates. Never open issues in other repos.
- Prefer `area:` labels if they exist. Do not invent `type:` or `priority:` labels. Use `[Epic]`, `[Story]`, `[Task]`, `[Bug]`, `[Chore]` title prefixes.
- Every issue gets a milestone from the implement order above (tooling chores may stay unmilestoned). Link sub-issues to their parent epic/story.
- Branch `cursor/{feature|fix|chore}/<issue-number>-<slug>`. Open PRs with `Fixes #<n>`.
- Close leaf issues via the PR; close parents and milestones only when all children are closed.
- Assignee: `apescic-director` unless another owner is named.
