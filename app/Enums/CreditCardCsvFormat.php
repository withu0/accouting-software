<?php

namespace App\Enums;

enum CreditCardCsvFormat: string
{
    case Saison = 'saison';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Saison => 'セゾンカード',
            self::Generic => '汎用形式',
        };
    }
}
