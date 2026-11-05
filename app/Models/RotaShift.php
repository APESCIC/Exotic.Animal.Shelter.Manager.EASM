<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RotaShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotaShift extends Model
{
    /** @use HasFactory<RotaShiftFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'starts_at',
        'ends_at',
        'area',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Shifts that start on or after $from and before $to.
     *
     * @param  Builder<RotaShift>  $query
     * @return Builder<RotaShift>
     */
    public function scopeStartingBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where('starts_at', '>=', $from)->where('starts_at', '<', $to);
    }

    /**
     * @param  Builder<RotaShift>  $query
     * @return Builder<RotaShift>
     */
    public function scopeUpcomingFor(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)->where('ends_at', '>', now());
    }

    public function timeRange(): string
    {
        $range = $this->starts_at->format('H:i').'–'.$this->ends_at->format('H:i');

        return $this->ends_at->isSameDay($this->starts_at) ? $range : $range.' (next day)';
    }

    public function assigneeName(): string
    {
        return $this->user?->name ?? 'Open shift';
    }
}
