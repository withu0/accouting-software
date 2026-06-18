<?php

namespace App\Enums;

enum BankCsvFormat: string
{
    case Native = 'native';
    case GmoNative = 'gmo_native';
    case GmoZenginCsv = 'gmo_zengin_csv';
    case GmoZenginFixed = 'gmo_zengin_fixed';
    case Rakuten = 'rakuten';
    case SbiSumishin = 'sbi_sumishin';

    public function label(): string
    {
        return match ($this) {
            self::Native => '標準形式',
            self::GmoNative => 'GMOあおぞら（当社CSV）',
            self::GmoZenginCsv => 'GMOあおぞら（全銀CSV）',
            self::GmoZenginFixed => 'GMOあおぞら（全銀固定長）',
            self::Rakuten => '楽天銀行',
            self::SbiSumishin => '住信SBIネット銀行',
        };
    }
}
