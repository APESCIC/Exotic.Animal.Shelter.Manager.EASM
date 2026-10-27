<?php

namespace App\Models;

use App\Enums\MedicalRecordType;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    /** @use HasFactory<MedicalRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'type',
        'name',
        'due_on',
        'given_on',
        'expires_on',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MedicalRecordType::class,
            'due_on' => 'date',
            'given_on' => 'date',
            'expires_on' => 'date',
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
     * @param  Builder<MedicalRecord>  $query
     * @return Builder<MedicalRecord>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNull('given_on')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', today());
    }
}
