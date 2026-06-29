<?php

namespace App\Enums;

enum SimplifiedTaxIndustry: string
{
    case Type1 = 'type_1';
    case Type2 = 'type_2';
    case Type3 = 'type_3';
    case Type4 = 'type_4';
    case Type5 = 'type_5';
    case Type6 = 'type_6';

    public function label(): string
    {
        return match ($this) {
            self::Type1 => '第1種事業（卸売業）',
            self::Type2 => '第2種事業（小売業）',
            self::Type3 => '第3種事業（製造業等）',
            self::Type4 => '第4種事業（その他）',
            self::Type5 => '第5種事業（サービス業等）',
            self::Type6 => '第6種事業（不動産業）',
        };
    }

    public function deemedPurchaseRatioPercent(): int
    {
        return match ($this) {
            self::Type1 => 90,
            self::Type2 => 80,
            self::Type3 => 70,
            self::Type4 => 60,
            self::Type5 => 50,
            self::Type6 => 40,
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
