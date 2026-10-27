<?php

namespace App\Models;

use App\Enums\SponsorshipFrequency;
use Database\Factories\SponsorshipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sponsorship extends Model
{
    /** @use HasFactory<SponsorshipFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'animal_id',
        'starts_on',
        'ends_on',
        'amount_pence',
        'frequency',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount_pence' => 'integer',
            'frequency' => SponsorshipFrequency::class,
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function isActive(): bool
    {
        return $this->ends_on === null || $this->ends_on->isFuture() || $this->ends_on->isToday();
    }

    /**
     * @param  Builder<Sponsorship>  $query
     * @return Builder<Sponsorship>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }
}
