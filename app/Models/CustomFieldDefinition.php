<?php

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Support\UkDate;
use Database\Factories\CustomFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function normalizeStoredValue(mixed $rawValue): ?string
    {
        if ($this->type === CustomFieldType::Boolean) {
            if ($rawValue === true || $rawValue === 1 || $rawValue === '1' || $rawValue === 'on') {
                return '1';
            }

            return '0';
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
            default => $stored,
        };
    }
}
