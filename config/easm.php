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

];
