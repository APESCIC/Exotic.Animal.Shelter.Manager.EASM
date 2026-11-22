<?php

namespace App\Models;

use Database\Factories\LookupListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LookupList extends Model
{
    /** @use HasFactory<LookupListFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
    ];

    /**
     * @return HasMany<LookupValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(LookupValue::class);
    }

    /**
     * @return HasMany<LookupValue, $this>
     */
    public function activeValues(): HasMany
    {
        return $this->values()->where('active', true)->orderBy('sort_order')->orderBy('name');
    }

    public static function key(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }
}
