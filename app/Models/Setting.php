<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organisation_name',
        'locale',
        'timezone',
        'partner_feed_enabled',
        'partner_feed_token',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'partner_feed_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'partner_feed_enabled' => 'boolean',
        ];
    }

    public function partnerFeedAccepts(?string $token): bool
    {
        return $this->partner_feed_enabled
            && filled($this->partner_feed_token)
            && is_string($token)
            && hash_equals($this->partner_feed_token, $token);
    }

    /**
     * Single-row shelter settings for this install.
     */
    public static function current(): ?self
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        return static::query()->first();
    }

    /**
     * Apply organisation settings to the running app config.
     */
    public function applyToConfig(): void
    {
        config([
            'app.name' => $this->organisation_name,
            'app.locale' => $this->locale,
            'app.fallback_locale' => $this->locale,
            'app.faker_locale' => $this->locale,
            'app.timezone' => $this->timezone,
        ]);

        date_default_timezone_set($this->timezone);
    }
}
