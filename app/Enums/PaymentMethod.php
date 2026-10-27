<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case StandingOrder = 'standing_order';
    case Cheque = 'cheque';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::BankTransfer => 'Bank transfer',
            self::StandingOrder => 'Standing order',
            self::Cheque => 'Cheque',
            self::Online => 'Online platform',
            self::Other => 'Other',
        };
    }
}
