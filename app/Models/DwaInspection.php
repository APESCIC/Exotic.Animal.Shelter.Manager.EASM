<?php

namespace App\Models;

use App\Enums\DwaInspectionOutcome;
use Database\Factories\DwaInspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DwaInspection extends Model
{
    /** @use HasFactory<DwaInspectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dwa_licence_id',
        'inspected_on',
        'inspector',
        'outcome',
        'notes',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspected_on' => 'date',
            'outcome' => DwaInspectionOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<DwaLicence, $this>
     */
    public function licence(): BelongsTo
    {
        return $this->belongsTo(DwaLicence::class, 'dwa_licence_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
