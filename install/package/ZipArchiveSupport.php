<?php

declare(strict_types=1);

namespace Easm\Package;

use Throwable;
use ZipArchive;

/**
 * Detect whether ZipArchive can be constructed (ext-zip loaded and not disable_classes).
 */
final class ZipArchiveSupport
{
    public static function isUsable(): bool
    {
        if (! extension_loaded('zip') || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $disabled = strtolower((string) ini_get('disable_classes'));
        foreach (array_map('trim', explode(',', $disabled)) as $class) {
            if ($class !== '' && strcasecmp($class, 'ZipArchive') === 0) {
                return false;
            }
        }

        try {
            // Instantiation fails when disable_classes lists ZipArchive.
            new ZipArchive;

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
