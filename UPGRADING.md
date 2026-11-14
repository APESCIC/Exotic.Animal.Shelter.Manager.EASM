# Upgrading EASM

How a shelter updates an installed copy using only a browser. First-time install: [INSTALL.md](INSTALL.md).

## Before every update

1. Sign in as an **admin**.
2. Create a backup from **Administration → Backups** (or confirm a recent ready backup exists). Downloads stay under `storage/app/backups` (never under `public/`).
3. Note the current version from **Administration** or `GET /health` (`version` field).
4. Read the [per-version notes](#per-version-notes) below for the versions you are crossing.

## Web updater (preferred)

1. Open **Administration → Update**.
2. Enter the target version (for example `0.9.0`).
3. **Option A — download:** paste the Release zip HTTPS URL and the `.minisig` URL from the [GitHub Release](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases), plus the SHA-256 from `SHA256SUMS`.
4. Confirm your password and start. Keep the tab open. Steps are short so a 30-second PHP limit is fine; use **Run remaining steps** if needed.
5. While maintenance is on, the public sees a 503 page. The progress page stays available after the maintenance bypass cookie is set (the page shows the bypass link).
6. When finished, `GET /health` should report the new version and `"status":"ok"`.

If a step fails, the updater rolls back automatically (previous code snapshot when swap had run, plus the pre-update database backup) and turns maintenance off.

### Manual upload (outbound HTTPS blocked)

Some shared hosts block outbound HTTPS. On **Administration → Update**, use **Option B**:

1. On your PC, download `easm-X.Y.Z.zip` and `easm-X.Y.Z.zip.minisig` from the Release.
2. Upload both through the page (chunks under 4&nbsp;MiB so an 8&nbsp;M `upload_max_filesize` still works).
3. Paste the SHA-256 from `SHA256SUMS`.
4. Start the update as usual. The download step is skipped; verify still checks the signature.

**Minisign public key id:** `0263FE993EE2A87C` (file: `resources/keys/easm-release.minisign.pub`).

## If Laravel will not boot after an update

1. Open `/recover.php?token=…` using the recovery token shown on the update progress page (also stored in `storage/app/update/recovery.token` while an update is in progress).
2. Choose **Turn maintenance mode off**, **Finish / clear update session**, or **Roll back to previous version**.
3. Wrong or missing token → 403. A successful action deletes the token.
4. This page is plain PHP and does not load `vendor/`.

## Backup and restore

| Action | How |
| --- | --- |
| Create | Administration → Backups → Create backup now (pure-PHP dump + `.env`/`storage` zip; keeps the newest three) |
| Download | Administration → Backups (admins only; others get 403) |
| Automatic before update | The updater’s backup step |
| Restore after failure | Automatic on updater rollback, or via `/recover.php` rollback |

Migrations are not undone by themselves; restoring the pre-update database backup is what reverts schema and data.

## Per-version notes

### v0.9.0 — Install and update system

- Prefer the published `easm-0.9.0.zip` (includes `vendor/` and built assets). No Composer or Node on the host.
- First install: [INSTALL.md](INSTALL.md) then `/check.php` and `/install`.
- Updates: web updater above; manual upload when outbound HTTPS is blocked.
- Recovery: `/recover.php` with the update token if the app will not boot.
- Cloudron LAMP: [docs/hosting/cloudron-lamp.md](docs/hosting/cloudron-lamp.md) (#146).

### Earlier versions (before v0.9.0)

- Installs that used `composer install` on the host can move to the Release zip from v0.9.0 onwards.
- Always take a backup before switching update methods.
- See [docs/install.md](docs/install.md) for host-specific notes that pre-date the browser-only zip path.

## Related

- [INSTALL.md](INSTALL.md) — first install on cPanel / Plesk
- [docs/install.md](docs/install.md) — nginx, proxies, APES Cloudron deploy layout
- [docs/workflows.md](docs/workflows.md#release-package-signing) — how Release zips are signed
