# AGENTS.md

Instructions for Cursor agents working on **Exotic Animal Shelter Manager (EASM)**. Do not invent extra product constraints. This repo may be README-only until the Laravel scaffold lands.

## Product constraints

- Self-hosted PHP/MySQL app for ordinary web hosting. One Composer install step. One shelter per install.
- Stack: **Laravel + PHP 8 + MySQL/MariaDB**.
- No Cloudron OIDC/staff login in v0.1. Local admin on generic hosting must work on day one. Do **not** copy MyAPES-Account Cloudron auth.
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

### v0.5.0 Documents, publishing and finance ([milestone/5](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/5), epic #6)

1. #21 document templates/e-sign
2. #22 publish to partner sites
3. #23 donations/fees

### v0.6.0 Animal control, reporting and mobile ([milestone/6](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/6), epic #8)

1. #24 animal control (does not block v1.0.0)
2. #25 reports/timeline
3. #26 touch-friendly tasks/rota

### v1.0.0 Production-ready self-host ([milestone/7](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/7), epic #7)

1. #27 backup/security/first production install pack

Plan epic is #1 (unmiled).

## Local development (Windows)

Prefer **Laragon** for preview and CLI. See README “Local development” and `.cursor/rules/local-dev.mdc`.

- Source `scripts/local/use-laragon.ps1` before `php` / `composer` / tests.
- Preview: **http://easm.test** (Apache document root `public/`). Do not start `php artisan serve` when Laragon can serve the app.
- Interactive DB: Laragon MySQL on `127.0.0.1`. Do not use Docker hostnames (e.g. `easm-mysql`) for local smoke.
- Do **not** run Pint / `composer format` / `composer lint` mid-work. Lint once only in the ship-gate pre-commit verification block.

## Ship-gate

Work starts and finishes from a Cursor agent request. Follow `.cursor/rules/ship-gate.mdc` and `.cursor/skills/ship-gate/SKILL.md`:

1. When a slice is ready, run the **pre-commit verification** (full `composer test`, one `composer lint`, Laragon UI smoke when HTTP was touched). On failure, fix or stop — do **not** present the commit gate.
2. Only after verification passes, present the **commit gate** (never commit silently).
3. Open/update the PR; watch CI until green; present the **merge gate**.
4. After merge on `main`, present the **GitHub Release gate** only as an opt-in — create a release when the operator decides, never silently.
5. **No deploy gate.** Shelters download/clone and install on their own hosting (Composer + `/install`). Do not invent Cloudron or CI deploy steps.

## GitHub issues

- Search **this repo** for duplicates. Never open issues in other repos.
- Prefer `area:` labels if they exist. Do not invent `type:` or `priority:` labels.
- Branch `cursor/{feature|fix|chore}/<issue-number>-<slug>`. Open PRs with `Fixes #<n>`.
- Assignee: `bmurphy-apescic` unless another owner is named.
