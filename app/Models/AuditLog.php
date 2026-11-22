<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Entries for an animal record: the animal itself and its medical records.
     *
     * @return Builder<static>
     */
    public static function forAnimalRecord(Animal $animal): Builder
    {
        return static::query()
            ->where(function (Builder $query) use ($animal): void {
                $query->where(function (Builder $inner) use ($animal): void {
                    $inner->where('auditable_type', $animal->getMorphClass())
                        ->where('auditable_id', $animal->getKey());
                })->orWhere(function (Builder $inner) use ($animal): void {
                    $inner->where('auditable_type', (new MedicalRecord)->getMorphClass())
                        ->whereIn('auditable_id', MedicalRecord::query()->where('animal_id', $animal->id)->select('id'));
                });
            })
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(Model $model, string $action, ?array $old, ?array $new): void
    {
        static::query()->create([
            'user_id' => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }

    public function subjectLabel(): string
    {
        return match ($this->auditable_type) {
            Animal::class, (new Animal)->getMorphClass() => 'Animal',
            Person::class, (new Person)->getMorphClass() => 'Person',
            MedicalRecord::class, (new MedicalRecord)->getMorphClass() => 'Medical record',
            default => class_basename($this->auditable_type),
        };
    }
}
