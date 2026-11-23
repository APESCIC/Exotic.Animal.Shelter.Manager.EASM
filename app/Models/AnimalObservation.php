<?php

namespace App\Models;

use App\Enums\ObservationTypeKind;
use App\Enums\UserRole;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\AnimalObservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalObservation extends Model
{
    /** @use HasFactory<AnimalObservationFactory> */
    use HasFactory;

    use RecordsAudit;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'observation_type_id',
        'lookup_value_id',
        'value_text',
        'observed_on',
        'body',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ObservationType, $this>
     */
    public function observationType(): BelongsTo
    {
        return $this->belongsTo(ObservationType::class);
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function lookupValue(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class);
    }

    public function displayValue(): string
    {
        if ($this->observation_type_id === null) {
            return trim((string) $this->body) !== '' ? (string) $this->body : '—';
        }

        $type = $this->observationType;
        if ($type === null) {
            return '—';
        }

        if ($type->kind === ObservationTypeKind::ValueList) {
            return $this->lookupValue?->name ?? '—';
        }

        $text = trim((string) $this->value_text);

        return $text !== '' ? $text : '—';
    }

    public function canBeModifiedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($this->user_id !== $user->id) {
            return false;
        }

        return $this->created_at !== null && $this->created_at->greaterThan(now()->subDay());
    }
}
