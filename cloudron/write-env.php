<?php

/**
 * Sync /app/data/env with the Cloudron addon variables on every start.
 * Keys the install wizard owns (APP_NAME, APP_TIMEZONE, APP_INSTALLED) are left alone.
 */

use App\Install\EnvFile;

require '/app/code/vendor/autoload.php';

$path = '/app/data/env';

if (! is_file($path)) {
    copy('/app/code/.env.example', $path);
}

$env = static fn (string $key, string $default = ''): string => (string) (getenv($key) ?: $default);

$values = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => $env('CLOUDRON_APP_ORIGIN', 'http://localhost'),
    'APP_VERSION' => $env('EASM_VERSION', 'dev'),
    'EASM_MANAGED_DATABASE' => 'true',
    'LOG_CHANNEL' => 'stderr',
    'LOG_LEVEL' => 'info',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => $env('CLOUDRON_MYSQL_HOST'),
    'DB_PORT' => $env('CLOUDRON_MYSQL_PORT', '3306'),
    'DB_DATABASE' => $env('CLOUDRON_MYSQL_DATABASE'),
    'DB_USERNAME' => $env('CLOUDRON_MYSQL_USERNAME'),
    'DB_PASSWORD' => $env('CLOUDRON_MYSQL_PASSWORD'),
];

if ($env('CLOUDRON_MAIL_SMTP_SERVER') !== '') {
    $values += [
        'MAIL_MAILER' => 'smtp',
        'MAIL_HOST' => $env('CLOUDRON_MAIL_SMTP_SERVER'),
        'MAIL_PORT' => $env('CLOUDRON_MAIL_SMTP_PORT', '2525'),
        'MAIL_USERNAME' => $env('CLOUDRON_MAIL_SMTP_USERNAME'),
        'MAIL_PASSWORD' => $env('CLOUDRON_MAIL_SMTP_PASSWORD'),
        'MAIL_FROM_ADDRESS' => $env('CLOUDRON_MAIL_FROM'),
    ];
}

if (preg_match('/^APP_KEY=\S+/m', (string) file_get_contents($path)) !== 1) {
    $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
}

(new EnvFile)->write($path, $values);
