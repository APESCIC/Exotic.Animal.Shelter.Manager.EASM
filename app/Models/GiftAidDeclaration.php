<?php

namespace App\Models;

use App\Enums\GiftAidDeclarationMethod;
use Carbon\CarbonInterface;
use Database\Factories\GiftAidDeclarationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftAidDeclaration extends Model
{
    /** @use HasFactory<GiftAidDeclarationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'declared_on',
        'effective_from',
        'cancelled_on',
        'method',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'declared_on' => 'date',
            'effective_from' => 'date',
            'cancelled_on' => 'date',
            'method' => GiftAidDeclarationMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isActive(): bool
    {
        return $this->cancelled_on === null;
    }

    public function covers(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $day >= $this->effective_from->toDateString()
            && ($this->cancelled_on === null || $day < $this->cancelled_on->toDateString());
    }
}
