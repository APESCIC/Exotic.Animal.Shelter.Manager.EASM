<?php

namespace App\Console\Commands;

use App\Services\ReminderSync;
use Illuminate\Console\Command;

class SyncReminders extends Command
{
    protected $signature = 'easm:reminders';

    protected $description = 'Create diary tasks for equipment replacements and other reminders that have fallen due';

    public function handle(ReminderSync $reminders): int
    {
        $created = $reminders->sync();

        $this->info($created === 1 ? '1 reminder task created.' : "{$created} reminder tasks created.");

        return self::SUCCESS;
    }
}
