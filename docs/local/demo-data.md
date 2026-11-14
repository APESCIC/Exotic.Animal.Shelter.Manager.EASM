# Demo data

The demo data is clearly fake and only for the local Herd preview. `database/seeders/DemoSeeder.php` uses Faker in `en_GB` with a fixed seed (`20261109`), so every run gives the same records.

| Command | What it does |
| --- | --- |
| `php artisan demo:seed` | Loads the demo data into the local database |
| `php artisan demo:reset` | Empties the local database (`migrate:fresh`), then loads the demo data |

Both refuse unless `APP_ENV=local` and the database is on `127.0.0.1` or `localhost`. Nothing here is real: never copy real animal, person or medical records into a local database.

## What it creates

- An organisation, "Demo Exotic Rescue (fake data)", if none exists.
- 10 enclosures (`Demo ENC-01` to `Demo ENC-10`).
- 30 people with `demo-person-NN@example.test` emails, Ofcom drama-range phone numbers (`07700 900001` to `07700 900030`), "Demo Street" addresses and `ZZ99` postcodes (a postcode area that does not exist).
- 40 animals (`DEMO-0001` to `DEMO-0040`) across the enclosures, each with an intake movement and a medical record; some adopted, some adoptable, some with diary tasks for the staff login.

## Test logins

One per role, all with the password `password` (from `DevUserSeeder`, which refuses outside local and testing):

| Role | Email |
| --- | --- |
| Admin | `admin@easm.test` |
| Staff | `staff@easm.test` |
| Volunteer | `volunteer@easm.test` |
| Read only | `readonly@easm.test` |

`php artisan db:seed` creates them when `APP_ENV=local`, or run `php artisan db:seed --class=DevUserSeeder`.

## Skip the login screen

Add to your local `.env`:

```env
DEV_AUTO_LOGIN=true
DEV_AUTO_LOGIN_ROLE=admin
```

- Guests are then signed in as the test login for `DEV_AUTO_LOGIN_ROLE`; missing accounts are created on first use.
- The sidebar gains a **Development: sign in as** list to switch roles, also reachable at `/dev/login-as/{role}`.
- Signing out pauses auto-login for that browser session, so the login page stays usable; pick a role on the login page to resume.
- The installer, `/login`, `/adopt`, `/apply` and `/feeds` are never auto-logged-in.
- To turn it off, set `DEV_AUTO_LOGIN=false` (then `php artisan config:clear` if config is cached).

Auto-login and `/dev/login-as` only work when `APP_ENV=local`. In any other environment the flag is ignored and the route returns 404.
