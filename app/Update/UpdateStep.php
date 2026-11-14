<?php

namespace App\Update;

/**
 * Ordered steps for the resumable web updater (#84).
 */
enum UpdateStep: string
{
    case Preflight = 'preflight';
    case Backup = 'backup';
    case MaintenanceOn = 'maintenance_on';
    case Download = 'download';
    case Verify = 'verify';
    case Extract = 'extract';
    case Swap = 'swap';
    case Migrate = 'migrate';
    case ClearCaches = 'clear_caches';
    case Health = 'health';
    case MaintenanceOff = 'maintenance_off';

    /**
     * @return list<self>
     */
    public static function sequence(): array
    {
        return [
            self::Preflight,
            self::Backup,
            self::MaintenanceOn,
            self::Download,
            self::Verify,
            self::Extract,
            self::Swap,
            self::Migrate,
            self::ClearCaches,
            self::Health,
            self::MaintenanceOff,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Preflight => 'Preflight',
            self::Backup => 'Backup',
            self::MaintenanceOn => 'Maintenance on',
            self::Download => 'Download or upload',
            self::Verify => 'Verify package',
            self::Extract => 'Extract to staging',
            self::Swap => 'Swap release',
            self::Migrate => 'Migrate database',
            self::ClearCaches => 'Clear caches',
            self::Health => 'Health check',
            self::MaintenanceOff => 'Maintenance off',
        };
    }

    public function next(): ?self
    {
        $sequence = self::sequence();
        $index = array_search($this, $sequence, true);
        if ($index === false || $index >= count($sequence) - 1) {
            return null;
        }

        return $sequence[$index + 1];
    }
}
