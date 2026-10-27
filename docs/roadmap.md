# Product constraints and roadmap

## Product constraints

- Self-hosted PHP/MySQL app for ordinary web hosting. One Composer install step. One shelter per install.
- Stack: **Laravel + PHP 8 + MySQL/MariaDB**.
- APES runs its own instance on Cloudron (packaging: `Dockerfile`, `CloudronManifest.json`, `cloudron/`). That package must not change how ordinary hosting installs work.
- No Cloudron OIDC/staff login. Local admin must work on day one everywhere, Cloudron included. Do **not** copy MyAPES-Account Cloudron auth.
- Do **not** fork Animal Shelter Manager (ASM / [sheltermanager.com](https://sheltermanager.com)). Use it as a feature baseline only.
- Exotic species stay free-text. Animal records get a **primary photo** plus enclosure/CITES/DWA on #12 (the primary photo does not wait for v0.3 media / #18).
- Animal control (#24) is municipal ASM; it must **not** block v1.0.0.
- UK locale/timezone defaults.

## Implement order

Finish a milestone before starting the next. The plan epic is [#1](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/1) (no milestone).

### v0.1.0 Foundation: generic hosting ([milestone/1](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestone/1), epic #2)

1. #9 scaffold (Laravel + PHP 8 + MySQL)
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
