<?php

namespace App\Models;

use Database\Factories\EnclosureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enclosure extends Model
{
    /** @use HasFactory<EnclosureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'zone',
        'size',
        'capacity',
        'notes',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Every animal record pointing at this enclosure, including past residents.
     *
     * @return HasMany<Animal, $this>
     */
    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    /**
     * Animals currently in care that live here.
     *
     * @return HasMany<Animal, $this>
     */
    public function occupants(): HasMany
    {
        return $this->animals()->inCare()->orderBy('name');
    }

    /**
     * @return HasMany<EnclosureLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EnclosureLog::class)->orderByDesc('recorded_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<EnclosureEquipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(EnclosureEquipment::class)
            ->orderByDesc('active')
            ->orderByRaw('replace_due_on is null')
            ->orderBy('replace_due_on')
            ->orderBy('name');
    }

    /**
     * @return HasMany<EnclosureEquipment, $this>
     */
    public function dueEquipment(): HasMany
    {
        return $this->hasMany(EnclosureEquipment::class)->due();
    }

    public function occupantCount(): int
    {
        return (int) ($this->occupants_count ?? $this->occupants()->count());
    }

    public function isOverCapacity(): bool
    {
        return $this->capacity !== null && $this->occupantCount() > $this->capacity;
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->occupantCount() >= $this->capacity;
    }

    public function occupancyLabel(): string
    {
        return $this->capacity === null
            ? $this->occupantCount().' (no limit set)'
            : $this->occupantCount().' / '.$this->capacity;
    }

    public function label(): string
    {
        return $this->zone ? $this->zone.' · '.$this->name : $this->name;
    }

    /**
     * @param  Builder<Enclosure>  $query
     * @return Builder<Enclosure>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<Enclosure>  $query
     * @return Builder<Enclosure>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('zone is null')->orderBy('zone')->orderBy('name');
    }
}
