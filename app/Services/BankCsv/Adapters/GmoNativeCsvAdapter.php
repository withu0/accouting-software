<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use InvalidArgumentException;

class GmoNativeCsvAdapter extends NativeCsvAdapter
{
    public function __construct(BankCsvRowBuilder $builder)
    {
        parent::__construct($builder, BankCsvFormat::GmoNative);
    }

    public function matches(array $lines): bool
    {
        if (! parent::matches($lines)) {
            return false;
        }

        $header = $this->builder->findHeaderRow($lines, ['日付', '摘要', '入金額', '出金額', '残高']);
        if ($header === null) {
            return false;
        }

        return in_array('メモ', $header['headers'], true);
    }
}
