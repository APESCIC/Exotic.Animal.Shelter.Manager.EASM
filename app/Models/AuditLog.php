<?php

namespace App\Models;

use App\Enums\AnimalTab;
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
     * Fields that must never appear in a one-line summary (UK GDPR caution).
     *
     * @var list<string>
     */
    private const SUMMARY_HIDDEN = [
        'email',
        'phone',
        'mobile',
        'address',
        'address_line_1',
        'address_line_2',
        'postcode',
        'postal_code',
        'date_of_birth',
        'dob',
        'notes',
        'body',
        'reason',
    ];

    /**
     * Entries for an animal record: the animal itself, medical records, observations and movements.
     * Child rows keep matching after delete via animal_id stored in old/new values.
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
                    self::whereRelatedToAnimal($inner, (new MedicalRecord)->getMorphClass(), MedicalRecord::class, $animal->id);
                })->orWhere(function (Builder $inner) use ($animal): void {
                    self::whereRelatedToAnimal($inner, (new AnimalObservation)->getMorphClass(), AnimalObservation::class, $animal->id);
                })->orWhere(function (Builder $inner) use ($animal): void {
                    self::whereRelatedToAnimal($inner, (new Movement)->getMorphClass(), Movement::class, $animal->id);
                });
            })
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<static>  $query
     * @param  class-string<Model>  $relatedClass
     */
    private static function whereRelatedToAnimal(Builder $query, string $morphClass, string $relatedClass, int $animalId): void
    {
        $query->where('auditable_type', $morphClass)
            ->where(function (Builder $inner) use ($relatedClass, $animalId): void {
                $inner->whereIn('auditable_id', $relatedClass::query()->where('animal_id', $animalId)->select('id'))
                    ->orWhere('old_values->animal_id', $animalId)
                    ->orWhere('new_values->animal_id', $animalId)
                    ->orWhere('old_values->animal_id', (string) $animalId)
                    ->orWhere('new_values->animal_id', (string) $animalId);
            });
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
            AnimalObservation::class, (new AnimalObservation)->getMorphClass() => 'Observation',
            Movement::class, (new Movement)->getMorphClass() => 'Movement',
            LookupValue::class, (new LookupValue)->getMorphClass() => 'Lookup value',
            default => class_basename($this->auditable_type),
        };
    }

    /**
     * One-line description for feeds. Omits personal data fields.
     */
    public function summary(): string
    {
        if ($this->action === 'created') {
            return $this->subjectLabel().' created';
        }

        if ($this->action === 'deleted') {
            return $this->subjectLabel().' deleted';
        }

        $values = is_array($this->new_values) ? $this->new_values : [];
        $fields = array_values(array_filter(
            array_keys($values),
            fn (string $field): bool => ! in_array($field, self::SUMMARY_HIDDEN, true),
        ));

        if ($fields === []) {
            return $this->subjectLabel().' updated';
        }

        $shown = array_slice($fields, 0, 3);
        $label = $this->subjectLabel().' updated ('.implode(', ', $shown);
        if (count($fields) > 3) {
            $label .= ', …';
        }

        return $label.')';
    }

    /**
     * Link to the auditable record, or its parent animal tab when the child was deleted.
     */
    public function recordUrl(): ?string
    {
        $model = $this->auditable;

        if ($model instanceof Animal) {
            return route('animals.show', $model);
        }

        if ($model instanceof Person) {
            return route('people.show', $model);
        }

        if ($model instanceof MedicalRecord) {
            return AnimalTab::Health->url($model->animal_id);
        }

        if ($model instanceof AnimalObservation) {
            return AnimalTab::Log->url($model->animal_id);
        }

        if ($model instanceof Movement) {
            return AnimalTab::Movements->url($model->animal_id);
        }

        $animalId = $this->old_values['animal_id'] ?? $this->new_values['animal_id'] ?? null;
        if ($animalId === null) {
            return null;
        }

        $animalId = (int) $animalId;

        return match ($this->auditable_type) {
            MedicalRecord::class, (new MedicalRecord)->getMorphClass() => AnimalTab::Health->url($animalId),
            AnimalObservation::class, (new AnimalObservation)->getMorphClass() => AnimalTab::Log->url($animalId),
            Movement::class, (new Movement)->getMorphClass() => AnimalTab::Movements->url($animalId),
            Animal::class, (new Animal)->getMorphClass() => route('animals.show', $animalId),
            default => null,
        };
    }

    /**
     * @return array<string, string> morph class => label
     */
    public static function subjectTypeOptions(): array
    {
        return [
            (new Animal)->getMorphClass() => 'Animal',
            (new Person)->getMorphClass() => 'Person',
            (new MedicalRecord)->getMorphClass() => 'Medical record',
            (new AnimalObservation)->getMorphClass() => 'Observation',
            (new Movement)->getMorphClass() => 'Movement',
            (new LookupValue)->getMorphClass() => 'Lookup value',
        ];
    }
}
