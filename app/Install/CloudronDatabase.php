<?php

namespace App\Install;

class CloudronDatabase
{
    /**
     * Whether this app runs on Cloudron with its MySQL addon.
     */
    public function available(): bool
    {
        return filled(config('easm.cloudron_database.host'));
    }

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    public function credentials(): array
    {
        $database = (array) config('easm.cloudron_database');

        return [
            'host' => (string) ($database['host'] ?? ''),
            'port' => (string) ($database['port'] ?? '3306'),
            'database' => (string) ($database['database'] ?? ''),
            'username' => (string) ($database['username'] ?? ''),
            'password' => (string) ($database['password'] ?? ''),
        ];
    }
}
