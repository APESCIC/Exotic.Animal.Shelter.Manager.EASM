# Install EASM (browser only)

How a shelter installs Exotic Animal Shelter Manager on ordinary shared hosting using only a browser (File Manager or FTP). No shell, SSH, Composer, cron or Node is required when you use the published Release zip.

Developers: see [AGENTS.md](AGENTS.md). Extra host detail (nginx, proxies, APES Cloudron ops): [docs/install.md](docs/install.md).

## What you need

| Item | Requirement |
| --- | --- |
| PHP | 8.3 or newer (MultiPHP / PHP selector in the panel) |
| Database | MySQL 8 or MariaDB 10.6+ (empty database + user) |
| Document root | Prefer `public/` inside the EASM folder |
| Extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |

One shelter per install. EASM is not multi-tenant.

## 1. Download the Release zip

1. Open the [latest GitHub Release](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/latest).
2. Download `easm-X.Y.Z.zip`, `easm-X.Y.Z.zip.minisig` and `SHA256SUMS`.
3. Optional but recommended on your PC: verify the zip with [minisign](https://jedisct1.github.io/minisign/) using the public key shipped in the package at `resources/keys/easm-release.minisign.pub`.

**Minisign public key id (fingerprint):** `0263FE993EE2A87C`

```bash
minisign -Vm easm-X.Y.Z.zip -p resources/keys/easm-release.minisign.pub
sha256sum -c SHA256SUMS
```

## 2. Upload with File Manager or FTP (cPanel / Plesk)

### cPanel

1. In **MySQL® Databases**, create an empty database and a user with all privileges on it. Note the full database name, username and password.
2. In **File Manager**, open `public_html` (or the subdomain folder).
3. Upload `easm-X.Y.Z.zip` and extract it. You should see folders such as `app`, `public`, `vendor` and `storage`.
4. In **Domains** / **Addon Domains** / **Subdomains**, set the document root to the extracted `public` folder (for example `public_html/easm/public`).
5. In **MultiPHP Manager**, select **PHP 8.3** (or newer) for that domain.
6. Ensure `storage` and `bootstrap/cache` are writable by the web server (often already true on shared hosts).

### Plesk

1. Create a database and user under **Databases**.
2. Upload and extract the zip under the domain’s httpdocs (or a subfolder) with **Files**.
3. Set the hosting document root to the extracted `public` directory (**Hosting Settings** → Document root).
4. Set PHP to 8.3+ under **PHP Settings**.
5. Confirm `storage` and `bootstrap/cache` are writable.

### Document root security note

Always point the site at `public/` when the panel allows it. That keeps `.env`, `storage/`, `vendor/` and application code outside the web root.

If the panel can only use the application root (the folder that contains `public/`), the root `index.php` and `.htaccess` fall back into `public/` and return **403** for sensitive paths. Prefer a real `public/` document root; the root fallback is a last resort, not a security substitute.

## 3. Run the browser wizard

1. Open the site in a browser. You are sent to `/install`.
2. First open `/check.php` if you want a plain-PHP host report (no Laravel). After install it locks.
3. Complete the wizard: requirements → database → organisation and first admin → migrations → finish.
4. Sign in at `/login` with the admin you created.

The wizard writes `.env` (including `APP_KEY` when missing), runs migrations in short steps (fine on a 30-second PHP limit), creates the admin and writes `storage/app/installed`.

## 4. After install

- Confirm `GET /health` returns JSON with `"status":"ok"` and the version.
- Store `APP_KEY` from `.env` in your password manager. Without it, encrypted data cannot be recovered from a backup.
- Updates: see [UPGRADING.md](UPGRADING.md).
- Backups: Administration → Backups (or follow [UPGRADING.md](UPGRADING.md#backup-and-restore)).

## Cloudron LAMP

For a Cloudron LAMP app (including the APES instance), follow [docs/hosting/cloudron-lamp.md](docs/hosting/cloudron-lamp.md) (issue #146). That guide covers PHP 8.3, DocumentRoot, health checks and the web updater on Cloudron.

## Related

- [docs/install.md](docs/install.md) — proxies, nginx, APES deploy layout
- [UPGRADING.md](UPGRADING.md) — browser updates, manual upload when HTTPS outbound is blocked, recovery
- [docs/features.md](docs/features.md) — what each role can do
