<?php

namespace App\Enums;

enum InvoiceTaxRate: string
{
    case Standard = '10';
    case Reduced = '8';

    public function percent(): int
    {
        return (int) $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => '10%',
            self::Reduced => '8%（軽減）',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Standard => '10%',
            self::Reduced => '8% 軽',
        };
    }

    public function isReduced(): bool
    {
        return $this === self::Reduced;
    }
}
