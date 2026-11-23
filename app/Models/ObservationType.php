<?php

namespace App\Models;

use App\Enums\ObservationTypeKind;
use Database\Factories\ObservationTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ObservationType extends Model
{
    /** @use HasFactory<ObservationTypeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'kind',
        'lookup_list_id',
        'sort_order',
        'active',
        'species_filter',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ObservationTypeKind::class,
            'sort_order' => 'integer',
            'active' => 'boolean',
            'species_filter' => 'array',
            'lookup_list_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LookupList, $this>
     */
    public function lookupList(): BelongsTo
    {
        return $this->belongsTo(LookupList::class);
    }

    /**
     * @return HasMany<AnimalObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(AnimalObservation::class);
    }

    /**
     * @param  Builder<ObservationType>  $query
     * @return Builder<ObservationType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<ObservationType>  $query
     * @return Builder<ObservationType>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function appliesToSpecies(?string $species): bool
    {
        $filter = $this->species_filter;
        if ($filter === null || $filter === []) {
            return true;
        }

        $species = trim((string) $species);
        if ($species === '') {
            return false;
        }

        foreach ($filter as $item) {
            if (strcasecmp(trim((string) $item), $species) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active types for a species, eager-loaded for forms.
     *
     * @return Collection<int, ObservationType>
     */
    public static function activeForSpecies(?string $species): Collection
    {
        return static::query()
            ->active()
            ->ordered()
            ->with('lookupList.activeValues')
            ->get()
            ->filter(fn (ObservationType $type): bool => $type->appliesToSpecies($species))
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function parseSpeciesFilterInput(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $species = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => $line !== ''));

        return $species === [] ? null : $species;
    }

    public function speciesFilterDisplay(): string
    {
        $filter = $this->species_filter;

        return is_array($filter) && $filter !== [] ? implode("\n", $filter) : '';
    }
}
