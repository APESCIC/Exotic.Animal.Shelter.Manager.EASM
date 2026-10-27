<?php

namespace App\Enums;

use App\Models\DiaryTask;
use Illuminate\Database\Eloquent\Builder;

enum DiaryDueFilter: string
{
    case Today = 'today';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Due today',
            self::Overdue => 'Overdue',
        };
    }

    /**
     * @param  Builder<DiaryTask>  $query
     * @return Builder<DiaryTask>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::Today => $query->dueToday(),
            self::Overdue => $query->overdue(),
        };
    }
}
