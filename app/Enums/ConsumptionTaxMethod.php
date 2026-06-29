<?php

namespace App\Enums;

enum ConsumptionTaxMethod: string
{
    case Standard = 'standard';
    case Simplified = 'simplified';

    public function label(): string
    {
        return match ($this) {
            self::Standard => '原則課税',
            self::Simplified => '簡易課税',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
