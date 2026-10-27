<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every stock change goes through here so the item quantity always equals its movement history.
 * Quantities are decimal strings handled with bcmath to avoid float drift.
 */
class StockLedger
{
    public function record(
        StockItem $item,
        StockMovementReason $reason,
        string $quantity,
        ?User $user = null,
        ?Animal $animal = null,
        ?MedicalRecord $medicalRecord = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($item, $reason, $quantity, $user, $animal, $medicalRecord, $notes): StockMovement {
            $locked = StockItem::query()->lockForUpdate()->findOrFail($item->id);
            $current = (string) $locked->quantity;

            $change = match ($reason) {
                StockMovementReason::Received => bcadd('0', $quantity, 2),
                StockMovementReason::Used, StockMovementReason::Wasted => bcsub('0', $quantity, 2),
                StockMovementReason::Counted => bcsub($quantity, $current, 2),
            };
            $after = bcadd($current, $change, 2);

            $locked->update(['quantity' => $after]);
            $item->setRawAttributes($locked->getAttributes(), true);

            return $locked->movements()->create([
                'reason' => $reason,
                'change' => $change,
                'quantity_after' => $after,
                'animal_id' => $animal?->id,
                'medical_record_id' => $medicalRecord?->id,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);
        });
    }
}
