<?php

namespace App\Models;

use App\Enums\EquipmentType;
use Database\Factories\EnclosureEquipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnclosureEquipment extends Model
{
    /** @use HasFactory<EnclosureEquipmentFactory> */
    use HasFactory;

    protected $table = 'enclosure_equipment';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'enclosure_id',
        'type',
        'name',
        'installed_on',
        'replace_every_days',
        'notes',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EquipmentType::class,
            'installed_on' => 'date',
            'replace_every_days' => 'integer',
            'replace_due_on' => 'date',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EnclosureEquipment $equipment): void {
            $equipment->replace_due_on = $equipment->installed_on !== null && $equipment->replace_every_days !== null
                ? $equipment->installed_on->copy()->addDays($equipment->replace_every_days)
                : null;
        });
    }

    /**
     * @return BelongsTo<Enclosure, $this>
     */
    public function enclosure(): BelongsTo
    {
        return $this->belongsTo(Enclosure::class);
    }

    public function isDue(): bool
    {
        return $this->active
            && $this->replace_due_on !== null
            && $this->replace_due_on->lte(today());
    }

    /**
     * One key per due date, so marking the item replaced starts a fresh reminder cycle.
     */
    public function reminderKey(): ?string
    {
        return $this->replace_due_on === null
            ? null
            : self::reminderKeyPrefix($this->id).$this->replace_due_on->format('Y-m-d');
    }

    public static function reminderKeyPrefix(int $id): string
    {
        return 'equipment:'.$id.':';
    }

    /**
     * @param  Builder<EnclosureEquipment>  $query
     * @return Builder<EnclosureEquipment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<EnclosureEquipment>  $query
     * @return Builder<EnclosureEquipment>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereNotNull('replace_due_on')
            ->whereDate('replace_due_on', '<=', today());
    }
}
