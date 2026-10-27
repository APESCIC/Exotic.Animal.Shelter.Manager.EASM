<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\DocumentTemplate;
use App\Models\Person;
use App\Models\Setting;
use App\Support\UkDate;

/**
 * Turns a plain-text template into HTML. Templates are admin-editable, so they are never
 * evaluated as Blade: text is escaped and only whitelisted {tokens} are substituted.
 */
class DocumentRenderer
{
    public const BLANK = '____________';

    /**
     * @var array<string, string>
     */
    public const TOKENS = [
        'organisation.name' => 'Organisation name',
        'today' => "Today's date",
        'document.notes' => 'Notes typed when the document is generated',
        'animal.name' => 'Animal name',
        'animal.species' => 'Species',
        'animal.breed_type' => 'Breed or type',
        'animal.sex' => 'Sex',
        'animal.date_of_birth' => 'Date of birth',
        'animal.identifying_code' => 'Identifying code or microchip',
        'animal.colour' => 'Colour',
        'animal.location' => 'Location',
        'animal.enclosure' => 'Enclosure',
        'animal.cites' => 'CITES',
        'animal.dwa' => 'DWA licence',
        'person.name' => 'Person name',
        'person.email' => 'Email',
        'person.phone' => 'Phone',
        'person.address' => 'Address',
    ];

    public function render(DocumentTemplate $template, ?Animal $animal, ?Person $person, ?string $notes = null): string
    {
        $values = $this->values($animal, $person, $notes);
        $blocks = preg_split('/\R\s*\R/', trim($template->body)) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if (str_starts_with($block, '# ')) {
                $html[] = '<h2>'.$this->inline(substr($block, 2), $values).'</h2>';

                continue;
            }

            $html[] = '<p>'.nl2br($this->inline($block, $values), false).'</p>';
        }

        return implode("\n", $html);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function inline(string $text, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z_]+(?:\.[a-z_]+)?)\}/',
            fn (array $match): string => array_key_exists($match[1], $values) ? e($values[$match[1]]) : $match[0],
            e($text),
        );
    }

    /**
     * @return array<string, string>
     */
    private function values(?Animal $animal, ?Person $person, ?string $notes): array
    {
        $raw = [
            'organisation.name' => Setting::current()?->organisation_name ?? config('app.name'),
            'today' => UkDate::format(now()),
            'document.notes' => $notes,
            'animal.name' => $animal?->name,
            'animal.species' => $animal?->species,
            'animal.breed_type' => $animal?->breed_type,
            'animal.sex' => $animal?->sex?->label(),
            'animal.date_of_birth' => $animal?->date_of_birth ? UkDate::format($animal->date_of_birth) : null,
            'animal.identifying_code' => $animal?->identifying_code,
            'animal.colour' => $animal?->colour,
            'animal.location' => $animal?->location,
            'animal.enclosure' => $animal?->enclosure,
            'animal.cites' => $animal?->cites,
            'animal.dwa' => $animal?->dwa,
            'person.name' => $person?->name,
            'person.email' => $person?->email,
            'person.phone' => $person?->phone,
            'person.address' => $person?->addressLine(),
        ];

        return array_map(
            fn (?string $value): string => filled($value) ? (string) $value : self::BLANK,
            $raw,
        );
    }
}
