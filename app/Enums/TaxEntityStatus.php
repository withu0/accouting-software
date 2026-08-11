<?php

namespace App\Enums;

enum TaxEntityStatus: string
{
    case Taxable = 'taxable';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Taxable => '課税事業者',
            self::Exempt => '免税事業者',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Taxable => '消費税を納める義務があります。税区分に応じて仮受・仮払消費税を分離します。',
            self::Exempt => '消費税の納税義務がありません。税区分は使わず、税込金額のまま計上します。',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases(),
        );
    }
}
