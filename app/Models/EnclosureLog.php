<?php

namespace App\Models;

use Database\Factories\EnclosureLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnclosureLog extends Model
{
    /** @use HasFactory<EnclosureLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'enclosure_id',
        'recorded_at',
        'temperature_hot_c',
        'temperature_cool_c',
        'humidity_percent',
        'notes',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature_hot_c' => 'decimal:1',
            'temperature_cool_c' => 'decimal:1',
            'humidity_percent' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Enclosure, $this>
     */
    public function enclosure(): BelongsTo
    {
        return $this->belongsTo(Enclosure::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function formatTemperature(?string $value): string
    {
        return $value === null ? '—' : rtrim(rtrim($value, '0'), '.').' °C';
    }
}
