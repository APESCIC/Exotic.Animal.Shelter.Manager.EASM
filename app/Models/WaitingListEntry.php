<?php

namespace App\Models;

use App\Enums\WaitingListStatus;
use App\Enums\WaitingListType;
use App\Enums\WaitingListUrgency;
use Database\Factories\WaitingListEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingListEntry extends Model
{
    /** @use HasFactory<WaitingListEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'person_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'species',
        'details',
        'urgency',
        'added_on',
        'status',
        'closed_on',
        'closed_reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WaitingListType::class,
            'urgency' => WaitingListUrgency::class,
            'status' => WaitingListStatus::class,
            'added_on' => 'date',
            'closed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function contactLabel(): string
    {
        return $this->person?->name ?? $this->contact_name ?? '—';
    }

    public function daysWaiting(): int
    {
        $end = $this->closed_on ?? today();

        return (int) $this->added_on->diffInDays($end);
    }

    /**
     * Most urgent first, then longest waiting.
     *
     * @param  Builder<WaitingListEntry>  $query
     * @return Builder<WaitingListEntry>
     */
    public function scopeByUrgency(Builder $query): Builder
    {
        $cases = implode(' ', array_map(
            fn (WaitingListUrgency $urgency): string => 'when ? then '.$urgency->rank(),
            WaitingListUrgency::cases(),
        ));

        return $query
            ->orderByRaw(
                'case urgency '.$cases.' else 99 end',
                array_map(fn (WaitingListUrgency $urgency): string => $urgency->value, WaitingListUrgency::cases()),
            )
            ->orderBy('added_on')
            ->orderBy('id');
    }
}
