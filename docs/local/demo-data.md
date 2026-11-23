# Demo data and Developer QA login

The demo data is clearly fake and only for the local Herd preview. `database/seeders/DemoSeeder.php` uses Faker in `en_GB` with a fixed seed (`20261109`), so every run gives the same records. Never enable this on Cloudron, staging or production, and never point it at real APES data.

## Commands

| Command | What it does |
| --- | --- |
| `php artisan demo:seed` / `composer demo:seed` | Loads or refreshes the demo data (idempotent) |
| `php artisan demo:seed --fresh` | Empties the local database (`migrate:fresh`), then loads the demo data |
| `php artisan demo:reset` / `composer demo:reset` | Alias of `demo:seed --fresh` |

Both refuse unless:

- `APP_ENV=local`
- the database host is on this computer (`127.0.0.1` / `localhost`), or SQLite for tests
- no Cloudron MySQL variables are set
- the database name is `easm` (this machine’s Herd site) or ends in `_local` (as in `.env.herd.example`)

Nothing here is real: never copy real animal, person or medical records into a local database. The installer never runs the seeder or turns on QA login.

## What it creates

- An organisation, "Demo Exotic Rescue (fake data)", if none exists.
- 10 enclosures (`Demo ENC-01` to `Demo ENC-10`), including a quarantine enclosure.
- 30 people with `demo-person-NN@example.test` emails, Ofcom drama-range phone numbers (`07700 900001` to `07700 900030`), "Demo Street" addresses and `ZZ99` postcodes (a postcode area that does not exist).
- 40 animals (`DEMO-0001` to `DEMO-0040`) across reptiles, primates and birds, with intake movements, medical records, diets, and some quarantine / adoption movements.
- At least one CITES Article 10 certificate, one DWA licence (with an inspection), enclosure logs/equipment and demo stock.
- Diary tasks linked to the QA admin, staff and volunteer accounts so switching role shows work for that role.

## Test logins

One per role, all with the password `password` (from `DevUserSeeder`, which refuses outside local and testing):

| Role | Email |
| --- | --- |
| Admin | `admin@easm.test` |
| Staff | `staff@easm.test` |
| Volunteer | `volunteer@easm.test` |
| Read only | `readonly@easm.test` |

`php artisan db:seed` creates them when `APP_ENV=local`, or run `php artisan db:seed --class=DevUserSeeder`. `demo:seed` also creates them.

## Developer QA login (local Herd only)

Add to your local `.env` (already set in `.env.herd.example`):

```env
QA_LOGIN_ENABLED=true
DEV_AUTO_LOGIN=false
DEV_AUTO_LOGIN_ROLE=admin
```

- **`QA_LOGIN_ENABLED=true`** with **`APP_ENV=local`** turns on the amber **Developer sign-in** panel on `/login` (tagged Local only), the `/qa` fallback page, the in-app **Local QA — signed in as {role}** banner, and the sidebar role switcher. Sign-in and switch use CSRF-safe `POST` forms (`/qa/login`, `/qa/logout`).
- When the gate is off, or `APP_ENV` is not `local` (including production, staging and testing), the panel and banner are absent and QA routes return **404**.
- The installer never sets `QA_LOGIN_ENABLED` or `DEV_AUTO_LOGIN` to true; it writes `APP_ENV=production`.
- Optional **`DEV_AUTO_LOGIN=true`**: guests are signed in as `DEV_AUTO_LOGIN_ROLE`. It is ignored unless the QA gate is on and `APP_ENV=local`. Signing out pauses auto-login for that browser session; pick a role on the login page to resume. `/login`, `/adopt`, `/apply` and `/feeds` are never auto-logged-in.
- Legacy `GET /dev/login-as/{role}` still works behind the gate; prefer the POST panel and switcher.
- An older local `.env` that predates these keys is fixed by re-running `scripts\herd-setup.ps1`: it appends any missing `QA_LOGIN_ENABLED` / `DEV_AUTO_LOGIN` / `DEV_AUTO_LOGIN_ROLE` values from `.env.herd.example` and never overwrites keys that are already set. It warns when `APP_ENV=local` but `QA_LOGIN_ENABLED` is explicitly false.

After changing flags, run `php artisan config:clear` if config is cached.
