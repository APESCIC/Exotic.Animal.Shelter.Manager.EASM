<?php

namespace App\Models;

use App\Enums\AnimalSex;
use App\Enums\CitesAnnex;
use App\Enums\CitesCertificateType;
use App\Enums\MovementType;
use Database\Factories\AnimalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Animal extends Model
{
    /** @use HasFactory<AnimalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    private const NOT_LISTED_VALUES = ['none', 'no', 'n/a', 'na', 'not listed', 'non-cites', '-'];

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
        'is_adoptable',
        'deceased_at',
        'death_reason',
        'enclosure_id',
        'cites',
        'cites_annex',
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
            'cites_annex' => CitesAnnex::class,
            'date_of_birth' => 'date',
            'deceased_at' => 'date',
            'non_shelter' => 'boolean',
            'is_adoptable' => 'boolean',
            'age_years' => 'integer',
        ];
    }

    /**
     * Public listing: staff opted in, not deceased, not a non-shelter record.
     *
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeAdoptable(Builder $query): Builder
    {
        return $query
            ->where('is_adoptable', true)
            ->whereNull('deceased_at')
            ->where('non_shelter', false);
    }

    public function isPubliclyAdoptable(): bool
    {
        return $this->is_adoptable
            && $this->deceased_at === null
            && ! $this->non_shelter;
    }

    /**
     * @return BelongsTo<Enclosure, $this>
     */
    public function enclosure(): BelongsTo
    {
        return $this->belongsTo(Enclosure::class);
    }

    /**
     * @return HasMany<CitesCertificate, $this>
     */
    public function citesCertificates(): HasMany
    {
        return $this->hasMany(CitesCertificate::class)->orderByDesc('issued_on')->orderByDesc('id');
    }

    /**
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    /**
     * @return HasOne<Movement, $this>
     */
    public function latestMovement(): HasOne
    {
        return $this->hasOne(Movement::class)->ofMany(['moved_at' => 'max', 'id' => 'max']);
    }

    public function currentMovementType(): ?MovementType
    {
        return $this->latestMovement?->type;
    }

    public function isInQuarantine(): bool
    {
        return $this->deceased_at === null
            && $this->currentMovementType() === MovementType::Quarantine;
    }

    /**
     * Living animals whose most recent movement is quarantine.
     *
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeInQuarantine(Builder $query): Builder
    {
        return $query
            ->whereNull('deceased_at')
            ->whereHas('latestMovement', function (Builder $movement): void {
                $movement->where('type', MovementType::Quarantine->value);
            });
    }

    /**
     * Living shelter animals that have not been adopted, reclaimed or transferred out.
     *
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeInCare(Builder $query): Builder
    {
        return $query
            ->whereNull('deceased_at')
            ->where('non_shelter', false)
            ->whereDoesntHave('latestMovement', function (Builder $movement): void {
                $movement->whereIn('type', MovementType::departureValues());
            });
    }

    /**
     * Living animals with a medical record due before today and not yet given.
     *
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeWithOverdueMedical(Builder $query): Builder
    {
        return $query
            ->whereNull('deceased_at')
            ->whereHas('medicalRecords', function (Builder $records): void {
                $records->overdue();
            });
    }

    /**
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeCitesListed(Builder $query): Builder
    {
        return $query->inCare()->where(function (Builder $inner): void {
            $inner->whereIn('cites_annex', CitesAnnex::listedValues())
                ->orWhere(fn (Builder $reference) => $this->whereListed($reference, 'cites'));
        });
    }

    /**
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeDwaListed(Builder $query): Builder
    {
        return $this->whereListed($query->inCare(), 'dwa');
    }

    /**
     * Annex A animals in care with no Article 10 certificate on file.
     *
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeMissingArticleTen(Builder $query): Builder
    {
        return $query
            ->inCare()
            ->where('cites_annex', CitesAnnex::A->value)
            ->whereDoesntHave('citesCertificates', function (Builder $certificates): void {
                $certificates->where('type', CitesCertificateType::Article10->value);
            });
    }

    /**
     * Animals with an Annex, a CITES reference or a certificate on file.
     *
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function scopeOnCitesRegister(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereIn('cites_annex', CitesAnnex::listedValues())
                ->orHas('citesCertificates')
                ->orWhere(fn (Builder $reference) => $this->whereListed($reference, 'cites'));
        });
    }

    public function isMissingArticleTen(): bool
    {
        return $this->cites_annex === CitesAnnex::A
            && ! $this->citesCertificates->contains('type', CitesCertificateType::Article10);
    }

    public function hasCitesListing(): bool
    {
        return $this->cites_annex?->isListed() || self::isListedValue($this->cites);
    }

    public function citesSummary(): string
    {
        return implode(' · ', array_filter([
            $this->cites_annex?->isListed() ? $this->cites_annex->label() : null,
            self::isListedValue($this->cites) ? $this->cites : null,
        ]));
    }

    public function hasDwaListing(): bool
    {
        return self::isListedValue($this->dwa);
    }

    /**
     * CITES and DWA are free text, so staff may type a negative instead of leaving them blank.
     */
    private static function isListedValue(?string $value): bool
    {
        $normalised = strtolower(trim((string) $value));

        return $normalised !== '' && ! in_array($normalised, self::NOT_LISTED_VALUES, true);
    }

    /**
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    private function whereListed(Builder $query, string $column): Builder
    {
        $placeholders = implode(', ', array_fill(0, count(self::NOT_LISTED_VALUES), '?'));

        return $query
            ->whereNotNull($column)
            ->whereRaw("lower(trim({$column})) != ''")
            ->whereRaw("lower(trim({$column})) not in ({$placeholders})", self::NOT_LISTED_VALUES);
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
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<Sponsorship, $this>
     */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class)->orderByDesc('starts_on')->orderByDesc('id');
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
                ->orWhereHas('enclosure', function (Builder $enclosure) use ($like): void {
                    $enclosure->where('name', 'like', $like);
                })
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
