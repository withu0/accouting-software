<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Support\BankCsvRowBuilder;

class GmoNativeCsvAdapter extends NativeCsvAdapter
{
    public function __construct(BankCsvRowBuilder $builder)
    {
        parent::__construct($builder, BankCsvFormat::GmoNative);
    }

    public function matches(array $lines): bool
    {
        $header = $this->findHeader($lines);

        return $header !== null && in_array('メモ', $header['headers'], true);
    }
}
