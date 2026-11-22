<?php

namespace Database\Seeders;

use App\Models\LookupList;
use App\Models\LookupValue;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    /**
     * UK exotic-shelter starter lists. Safe to re-run: creates missing lists/values only.
     */
    public function run(): void
    {
        foreach ($this->lists() as $listDef) {
            $list = LookupList::query()->firstOrCreate(
                ['key' => $listDef['key']],
                [
                    'name' => $listDef['name'],
                    'description' => $listDef['description'],
                ],
            );

            foreach ($listDef['values'] as $index => $valueName) {
                LookupValue::query()->firstOrCreate(
                    [
                        'lookup_list_id' => $list->id,
                        'name' => $valueName,
                    ],
                    [
                        'sort_order' => ($index + 1) * 10,
                        'active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{key: string, name: string, description: string, values: list<string>}>
     */
    private function lists(): array
    {
        return [
            [
                'key' => 'colour',
                'name' => 'Colour',
                'description' => 'Coat, scale or plumage colour for exotic animals.',
                'values' => [
                    'Albino',
                    'Axanthic',
                    'Green',
                    'Grey',
                    'Mixed',
                    'Normal',
                    'Orange',
                    'Patterned',
                    'Red',
                    'Unknown',
                    'Yellow',
                ],
            ],
            [
                'key' => 'breed_type',
                'name' => 'Breed / type',
                'description' => 'Morph, locality or type labels used on animal records.',
                'values' => [
                    'Captive bred',
                    'Locality',
                    'Morph',
                    'Rescue type',
                    'Unknown',
                    'Wild type',
                ],
            ],
            [
                'key' => 'entry_reason',
                'name' => 'Entry reason',
                'description' => 'Why the animal entered the shelter.',
                'values' => [
                    'Abandoned',
                    'Born in care',
                    'Confiscation',
                    'Owner surrender',
                    'Stray',
                    'Transfer in',
                    'Wildlife rescue',
                    'Other',
                ],
            ],
            [
                'key' => 'death_reason',
                'name' => 'Death reason',
                'description' => 'Cause recorded when an animal dies in care.',
                'values' => [
                    'Died in care',
                    'Euthanasia (welfare)',
                    'Natural causes',
                    'Unknown',
                ],
            ],
            [
                'key' => 'flags',
                'name' => 'Flags',
                'description' => 'Tags that can apply together on an animal record.',
                'values' => [
                    'Bite risk',
                    'Bonded',
                    'Do not handle',
                    'Escape risk',
                    'Quarantine note',
                    'Special diet',
                    'Venomous',
                ],
            ],
        ];
    }
}
