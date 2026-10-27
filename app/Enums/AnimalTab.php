<?php

namespace App\Enums;

use App\Models\Animal;

enum AnimalTab: string
{
    case Overview = 'overview';
    case Health = 'health';
    case Husbandry = 'husbandry';
    case Movements = 'movements';
    case Legal = 'legal';
    case Media = 'media';
    case Diary = 'diary';
    case Log = 'log';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Overview',
            self::Health => 'Health',
            self::Husbandry => 'Husbandry',
            self::Movements => 'Movements',
            self::Legal => 'Legal',
            self::Media => 'Media',
            self::Diary => 'Diary',
            self::Log => 'Log',
        };
    }

    /**
     * Relations the tab renders, so each request loads only what it shows.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        return match ($this) {
            self::Overview => ['customFieldValues.definition'],
            self::Health => ['medicalRecords'],
            self::Husbandry => ['diets'],
            self::Movements => ['movements.person'],
            self::Legal => [],
            self::Media => ['media.uploader'],
            self::Diary => ['diaryTasks.assignee'],
            self::Log => ['observations.user'],
        };
    }

    /**
     * Relation counted for the tab label, if any.
     */
    public function countedRelation(): ?string
    {
        return match ($this) {
            self::Overview, self::Legal => null,
            self::Health => 'medicalRecords',
            self::Husbandry => 'diets',
            self::Movements => 'movements',
            self::Media => 'media',
            self::Diary => 'diaryTasks',
            self::Log => 'observations',
        };
    }

    public function url(Animal|int $animal): string
    {
        return $this === self::Overview
            ? route('animals.show', $animal)
            : route('animals.show', [$animal, $this->value]);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $tab): string => $tab->value, self::cases());
    }
}
