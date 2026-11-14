# Product constraints and roadmap

## Product constraints

- Self-hosted PHP/MySQL app for ordinary web hosting. One Composer install step. One shelter per install.
- Stack: **Laravel + PHP 8 + MySQL/MariaDB**.
- APES runs its own instance on a Cloudron LAMP app (since v0.6.1), deployed from each release tag by `deploy-cloudron.yml`; the custom Cloudron package was removed in v0.6.3. Nothing about it may change how ordinary hosting installs work.
- No OIDC or external staff login. Local admin must work on day one on every host. Do **not** copy MyAPES-Account's platform login.
- Do **not** fork Animal Shelter Manager (ASM / [sheltermanager.com](https://sheltermanager.com)). Use it as a feature baseline only.
- Exotic species stay free-text. Animal records get a **primary photo** plus enclosure/CITES/DWA on #12 (the primary photo does not wait for v0.3 media / #18).
- Animal control (#24) is municipal ASM; it must **not** block v1.0.0.
- UK locale/timezone defaults.

## Implement order

Finish a milestone before starting the next. The plan epic is [#1](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/1) (no milestone).

Status (11/11/2026): v0.1.0 to v0.6.0 are done. Next are the patch versions v0.6.1 PWA icon fix, v0.6.2 Repo standards and v0.6.3 Move to Cloudron LAMP, then v0.9.0, starting with #77. `composer milestone:next` always prints the next one. Live progress is on the [milestones page](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestones?state=all).

### v0.1.0 Foundation: generic hosting ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.1.0+Foundation%3A+generic+hosting%22), epic #2) — done

1. #9 scaffold (Laravel + PHP 8 + MySQL)
2. #10 installer wizard
3. #11 auth/roles (admin, staff, volunteer, readonly)
4. #15 settings (org, UK locale, timezone)

### v0.2.0 Animals, people and movements ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.2.0+Animals%2C+people+and+movements%22), epic #3) — done

1. #12 animal records (photo, enclosure, CITES, DWA)
2. #14 people/contacts
3. #13 movements (intake, hold, quarantine, foster, trial adoption, adopt, reclaim, transfer, deceased — first movements; quarantine for exotic intake)
4. #28 lost/found matching

### v0.3.0 Medical, diary and care ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.3.0+Medical%2C+diary+and+care%22), epic #4) — done

1. #16 vaccinations/tests/treatments/diets
2. #17 staff diary/tasks
3. #18 media attachments
4. #31 custom fields (after #12 enclosure/CITES/DWA; does not block first animal records)

### v0.4.0 Public adoption and applications ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.4.0+Public+adoption+and+applications%22), epic #5) — done

1. #19 adoptable website and public API
2. #20 online applications

### v0.4.5 UI and UX modernisation ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.4.5+UI+and+UX+modernisation%22), epic #56) — done

1. #56 design tokens, light/dark, sidebar and top bar layout, badges (plain CSS/JS under `public/`; no npm build for shelters)
2. #60 dashboard alerts and counters
3. #58 global search and command palette
4. #59 animal record tabs and sticky save

### v0.5.0 Documents, publishing, finance and stock ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.5.0+Documents%2C+publishing%2C+finance+and+stock%22), epic #6) — done

1. #21 document templates/e-sign
2. #22 publish to partner sites (generic CSV/JSON/RSS partner feed; see [publishing.md](publishing.md))
3. #23 donations/fees/Gift Aid/sponsorship (Gift Aid export is a draft until #110 checks it against the HMRC spec)
4. #65 stock control for food and medical
5. #66 waiting list with urgency

Also shipped: #67 Dependabot fixes. #47 (Laravel Boost) was dropped because it conflicts with removing AI-agent files (#99).

### v0.5.5 Enclosures, husbandry and compliance ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.5.5+Enclosures%2C+husbandry+and+compliance%22), epic #115) — done

1. #61 enclosures as a first-class entity
2. #62 environment and equipment logs (reminders run when the dashboard or diary loads, or via `php artisan easm:reminders`; no cron)
3. #63 CITES register and Article 10 tracking (legal check still open)
4. #64 DWA licence and inspection tracking (legal check still open)

Also shipped: #116 development auto-login and QA role accounts (local only).

### v0.6.0 Reporting, rota and mobile ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.6.0+Reporting%2C+rota+and+mobile%22), epic #8) — done

1. #25 occupancy, intake/outcome and animal timeline reports with CSV
2. #26 mobile PWA care tasks (offline queue, enclosure QR labels and scan, camera photo, e-sign) and staff/volunteer rota
3. #122 CITES Annex X exemption, #123 export permit cover for 6 months, #126 interim Article 10 wording (APHA follow-up is #134 in v1.0.0)
4. #124 DWA renewal reminder lead time setting (default 60 days), #125 default DWA expiry two years after issue

Also shipped: #114 GitHub Actions on Node 24 runtimes.

### v0.9.0 Install and update system ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v0.9.0+Install+and+update+system%22), epic #76)

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

### v1.0.0 Production-ready self-host ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v1.0.0+Production-ready+self-host%22), epic #7)

1. #27 backup/security/first production install pack
2. #34 bounded /health DB timeout and nginx front-controller docs
3. #68 per-record audit log
4. #134 apply APHA's answer on adoption-fee rehoming and Article 10

### v1.1.0 Optional: animal control ([issues](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues?q=milestone%3A%22v1.1.0+Optional%3A+animal+control%22))

1. #24 animal control (municipal ASM; does not block v1.0.0)
