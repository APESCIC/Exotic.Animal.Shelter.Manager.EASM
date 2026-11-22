<?php

namespace App\Support;

use App\Models\LookupList;
use App\Models\LookupValue;
use Illuminate\Support\Collection;

class LookupField
{
    /**
     * Active values for a list, plus any current names that are retired so existing
     * records can keep their selection unchanged.
     *
     * @param  string|list<string>|null  $current
     * @return Collection<int, LookupValue>
     */
    public static function options(string $listKey, string|array|null $current = null): Collection
    {
        $list = LookupList::key($listKey);
        if ($list === null) {
            return collect();
        }

        $currentNames = self::normalizeNames($current);
        $options = $list->values()->active()->ordered()->get();

        if ($currentNames === []) {
            return $options;
        }

        $missing = $list->values()
            ->whereIn('name', $currentNames)
            ->where('active', false)
            ->ordered()
            ->get();

        return $options->concat($missing)->unique('id')->values();
    }

    /**
     * @param  string|list<string>|null  $value
     * @return list<string>
     */
    public static function normalizeNames(string|array|null $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                $value,
            ), static fn (string $item): bool => $item !== ''));
        }

        return [trim($value)];
    }

    /**
     * Allowed names for validation: active values plus any already on the record.
     *
     * @param  string|list<string>|null  $current
     * @return list<string>
     */
    public static function allowedNames(string $listKey, string|array|null $current = null): array
    {
        return self::options($listKey, $current)->pluck('name')->all();
    }

    /**
     * Ensure a lookup value exists for a free-text name (case-insensitive match).
     * Returns the canonical stored name.
     */
    public static function ensureValue(LookupList $list, string $rawName): string
    {
        $trimmed = trim($rawName);
        if ($trimmed === '') {
            return '';
        }

        $existing = $list->values()
            ->get()
            ->first(fn (LookupValue $value): bool => strcasecmp($value->name, $trimmed) === 0);

        if ($existing !== null) {
            return $existing->name;
        }

        $maxOrder = (int) $list->values()->max('sort_order');

        $created = $list->values()->create([
            'name' => $trimmed,
            'sort_order' => $maxOrder + 10,
            'active' => true,
        ]);

        return $created->name;
    }
}
