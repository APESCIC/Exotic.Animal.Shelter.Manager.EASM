<?php

namespace App\Update;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copy a staged release over the live tree, keeping .env and storage/.
 */
class ReleaseSwapper
{
    /**
     * @param  list<string>  $preserve
     */
    public function __construct(
        private array $preserve = ['.env', 'storage', '.git'],
    ) {}

    public function swap(string $stagingRoot, string $liveRoot): void
    {
        if (! is_dir($stagingRoot)) {
            throw new UpdateException('Staging directory is missing.');
        }
        if (! is_dir($liveRoot)) {
            throw new UpdateException('Live application root is missing.');
        }

        $stagingRoot = rtrim(str_replace('\\', '/', $stagingRoot), '/');
        $liveRoot = rtrim(str_replace('\\', '/', $liveRoot), '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stagingRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $absolute = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($absolute, strlen($stagingRoot)), '/');
            if ($relative === '' || $this->shouldPreserve($relative)) {
                continue;
            }

            $target = $liveRoot.'/'.$relative;
            if ($item->isDir()) {
                if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
                    throw new UpdateException('Could not create '.$relative.' during swap.');
                }

                continue;
            }

            $directory = dirname($target);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new UpdateException('Could not create directory for '.$relative.' during swap.');
            }

            if (! copy($absolute, $target)) {
                throw new UpdateException('Could not copy '.$relative.' during swap.');
            }
        }
    }

    private function shouldPreserve(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);
        foreach ($this->preserve as $name) {
            if ($relative === $name || str_starts_with($relative, $name.'/')) {
                return true;
            }
        }

        return false;
    }
}
