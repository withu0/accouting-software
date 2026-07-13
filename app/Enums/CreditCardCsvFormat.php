<?php

namespace App\Enums;

enum CreditCardCsvFormat: string
{
    case Saison = 'saison';

    public function label(): string
    {
        return match ($this) {
            self::Saison => 'セゾンカード',
        };
    }
}
