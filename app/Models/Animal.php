<?php

namespace App\Models;

use App\Enums\AnimalSex;
use Database\Factories\AnimalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Animal extends Model
{
    /** @use HasFactory<AnimalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'species',
        'breed_type',
        'sex',
        'date_of_birth',
        'age_years',
        'colour',
        'identifying_code',
        'flags',
        'location',
        'bonded_animals',
        'entry_reason',
        'non_shelter',
        'deceased_at',
        'death_reason',
        'enclosure',
        'cites',
        'dwa',
        'primary_photo_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sex' => AnimalSex::class,
            'date_of_birth' => 'date',
            'deceased_at' => 'date',
            'non_shelter' => 'boolean',
            'age_years' => 'integer',
        ];
    }

    /**
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<MedicalRecord, $this>
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class)
            ->orderByDesc('due_on')
            ->orderByDesc('given_on')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<Diet, $this>
     */
    public function diets(): HasMany
    {
        return $this->hasMany(Diet::class)->orderByDesc('started_on')->orderByDesc('id');
    }

    /**
     * @return HasMany<AnimalObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(AnimalObservation::class)->orderByDesc('observed_on')->orderByDesc('id');
    }

    /**
     * @return HasMany<DiaryTask, $this>
     */
    public function diaryTasks(): HasMany
    {
        return $this->hasMany(DiaryTask::class)
            ->orderByRaw('completed_at is not null')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<AnimalMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(AnimalMedia::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function primaryPhotoUrl(): ?string
    {
        if ($this->primary_photo_path === null || $this->primary_photo_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->primary_photo_path);
    }

    /**
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('species', 'like', $like)
                ->orWhere('breed_type', 'like', $like)
                ->orWhere('identifying_code', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('colour', 'like', $like)
                ->orWhere('enclosure', 'like', $like)
                ->orWhere('cites', 'like', $like)
                ->orWhere('dwa', 'like', $like)
                ->orWhereHas('customFieldValues', function (Builder $values) use ($like): void {
                    $values->where('value', 'like', $like);
                });
        });
    }

    /**
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeAtLocation(Builder $query, ?string $location): Builder
    {
        $location = trim((string) $location);

        if ($location === '') {
            return $query;
        }

        return $query->where('location', $location);
    }
}
