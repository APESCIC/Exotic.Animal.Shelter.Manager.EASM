<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Setting;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;

/**
 * Public-safe adoptable animal data shared by the JSON API and the partner feeds.
 */
class AdoptableFeed
{
    public const CSV_COLUMNS = [
        'id',
        'name',
        'species',
        'breed_type',
        'sex',
        'age_years',
        'date_of_birth',
        'colour',
        'bonded_animals',
        'photo_url',
        'url',
        'updated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function payload(Animal $animal): array
    {
        $photoUrl = $animal->primaryPhotoUrl();

        return [
            'id' => $animal->id,
            'name' => $animal->name,
            'species' => $animal->species,
            'breed_type' => $animal->breed_type,
            'sex' => $animal->sex->value,
            'age_years' => $animal->age_years,
            'date_of_birth' => $animal->date_of_birth?->toDateString(),
            'colour' => $animal->colour,
            'bonded_animals' => $animal->bonded_animals,
            'photo_url' => $photoUrl !== null ? url($photoUrl) : null,
            'url' => route('public.adopt.show', $animal),
            'updated_at' => $animal->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, Animal>
     */
    public function animals(): Collection
    {
        return Animal::query()->adoptable()->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $animals = $this->animals();

        return [
            'organisation' => $this->organisationName(),
            'generated_at' => now()->toIso8601String(),
            'count' => $animals->count(),
            'data' => $animals->map(fn (Animal $animal): array => $this->payload($animal))->values()->all(),
        ];
    }

    public function csv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::CSV_COLUMNS, escape: '');

        foreach ($this->animals() as $animal) {
            $payload = $this->payload($animal);
            fputcsv($handle, array_map(
                fn (string $column): string => $this->csvCell($payload[$column]),
                self::CSV_COLUMNS,
            ), escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function rss(): string
    {
        $organisation = $this->organisationName();
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $dom->appendChild($rss);
        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);
        $this->appendText($dom, $channel, 'title', $organisation.' · Animals for adoption');
        $this->appendText($dom, $channel, 'link', route('public.adopt.index'));
        $this->appendText($dom, $channel, 'description', 'Animals currently available for adoption from '.$organisation.'.');
        $this->appendText($dom, $channel, 'language', 'en-gb');
        $this->appendText($dom, $channel, 'lastBuildDate', now()->toRfc2822String());

        foreach ($this->animals() as $animal) {
            $payload = $this->payload($animal);
            $item = $dom->createElement('item');
            $channel->appendChild($item);
            $this->appendText($dom, $item, 'title', $payload['name'].' ('.$payload['species'].')');
            $this->appendText($dom, $item, 'link', $payload['url']);
            $this->appendText($dom, $item, 'guid', $payload['url']);
            $this->appendText($dom, $item, 'description', $this->summary($payload));

            if ($animal->updated_at !== null) {
                $this->appendText($dom, $item, 'pubDate', $animal->updated_at->toRfc2822String());
            }

            if ($payload['photo_url'] !== null) {
                $enclosure = $dom->createElement('enclosure');
                $enclosure->setAttribute('url', $payload['photo_url']);
                $enclosure->setAttribute('type', $this->imageType($payload['photo_url']));
                $enclosure->setAttribute('length', '0');
                $item->appendChild($enclosure);
            }
        }

        return (string) $dom->saveXML();
    }

    /**
     * Partner spreadsheets execute leading =, +, - and @ as formulas.
     */
    private function csvCell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summary(array $payload): string
    {
        return implode(' · ', array_filter([
            $payload['species'],
            $payload['breed_type'],
            $payload['sex'] !== 'unknown' ? ucfirst((string) $payload['sex']) : null,
            $payload['age_years'] !== null ? $payload['age_years'].' years' : null,
            $payload['colour'],
        ]));
    }

    private function imageType(string $url): string
    {
        return match (strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function appendText(DOMDocument $dom, DOMElement $parent, string $name, string $text): void
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($text));
        $parent->appendChild($element);
    }

    private function organisationName(): string
    {
        return Setting::current()?->organisation_name ?? config('app.name');
    }
}
