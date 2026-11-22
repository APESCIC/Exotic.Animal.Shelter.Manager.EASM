<?php

namespace App\Models;

use App\Enums\AnimalControlCaseStatus;
use Database\Factories\AnimalControlCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalControlCase extends Model
{
    /** @use HasFactory<AnimalControlCaseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'reported_at',
        'status',
        'location_area',
        'species',
        'summary',
        'notes',
        'reporter_person_id',
        'linked_animal_id',
        'assigned_user_id',
        'dispatched_at',
        'closed_at',
        'close_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnimalControlCaseStatus::class,
            'reported_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reporter_person_id');
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function linkedAnimal(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'linked_animal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @param  Builder<AnimalControlCase>  $query
     * @return Builder<AnimalControlCase>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('reference', 'like', $like)
                ->orWhere('location_area', 'like', $like)
                ->orWhere('species', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('notes', 'like', $like);
        });
    }

    /**
     * @param  Builder<AnimalControlCase>  $query
     * @return Builder<AnimalControlCase>
     */
    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        $status = trim((string) $status);

        if ($status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public static function nextReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'AC-'.$year.'-';
        $latest = static::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/^AC-\d{4}-(\d+)$/', $latest, $matches) === 1) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
