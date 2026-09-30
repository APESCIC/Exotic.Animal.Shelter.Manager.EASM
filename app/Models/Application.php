<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'status',
        'animal_id',
        'person_id',
        'name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'town_city',
        'county',
        'postcode',
        'message',
        'reviewed_by',
        'reviewed_at',
        'status_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationType::class,
            'status' => ApplicationStatus::class,
            'reviewed_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ApplicationStatus::Submitted->value,
            ApplicationStatus::UnderReview->value,
        ]);
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        $type = trim((string) $type);

        if ($type === '') {
            return $query;
        }

        return $query->where('type', $type);
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        $status = trim((string) $status);

        if ($status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }
}
