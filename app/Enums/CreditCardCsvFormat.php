<?php

namespace App\Enums;

enum CreditCardCsvFormat: string
{
    case Saison = 'saison';
    case Generic = 'generic';
    case AiMapped = 'ai_mapped';

    public function label(): string
    {
        return match ($this) {
            self::Saison => 'セゾンカード',
            self::Generic => '汎用形式',
            self::AiMapped => 'AI列判定',
        };
    }
}
