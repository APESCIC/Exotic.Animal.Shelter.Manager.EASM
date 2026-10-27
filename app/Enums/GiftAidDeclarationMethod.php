<?php

namespace App\Enums;

enum GiftAidDeclarationMethod: string
{
    case Written = 'written';
    case Online = 'online';
    case Oral = 'oral';

    public function label(): string
    {
        return match ($this) {
            self::Written => 'Written form',
            self::Online => 'Online form',
            self::Oral => 'Oral (recorded and confirmed in writing)',
        };
    }
}
