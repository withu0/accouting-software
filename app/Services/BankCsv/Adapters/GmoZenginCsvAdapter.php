<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\BankCsv\Support\ZenginDateParser;
use InvalidArgumentException;

class GmoZenginCsvAdapter implements BankCsvFormatAdapter
{
    private const DATA_RECORD_TYPE = '2';

    public function __construct(
        private readonly BankCsvRowBuilder $builder,
    ) {}

    public function format(): BankCsvFormat
    {
        return BankCsvFormat::GmoZenginCsv;
    }

    public function matches(array $lines): bool
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = $this->builder->parseCsvLine($line);
            if (($fields[0] ?? '') === '1' && ($fields[1] ?? '') === '03') {
                return true;
            }
        }

        return false;
    }

    public function parse(array $lines): array
    {
        $rows = [];
        $rowNumber = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = $this->builder->parseCsvLine($line);
            if (($fields[0] ?? '') !== self::DATA_RECORD_TYPE) {
                continue;
            }

            $rowNumber++;
            $rows[] = $this->parseDataRecord($fields, $rowNumber);
        }

        if ($rows === []) {
            throw new InvalidArgumentException('CSV file contains no data rows.');
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array{
     *     transaction_date: \Carbon\Carbon,
     *     description: string,
     *     deposit_amount: int,
     *     withdrawal_amount: int,
     *     balance: ?int,
     *     row_hash: string,
     * }
     */
    private function parseDataRecord(array $fields, int $rowNumber): array
    {
        $transactionDate = ZenginDateParser::parse(trim($fields[2] ?? ''));
        $direction = trim($fields[4] ?? '');
        $amount = $this->builder->parseUnsignedAmount($fields[6] ?? '0');
        $remitter = trim($fields[14] ?? '');
        $summary = trim($fields[17] ?? '');
        $description = $remitter !== '' ? $remitter : $summary;
        if ($description === '' && $summary !== '') {
            $description = $summary;
        }

        $depositAmount = $direction === '1' ? $amount : 0;
        $withdrawalAmount = $direction === '2' ? $amount : 0;

        return $this->builder->buildRow(
            $transactionDate,
            $description,
            $depositAmount,
            $withdrawalAmount,
            null,
            $rowNumber,
        );
    }
}
