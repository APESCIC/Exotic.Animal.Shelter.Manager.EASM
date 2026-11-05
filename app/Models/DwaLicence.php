<?php

namespace App\Models;

use Database\Factories\DwaLicenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DwaLicence extends Model
{
    /** @use HasFactory<DwaLicenceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'licence_number',
        'issuing_authority',
        'species_covered',
        'conditions',
        'issued_on',
        'expires_on',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<Animal, $this>
     */
    public function animals(): BelongsToMany
    {
        return $this->belongsToMany(Animal::class)->withTimestamps()->orderBy('name');
    }

    /**
     * @return HasMany<DwaInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(DwaInspection::class)->orderByDesc('inspected_on')->orderByDesc('id');
    }

    public function isExpired(): bool
    {
        return $this->expires_on->lt(today());
    }

    public function isExpiringSoon(): bool
    {
        return ! $this->isExpired() && $this->expires_on->lte(self::reminderHorizon());
    }

    public function statusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        $days = (int) today()->diffInDays($this->expires_on);

        return match (true) {
            $days === 0 => 'Expires today',
            $this->isExpiringSoon() => "Expires in {$days} ".($days === 1 ? 'day' : 'days'),
            default => 'Current',
        };
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->isExpired() => 'dwa',
            $this->isExpiringSoon() => 'quarantine',
            default => 'adoptable',
        };
    }

    /**
     * One key per expiry date, so a renewed licence starts a fresh reminder.
     */
    public function reminderKey(): string
    {
        return self::reminderKeyPrefix($this->id).$this->expires_on->format('Y-m-d');
    }

    public static function reminderKeyPrefix(int $id): string
    {
        return 'dwa-licence:'.$id.':';
    }

    /**
     * Shelter setting: how many days before expiry the renewal reminder is raised.
     */
    public static function reminderDays(): int
    {
        return (int) config('easm.dwa_reminder_days', Setting::DEFAULT_DWA_REMINDER_DAYS);
    }

    public function reminderDueOn(): Carbon
    {
        return $this->expires_on->copy()->subDays(self::reminderDays());
    }

    public static function reminderHorizon(): Carbon
    {
        return today()->addDays(self::reminderDays());
    }

    /**
     * Expired, or expiring within the renewal reminder window.
     *
     * @param  Builder<DwaLicence>  $query
     * @return Builder<DwaLicence>
     */
    public function scopeNeedsRenewal(Builder $query): Builder
    {
        return $query->whereDate('expires_on', '<=', self::reminderHorizon());
    }
}
