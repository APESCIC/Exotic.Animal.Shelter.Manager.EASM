# Install and update EASM on a Cloudron LAMP app

Operator guide for installing and updating EASM on a [Cloudron](https://www.cloudron.io/) LAMP app using the published Release zip and the browser installer/updater. No SSH shell, Composer or Node is required on the app once the files are in place.

Linked from [INSTALL.md](../../INSTALL.md) and the [README](../../README.md). Browser-only shared hosting without Cloudron: [INSTALL.md](../../INSTALL.md). Upgrades in general: [UPGRADING.md](../../UPGRADING.md). APES deploy automation (tag → Cloudron): [docs/install.md](../install.md#the-apes-cloudron-instance) and [docs/shipping.md](../shipping.md).

> **Live evidence (Bambie):** Acceptance checks that need a fresh Cloudron LAMP app and a v0.9.0 → v0.9.1 web update on the APES instance are listed under [Verification on the APES instance](#verification-on-the-apes-instance). This document ships the steps; those ticks stay open until Bambie records the evidence.

## What you need

- A Cloudron server and permission to install the **LAMP** app from the Cloudron App Store.
- The published Release assets for the version you want: `easm-X.Y.Z.zip`, `easm-X.Y.Z.zip.minisig`, and `SHA256SUMS` (from the GitHub Release).
- Optional: SFTP enabled briefly for the first upload (File Manager alone is enough for smaller zips).

## 1. Install the LAMP app

1. In the Cloudron dashboard, install **LAMP**.
2. Give the app enough memory for PHP and MySQL (512&nbsp;MiB is tight; 1&nbsp;GiB is comfortable for a small shelter).
3. Open the app’s File Manager (or enable SFTP for the upload, then turn it off again later).

## 2. Pin PHP 8.3

EASM needs PHP 8.3.

1. In File Manager, open `/app/data/`.
2. Create or edit the file `PHP_VERSION` so it contains exactly:

   ```text
   PHP_VERSION=8.3
   ```

3. Restart the app from the Cloudron dashboard so Apache picks up PHP 8.3.

## 3. Upload and extract the Release zip

1. Download `easm-X.Y.Z.zip` (and preferably the `.minisig` / `SHA256SUMS`) from the GitHub Release.
2. Upload the zip into `/app/data/` (File Manager or SFTP).
3. Extract it so the application root is **`/app/data/easm`** (the folder that contains `artisan`, `public/`, `VERSION`, and so on).
   - If the zip expands to `/app/data/easm-X.Y.Z/`, rename or move that folder to `/app/data/easm`.
4. Ensure these paths exist and are writable by the app user (the zip normally includes them):

   - `/app/data/easm/storage`
   - `/app/data/easm/bootstrap/cache`

5. You can delete the uploaded zip from `/app/data/` after a successful extract.

## 4. Point Apache at `public/`

Edit `/app/data/apache/app.conf` (Cloudron loads this for the LAMP site).

Minimum that works for EASM (same shape as the APES app):

```apache
ServerName localhost

<VirtualHost *:80>
    ServerName localhost
    UseCanonicalName Off
    DocumentRoot /app/data/easm/public

    LogFormat "%{X-Forwarded-For}i %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" proxy
    CustomLog "|/bin/cat" proxy
    ErrorLog "|/bin/cat"

    <Directory /app/data/easm/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Keep this: Cloudron’s real client IP, host and HTTPS for Laravel URLs.
    Include "/app/code/apache/rpaf.conf"

    # Optional while debugging: phpMyAdmin. Remove the next line to turn it off.
    # Include "/app/code/apache/phpmyadmin.conf"

    # If Cloudron’s health probe requests /CloudronHealth, send it to EASM’s JSON health.
    RewriteEngine On
    RewriteRule ^CloudronHealth$ /health [L]
</VirtualHost>
```

Notes:

- **DocumentRoot** must be `/app/data/easm/public`, not `/app/data/easm`.
- Keep the **`rpaf.conf`** include. Cloudron terminates HTTPS in front of Apache; without it Laravel may build `http://` links.
- Set the LAMP app’s health check URL (Cloudron UI) to **`/health`** when the UI allows a path. The `CloudronHealth` rewrite covers the default probe name when that is what the platform requests.
- Restart the app after saving `app.conf`.

## 5. Environment (`.env`)

Create `/app/data/easm/.env` from the package’s `.env.example` (or let the `/install` wizard create it). On Cloudron:

| Topic | What to do |
| --- | --- |
| Database | Prefer the wizard choice **Use the database Cloudron provides**. That removes `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` from `.env` so EASM reads `${CLOUDRON_MYSQL_*}` on every request. |
| Managed DB flag | If you write no `DB_*` yourself and skip the wizard DB step, set `EASM_MANAGED_DATABASE=true`. |
| Mail | Leave `MAIL_*` unset when using Cloudron mail; EASM can use `${CLOUDRON_MAIL_*}` the same way as MySQL. Or configure SMTP in the wizard if you use another provider. |
| Trusted proxies | Leave `EASM_TRUSTED_PROXIES` empty on a normal Cloudron LAMP app — `rpaf.conf` already tells PHP the request was HTTPS. See [Behind an HTTPS proxy](../install.md#behind-an-https-proxy). |
| `APP_URL` | Set to the public `https://` URL of the LAMP app. |
| `APP_KEY` | Generated by the wizard (or on first deploy for APES). **Copy it into your password manager** — without it, encrypted data cannot be recovered from a backup. |

### Do not run `config:cache` on Cloudron

Cloudron can rotate the MySQL password (restore, clone, addon changes). If Laravel’s config is cached, the old password stays baked in and the app breaks until you clear the cache. Leave config uncached; routes/events/views caching per release is fine when you use the APES deploy scripts, but a zip install should not run `php artisan config:cache`.

## 6. Run the install wizard

1. Open `https://<your-lamp-domain>/install` in a browser.
2. Complete the steps (requirements, database choice, shelter details, admin account).
3. Choose **Use the database Cloudron provides** when offered.
4. When the wizard finishes, sign in at `/login`.
5. Confirm `GET /health` returns JSON with `"status":"ok"` and the expected `version`.
6. Confirm the Cloudron dashboard shows the app as **Running**.

## 7. Updates (v0.9.0 web updater)

After install, use **Administration → Update** (same flow as [UPGRADING.md](../../UPGRADING.md)):

1. Cloudron backup first (dashboard → Backup), in addition to EASM’s own pre-update backup.
2. When the app can reach GitHub Releases, start the update from the admin UI.
3. When outbound HTTPS is blocked, upload the new `easm-X.Y.Z.zip` and `.minisig` with the manual upload fallback (chunked; each chunk under 4&nbsp;MiB if `upload_max_filesize` is 8&nbsp;M).
4. Wait until `/health` reports the new version and `"status":"ok"`.
5. If a step fails, the updater rolls back automatically; restore a Cloudron backup only if you still need an older database state.

## 8. Backups

- **Cloudron:** use the dashboard Backup for the whole LAMP app (`/app/data` including `.env` and `storage`).
- **EASM:** Administration → Backups keeps the newest three pure-PHP dumps under `storage/app/backups` (never under `public/`).
- Always keep `APP_KEY` outside the server (password manager).

## 9. Turn off phpMyAdmin and SFTP when finished

After install (and any SFTP upload):

1. Remove or comment the `phpmyadmin.conf` include in `/app/data/apache/app.conf`, then restart the app.
2. Disable SFTP for the app in the Cloudron UI if you enabled it only for the zip upload.

Leaving either open is a common accidental exposure on shared admin URLs.

## APES instance notes

APES runs <https://easm.intranet.apes.org.uk> on Cloudron LAMP. Day-to-day releases there often use `deploy-cloudron.yml` (release tag → `/app/data/releases/…` → `easm` link) rather than a manual zip extract. The zip path in this guide is what a shelter (or a first install) should follow. Layout and secrets for the automated deploy: [The APES Cloudron instance](../install.md#the-apes-cloudron-instance).

## Verification on the APES instance

Record evidence on issue #146 (or the Epic) when done. Until then these stay unchecked:

- [ ] Following **only** this guide, a new Cloudron LAMP app reaches the EASM login from the v0.9.0 Release zip, with `/health` ok and Cloudron showing the app as Running. *(Bambie — live host)*
- [ ] A v0.9.0 → v0.9.1 web update on that app succeeds by following [Updates](#7-updates-v090-web-updater). *(Bambie — live host)*
- [ ] Steps that differ on APES’s automated deploy vs this zip path are noted in the evidence comment. *(Bambie)*

Steps derived from the APES `deploy/cloudron/` layout and existing install docs (PHP 8.3 pin, DocumentRoot, `rpaf.conf`, Cloudron MySQL variables, no `config:cache`) are treated as already proven on that instance; re-confirm when ticking the boxes above.
