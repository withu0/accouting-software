<?php

namespace App\Services\BankCsv;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Adapters\GmoNativeCsvAdapter;
use App\Services\BankCsv\Adapters\GmoZenginCsvAdapter;
use App\Services\BankCsv\Adapters\GmoZenginFixedAdapter;
use App\Services\BankCsv\Adapters\NativeCsvAdapter;
use App\Services\BankCsv\Adapters\RakutenBankCsvAdapter;
use App\Services\BankCsv\Adapters\SbiSumishinBankCsvAdapter;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use InvalidArgumentException;

class BankCsvFormatDetector
{
    /** @var array<int, BankCsvFormatAdapter> */
    private array $adapters;

    public function __construct(BankCsvRowBuilder $builder)
    {
        $this->adapters = [
            new GmoZenginFixedAdapter($builder),
            new GmoZenginCsvAdapter($builder),
            new RakutenBankCsvAdapter($builder),
            new SbiSumishinBankCsvAdapter($builder),
            new GmoNativeCsvAdapter($builder),
            new NativeCsvAdapter($builder),
        ];
    }

    /**
     * @param  array<int, string>  $lines
     */
    public function detect(array $lines): BankCsvFormatAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->matches($lines)) {
                return $adapter;
            }
        }

        throw new InvalidArgumentException(
            '対応していないCSV形式です。GMOあおぞら（当社CSV・全銀CSV・全銀固定長）、楽天銀行、住信SBIネット銀行、または標準形式（日付・摘要・入金額・出金額・残高）のCSVをアップロードしてください。'
        );
    }

    public function formatLabel(BankCsvFormat $format): string
    {
        return $format->label();
    }
}
