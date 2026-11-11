<?php

namespace Tests\Feature;

use Tests\Concerns\SetsEnvironment;
use Tests\TestCase;

class CloudronConfigTest extends TestCase
{
    use SetsEnvironment;

    private const CLOUDRON = [
        'CLOUDRON_MYSQL_HOST' => 'mysql.cloudron',
        'CLOUDRON_MYSQL_PORT' => '3307',
        'CLOUDRON_MYSQL_DATABASE' => 'cloudron_db',
        'CLOUDRON_MYSQL_USERNAME' => 'cloudron_user',
        'CLOUDRON_MYSQL_PASSWORD' => 'cloudron_secret',
        'CLOUDRON_MAIL_SMTP_SERVER' => 'mail.cloudron',
        'CLOUDRON_MAIL_SMTP_PORT' => '2525',
        'CLOUDRON_MAIL_SMTP_USERNAME' => 'easm.app@apes.org.uk',
        'CLOUDRON_MAIL_SMTP_PASSWORD' => 'mail_secret',
        'CLOUDRON_MAIL_FROM' => 'easm.app@apes.org.uk',
    ];

    private const UNSET_APP_SETTINGS = [
        'DB_CONNECTION' => null,
        'DB_HOST' => null,
        'DB_PORT' => null,
        'DB_DATABASE' => null,
        'DB_USERNAME' => null,
        'DB_PASSWORD' => null,
        'MAIL_MAILER' => null,
        'MAIL_HOST' => null,
        'MAIL_PORT' => null,
        'MAIL_USERNAME' => null,
        'MAIL_PASSWORD' => null,
        'MAIL_FROM_ADDRESS' => null,
    ];

    public function test_database_config_falls_back_to_cloudron_variables(): void
    {
        $config = $this->withEnvironment(self::UNSET_APP_SETTINGS + self::CLOUDRON, fn (): array => require config_path('database.php'));

        $this->assertSame('mysql', $config['default']);
        $this->assertSame('mysql.cloudron', $config['connections']['mysql']['host']);
        $this->assertSame('3307', $config['connections']['mysql']['port']);
        $this->assertSame('cloudron_db', $config['connections']['mysql']['database']);
        $this->assertSame('cloudron_user', $config['connections']['mysql']['username']);
        $this->assertSame('cloudron_secret', $config['connections']['mysql']['password']);
    }

    public function test_database_settings_in_env_win_over_cloudron(): void
    {
        $own = ['DB_HOST' => 'db.example.org', 'DB_DATABASE' => 'own_db'] + self::UNSET_APP_SETTINGS + self::CLOUDRON;
        $config = $this->withEnvironment($own, fn (): array => require config_path('database.php'));

        $this->assertSame('db.example.org', $config['connections']['mysql']['host']);
        $this->assertSame('own_db', $config['connections']['mysql']['database']);
    }

    public function test_mail_config_falls_back_to_cloudron_variables(): void
    {
        $config = $this->withEnvironment(self::UNSET_APP_SETTINGS + self::CLOUDRON, fn (): array => require config_path('mail.php'));

        $this->assertSame('smtp', $config['default']);
        $this->assertSame('mail.cloudron', $config['mailers']['smtp']['host']);
        $this->assertSame('2525', $config['mailers']['smtp']['port']);
        $this->assertSame('easm.app@apes.org.uk', $config['mailers']['smtp']['username']);
        $this->assertSame('mail_secret', $config['mailers']['smtp']['password']);
        $this->assertSame('easm.app@apes.org.uk', $config['from']['address']);
    }

    public function test_without_cloudron_mail_stays_on_the_log_mailer(): void
    {
        $none = array_map(fn (): null => null, self::CLOUDRON) + self::UNSET_APP_SETTINGS;
        $config = $this->withEnvironment($none, fn (): array => require config_path('mail.php'));

        $this->assertSame('log', $config['default']);
        $this->assertSame('127.0.0.1', $config['mailers']['smtp']['host']);
    }
}
