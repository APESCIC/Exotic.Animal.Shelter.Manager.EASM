<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\LookupValueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookupValue extends Model
{
    /** @use HasFactory<LookupValueFactory> */
    use HasFactory;

    use RecordsAudit;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lookup_list_id',
        'name',
        'description',
        'sort_order',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LookupList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(LookupList::class, 'lookup_list_id');
    }

    /**
     * @param  Builder<LookupValue>  $query
     * @return Builder<LookupValue>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<LookupValue>  $query
     * @return Builder<LookupValue>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
