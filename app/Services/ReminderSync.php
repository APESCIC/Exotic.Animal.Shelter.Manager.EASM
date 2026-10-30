<?php

namespace App\Services;

use App\Models\DiaryTask;
use App\Models\DwaLicence;
use App\Models\EnclosureEquipment;
use App\Support\UkDate;

/**
 * Raises diary tasks for things that have fallen due. Shared hosting has no cron, so this
 * runs when the dashboard or diary loads and from `php artisan easm:reminders`. Each reminder
 * has a unique key, so repeated runs never create a second task for the same event, and a
 * completed reminder is not raised again.
 */
class ReminderSync
{
    /**
     * @return int Number of diary tasks created.
     */
    public function sync(): int
    {
        return $this->equipmentReplacements() + $this->dwaRenewals();
    }

    private function dwaRenewals(): int
    {
        $created = 0;

        foreach (DwaLicence::query()->needsRenewal()->get() as $licence) {
            $task = DiaryTask::query()->firstOrCreate(
                ['reminder_key' => $licence->reminderKey()],
                [
                    'title' => "Renew DWA licence {$licence->licence_number}",
                    'body' => "Issued by {$licence->issuing_authority}; expires on ".UkDate::format($licence->expires_on)
                        .'. Update the expiry date on the licence once it is renewed.',
                    'due_on' => $licence->expires_on->copy()->subDays(DwaLicence::RENEWAL_REMINDER_DAYS),
                ],
            );

            if ($task->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function equipmentReplacements(): int
    {
        $created = 0;

        $due = EnclosureEquipment::query()
            ->due()
            ->with('enclosure')
            ->get();

        foreach ($due as $equipment) {
            $task = DiaryTask::query()->firstOrCreate(
                ['reminder_key' => $equipment->reminderKey()],
                [
                    'enclosure_id' => $equipment->enclosure_id,
                    'title' => "{$equipment->type->reminderVerb()} {$equipment->type->label()} in {$equipment->enclosure->name}",
                    'body' => "{$equipment->name} was due for replacement on ".UkDate::format($equipment->replace_due_on)
                        .'. Mark it replaced on the enclosure page to start the next interval.',
                    'due_on' => $equipment->replace_due_on,
                ],
            );

            if ($task->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
