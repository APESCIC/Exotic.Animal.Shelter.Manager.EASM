# Exotic Animal Shelter Manager (EASM)

EASM is free, self-hosted web software for running one exotic-animal shelter. It keeps animal records, care and medical history, CITES and Dangerous Wild Animals paperwork, people and adoptions, documents, finance and reports in one place. It runs on ordinary PHP and MySQL web hosting. It is maintained by [APES CIC](https://github.com/APESCIC).

## What it does

- **Animals and care:** records with free-text species, movements, medical care, diary tasks, stock and reminders.
- **Compliance:** a CITES register with Article 10 tracking, and Dangerous Wild Animals Act licences and inspections.
- **People and adoption:** contacts, a waiting list, public adoption pages, online applications and a partner feed.
- **Documents and finance:** signed PDF documents, donations, fees, sponsorship and a draft Gift Aid schedule.
- **Reports, mobile and rota:** CSV reports, a phone-friendly task screen that works offline, and a staff rota.

The full list is in [docs/features.md](docs/features.md).

## Quick start

| I want to… | Go to |
| --- | --- |
| Install EASM for a shelter (browser / File Manager) | [INSTALL.md](INSTALL.md): Release zip, then `/install` |
| Upgrade an installed shelter | [UPGRADING.md](UPGRADING.md) |
| Install on a Cloudron LAMP app | [docs/hosting/cloudron-lamp.md](docs/hosting/cloudron-lamp.md) |
| Host detail (nginx, proxies, APES deploy) | [docs/install.md](docs/install.md) |
| Run EASM behind Cloudflare or another HTTPS proxy | Set `EASM_TRUSTED_PROXIES` ([docs/install.md](docs/install.md#behind-an-https-proxy)) |
| Develop EASM on my PC | [Start here in AGENTS.md](AGENTS.md#start-here): Laravel Herd on Windows, five steps |
| Contribute a change | [CONTRIBUTING.md](CONTRIBUTING.md) |

## Document root and nginx

Point the site's document root at `public/`. Apache uses `public/.htaccess` for the front controller. nginx ignores `.htaccess`, so without `try_files` routes such as `/health` and `/install` return 404. See also [Laravel 13 nginx deployment](https://laravel.com/docs/13.x/deployment#nginx).

```nginx
root /var/www/easm/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock; # or 127.0.0.1:9000
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
    try_files $uri =404;
}
```

Adjust `root` and `fastcgi_pass` to the host. cPanel and Plesk Apache vhosts already honour `.htaccess`. Operator install steps are in [INSTALL.md](INSTALL.md); host detail is in [docs/install.md](docs/install.md).

`GET /health` returns JSON status and version. On MySQL/MariaDB it uses a 2-second connect timeout so a silent packet drop returns 503 instead of holding a PHP worker for the driver default (often about 60 seconds).

## Links

- [How a version ships](docs/shipping.md)
- [Roadmap and product constraints](docs/roadmap.md), with the plan in [issue #1](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/issues/1) and the [milestones](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/milestones?state=all)
- [Publishing adoptable animals to partner sites](docs/publishing.md)
- [Glossary of terms](docs/glossary.md)
- [Releases](https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases)
