<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation lock
    |--------------------------------------------------------------------------
    |
    | The web installer writes APP_INSTALLED=true into .env and a lock file
    | under storage/app. Either marker means this shelter is already installed
    | and the wizard must not run again. One shelter per install.
    |
    */

    'installed' => filter_var(env('APP_INSTALLED', false), FILTER_VALIDATE_BOOLEAN),

    'env_path' => env('EASM_ENV_PATH', base_path('.env')),

    'installed_path' => env('EASM_INSTALLED_PATH', storage_path('app/installed')),

    /*
    |--------------------------------------------------------------------------
    | Platform-managed database
    |--------------------------------------------------------------------------
    |
    | When the hosting platform writes DB_* into .env itself (some managed
    | hosts do this on every start), the wizard skips the database step and
    | never overwrites those keys.
    |
    */

    'managed_database' => filter_var(env('EASM_MANAGED_DATABASE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Cloudron database
    |--------------------------------------------------------------------------
    |
    | A Cloudron app gets its MySQL database as CLOUDRON_MYSQL_* variables.
    | When they exist, the wizard offers that database or your own. Cloudron
    | can change them on a restore or clone, so they are never copied to .env.
    |
    */

    'cloudron_database' => [
        'host' => env('CLOUDRON_MYSQL_HOST'),
        'port' => env('CLOUDRON_MYSQL_PORT', '3306'),
        'database' => env('CLOUDRON_MYSQL_DATABASE'),
        'username' => env('CLOUDRON_MYSQL_USERNAME'),
        'password' => env('CLOUDRON_MYSQL_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development auto-login
    |--------------------------------------------------------------------------
    |
    | Signs guests in as a QA account so local testing needs no password, and
    | enables the role switcher. Only honoured when APP_ENV=local; ignored in
    | every other environment even when set.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | DWA licence renewal reminder
    |--------------------------------------------------------------------------
    |
    | Days before a Dangerous Wild Animals licence expires that EASM raises the
    | renewal diary task. Admins change it under Settings; this is the default
    | until they do.
    |
    */

    'dwa_reminder_days' => 60,

    /*
    |--------------------------------------------------------------------------
    | Updater backups
    |--------------------------------------------------------------------------
    |
    | Pure-PHP dumps for the web updater live under storage/app/backups (never
    | under public/). Only the newest few are kept.
    |
    */

    'backups' => [
        'path' => env('EASM_BACKUPS_PATH', storage_path('app/backups')),
        'keep' => (int) env('EASM_BACKUPS_KEEP', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Update check (poor-man's scheduler)
    |--------------------------------------------------------------------------
    |
    | Admins trigger at most one GitHub Releases check per day on page load.
    | Optional EASM_UPDATE_CRON_TOKEN enables GET /cron/update-check?token=…
    | for hosts that can hit a URL on a schedule. Failures stay quiet.
    |
    */

    'updates' => [
        'enabled' => filter_var(env('EASM_UPDATE_CHECK', true), FILTER_VALIDATE_BOOLEAN),
        'repository' => env('EASM_UPDATE_REPO', 'APESCIC/Exotic.Animal.Shelter.Manager.EASM'),
        'channel' => env('EASM_UPDATE_CHANNEL', 'stable'),
        'check_interval_hours' => (int) env('EASM_UPDATE_CHECK_HOURS', 24),
        'cron_token' => env('EASM_UPDATE_CRON_TOKEN'),
        'state_path' => env('EASM_UPDATE_STATE_PATH', storage_path('app/update')),
        'lock_stale_seconds' => (int) env('EASM_UPDATE_LOCK_STALE', 120),
    ],

    'dev_login' => [
        'auto_login' => filter_var(env('DEV_AUTO_LOGIN', false), FILTER_VALIDATE_BOOLEAN),
        'role' => env('DEV_AUTO_LOGIN_ROLE', 'admin'),
    ],

];
