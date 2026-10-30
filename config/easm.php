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
    | When the hosting platform writes DB_* itself (the Cloudron package does
    | this on every start), the wizard skips the database step and never
    | overwrites those keys.
    |
    */

    'managed_database' => filter_var(env('EASM_MANAGED_DATABASE', false), FILTER_VALIDATE_BOOLEAN),

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

    'dev_login' => [
        'auto_login' => filter_var(env('DEV_AUTO_LOGIN', false), FILTER_VALIDATE_BOOLEAN),
        'role' => env('DEV_AUTO_LOGIN_ROLE', 'admin'),
    ],

];
