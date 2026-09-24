<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Volunteer = 'volunteer';
    case Readonly = 'readonly';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Staff => 'Staff',
            self::Volunteer => 'Volunteer',
            self::Readonly => 'Read only',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function canManageAnimals(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }

    public function canManagePeople(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }

    public function canManageMovements(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }

    public function canManageLostFound(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }

    public function canManageMedical(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }

    public function canManageDiary(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }

    public function canManageMedia(): bool
    {
        return match ($this) {
            self::Admin, self::Staff => true,
            self::Volunteer, self::Readonly => false,
        };
    }
}
