<?php

namespace App\Models;

use Database\Factories\DiaryTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryTask extends Model
{
    /** @use HasFactory<DiaryTaskFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'assigned_to',
        'created_by',
        'title',
        'body',
        'due_on',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @param  Builder<DiaryTask>  $query
     * @return Builder<DiaryTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<DiaryTask>  $query
     * @return Builder<DiaryTask>
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->open()->whereDate('due_on', today());
    }

    /**
     * @param  Builder<DiaryTask>  $query
     * @return Builder<DiaryTask>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_on')->whereDate('due_on', '<', today());
    }
}
