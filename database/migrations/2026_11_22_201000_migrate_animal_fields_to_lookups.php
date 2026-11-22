<?php

use App\Models\Animal;
use App\Models\LookupList;
use App\Support\LookupField;
use Database\Seeders\LookupSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const SINGLE_FIELDS = ['colour', 'breed_type', 'entry_reason', 'death_reason'];

    public function up(): void
    {
        (new LookupSeeder)->run();

        foreach (self::SINGLE_FIELDS as $field) {
            $list = LookupList::key($field === 'breed_type' ? 'breed_type' : $field);
            if ($list === null) {
                continue;
            }

            $distinct = DB::table('animals')
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->pluck($field);

            $map = [];
            foreach ($distinct as $raw) {
                $map[(string) $raw] = LookupField::ensureValue($list, (string) $raw);
            }

            foreach ($map as $from => $to) {
                if ($from === $to) {
                    continue;
                }
                DB::table('animals')->where($field, $from)->update([$field => $to]);
            }
        }

        $flagsList = LookupList::key('flags');
        if ($flagsList === null) {
            return;
        }

        Animal::query()
            ->whereNotNull('flags')
            ->where('flags', '!=', '')
            ->orderBy('id')
            ->each(function (Animal $animal) use ($flagsList): void {
                $raw = (string) $animal->getRawOriginal('flags');
                if ($raw === '') {
                    return;
                }

                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $parts = $decoded;
                } else {
                    $parts = preg_split('/\s*[,;|]\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [$raw];
                }

                $names = [];
                foreach ($parts as $part) {
                    $canonical = LookupField::ensureValue($flagsList, (string) $part);
                    if ($canonical !== '') {
                        $names[] = $canonical;
                    }
                }

                $names = array_values(array_unique($names));
                DB::table('animals')->where('id', $animal->id)->update([
                    'flags' => $names === [] ? null : json_encode($names, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    public function down(): void
    {
        Animal::query()
            ->whereNotNull('flags')
            ->where('flags', '!=', '')
            ->orderBy('id')
            ->each(function (Animal $animal): void {
                $raw = (string) $animal->getRawOriginal('flags');
                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    return;
                }

                DB::table('animals')->where('id', $animal->id)->update([
                    'flags' => $decoded === [] ? null : implode(', ', $decoded),
                ]);
            });
    }
};
