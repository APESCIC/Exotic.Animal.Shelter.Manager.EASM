<?php

namespace App\Console\Commands;

use App\Support\SharedHosting;
use Illuminate\Console\Command;

class EnsurePublicStorageCommand extends Command
{
    protected $signature = 'easm:ensure-public-storage';

    protected $description = 'Create public/storage (symlink when possible) or rely on the /storage route fallback';

    public function handle(): int
    {
        SharedHosting::ensureAppTempDirectory();
        $result = SharedHosting::ensurePublicStorage();

        if ($result['linked']) {
            $this->info($result['message'].' ('.$result['method'].')');

            return self::SUCCESS;
        }

        $this->warn($result['message']);

        return self::SUCCESS;
    }
}
