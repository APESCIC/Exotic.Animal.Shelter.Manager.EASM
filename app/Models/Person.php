<?php

namespace App\Models;

use App\Enums\PersonCategory;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'category_custom',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'town_city',
        'county',
        'postcode',
        'banned',
        'homechecked',
        'flags',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PersonCategory::class,
            'banned' => 'boolean',
            'homechecked' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderByDesc('id');
    }

    public function addressLine(): string
    {
        return implode(', ', array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->town_city,
            $this->county,
            $this->postcode,
        ]));
    }

    public function categoryLabel(): string
    {
        if ($this->category === PersonCategory::Custom && filled($this->category_custom)) {
            return $this->category_custom;
        }

        return $this->category->label();
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
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
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('postcode', 'like', $like)
                ->orWhere('town_city', 'like', $like)
                ->orWhere('category_custom', 'like', $like)
                ->orWhere('flags', 'like', $like)
                ->orWhere('notes', 'like', $like);
        });
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeOfCategory(Builder $query, ?string $category): Builder
    {
        $category = trim((string) $category);

        if ($category === '') {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeBannedOnly(Builder $query, bool $bannedOnly): Builder
    {
        if (! $bannedOnly) {
            return $query;
        }

        return $query->where('banned', true);
    }
}
