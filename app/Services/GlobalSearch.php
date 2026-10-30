<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Person;

class GlobalSearch
{
    public const MIN_LENGTH = 2;

    public const LIMIT_PER_GROUP = 8;

    /**
     * @return list<array{key: string, label: string, more_url: string, items: list<array{type: string, title: string, subtitle: string, url: string}>}>
     */
    public function search(string $term, int $limit = self::LIMIT_PER_GROUP): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            [
                'key' => 'animals',
                'label' => 'Animals',
                'more_url' => route('animals.index', ['q' => $term]),
                'items' => $this->animals($term, $limit),
            ],
            [
                'key' => 'people',
                'label' => 'People',
                'more_url' => route('people.index', ['q' => $term]),
                'items' => $this->people($term, $limit),
            ],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    /**
     * @return list<array{type: string, title: string, subtitle: string, url: string}>
     */
    private function animals(string $term, int $limit): array
    {
        return Animal::query()
            ->search($term)
            ->orderByRaw('case when identifying_code = ? or cites = ? or dwa = ? then 0 else 1 end', [$term, $term, $term])
            ->orderBy('name')
            ->limit($limit)
            ->with('enclosure:id,name')
            ->get(['id', 'name', 'species', 'identifying_code', 'cites', 'dwa', 'location', 'enclosure_id', 'deceased_at'])
            ->map(fn (Animal $animal): array => [
                'type' => 'animal',
                'title' => $animal->name,
                'subtitle' => $this->join([
                    $animal->species,
                    $animal->identifying_code,
                    $animal->hasCitesListing() ? 'CITES '.$animal->cites : null,
                    $animal->hasDwaListing() ? 'DWA '.$animal->dwa : null,
                    $animal->location,
                    $animal->enclosure ? 'Enclosure '.$animal->enclosure->name : null,
                    $animal->deceased_at ? 'Deceased' : null,
                ]),
                'url' => route('animals.show', $animal),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{type: string, title: string, subtitle: string, url: string}>
     */
    private function people(string $term, int $limit): array
    {
        return Person::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Person $person): array => [
                'type' => 'person',
                'title' => $person->name,
                'subtitle' => $this->join([
                    $person->categoryLabel(),
                    $person->email,
                    $person->phone,
                    $person->postcode,
                    $person->banned ? 'Banned' : null,
                ]),
                'url' => route('people.show', $person),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function join(array $parts): string
    {
        return implode(' · ', array_filter($parts, fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }
}
