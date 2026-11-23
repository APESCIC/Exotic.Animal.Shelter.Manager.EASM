<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movement extends Model
{
    /** @use HasFactory<MovementFactory> */
    use HasFactory;

    use RecordsAudit;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'type',
        'moved_at',
        'person_id',
        'reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'moved_at' => 'date',
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
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
