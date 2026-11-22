<?php

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Support\UkDate;
use Database\Factories\CustomFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomFieldDefinition extends Model
{
    /** @use HasFactory<CustomFieldDefinitionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity',
        'label',
        'slug',
        'type',
        'sort_order',
        'active',
        'is_mandatory',
        'tooltip',
        'show_on_new_record',
        'species_filter',
        'lookup_list_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity' => CustomFieldEntity::class,
            'type' => CustomFieldType::class,
            'sort_order' => 'integer',
            'active' => 'boolean',
            'is_mandatory' => 'boolean',
            'show_on_new_record' => 'boolean',
            'lookup_list_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }

    /**
     * @return BelongsTo<LookupList, $this>
     */
    public function lookupList(): BelongsTo
    {
        return $this->belongsTo(LookupList::class);
    }

    /**
     * @param  Builder<CustomFieldDefinition>  $query
     * @return Builder<CustomFieldDefinition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<CustomFieldDefinition>  $query
     * @return Builder<CustomFieldDefinition>
     */
    public function scopeForAnimal(Builder $query): Builder
    {
        return $query->where('entity', CustomFieldEntity::Animal->value);
    }

    /**
     * @param  Builder<CustomFieldDefinition>  $query
     * @return Builder<CustomFieldDefinition>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    public static function makeSlug(string $label): string
    {
        $slug = Str::slug($label);

        return $slug !== '' ? $slug : 'field';
    }

    public function appliesToSpecies(?string $species): bool
    {
        $filter = trim((string) $this->species_filter);
        if ($filter === '') {
            return true;
        }

        return strcasecmp(trim((string) $species), $filter) === 0;
    }

    public function normalizeStoredValue(mixed $rawValue): ?string
    {
        if ($this->type === CustomFieldType::Boolean) {
            if ($rawValue === true || $rawValue === 1 || $rawValue === '1' || $rawValue === 'on') {
                return '1';
            }

            return '0';
        }

        if ($this->type === CustomFieldType::MultiLookup) {
            if (! is_array($rawValue)) {
                $rawValue = $rawValue === null || $rawValue === '' ? [] : [$rawValue];
            }

            $names = array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                $rawValue,
            ), static fn (string $item): bool => $item !== ''));

            return $names === [] ? null : json_encode($names, JSON_UNESCAPED_UNICODE);
        }

        if ($rawValue === null) {
            return null;
        }

        $value = trim((string) $rawValue);

        return $value === '' ? null : $value;
    }

    public function displayValue(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '—';
        }

        return match ($this->type) {
            CustomFieldType::Boolean => $stored === '1' ? 'Yes' : 'No',
            CustomFieldType::Date => UkDate::format($stored) ?: $stored,
            CustomFieldType::MultiLookup => $this->decodeMultiLookup($stored),
            default => $stored,
        };
    }

    /**
     * @return list<string>
     */
    public function decodedMultiLookup(?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded)))
            : [$stored];
    }

    private function decodeMultiLookup(string $stored): string
    {
        $names = $this->decodedMultiLookup($stored);

        return $names === [] ? '—' : implode(', ', $names);
    }
}
