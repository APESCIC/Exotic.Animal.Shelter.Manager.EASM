<?php

namespace App\Models;

use App\Enums\StockCategory;
use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    /** @use HasFactory<StockItemFactory> */
    use HasFactory;

    public const QUANTITY_PATTERN = '/^\d{1,9}(\.\d{1,2})?$/';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'unit',
        'quantity',
        'reorder_level',
        'location',
        'notes',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StockCategory::class,
            'quantity' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isLow(): bool
    {
        return $this->active
            && $this->reorder_level !== null
            && bccomp((string) $this->quantity, (string) $this->reorder_level, 2) <= 0;
    }

    public function formattedQuantity(): string
    {
        return self::trimDecimal((string) $this->quantity).' '.$this->unit;
    }

    public static function trimDecimal(?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    /**
     * @param  Builder<StockItem>  $query
     * @return Builder<StockItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<StockItem>  $query
     * @return Builder<StockItem>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereNotNull('reorder_level')
            ->whereColumn('quantity', '<=', 'reorder_level');
    }
}
