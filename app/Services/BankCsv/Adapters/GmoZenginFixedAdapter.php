<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\BankCsv\Support\ZenginDateParser;
use InvalidArgumentException;

class GmoZenginFixedAdapter implements BankCsvFormatAdapter
{
    private const DATA_RECORD_TYPE = '2';

    private const RECORD_LENGTH = 200;

    public function __construct(
        private readonly BankCsvRowBuilder $builder,
    ) {}

    public function format(): BankCsvFormat
    {
        return BankCsvFormat::GmoZenginFixed;
    }

    public function matches(array $lines): bool
    {
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            if (str_contains($line, ',')) {
                return false;
            }

            if (strlen($line) >= self::RECORD_LENGTH && ($line[0] ?? '') === '1') {
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
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $record = str_pad($line, self::RECORD_LENGTH, ' ');

            if ($record[0] !== self::DATA_RECORD_TYPE) {
                continue;
            }

            $rowNumber++;
            $rows[] = $this->parseDataRecord($record, $rowNumber);
        }

        if ($rows === []) {
            throw new InvalidArgumentException('CSV file contains no data rows.');
        }

        return $rows;
    }

    /**
     * @return array{
     *     transaction_date: \Carbon\Carbon,
     *     description: string,
     *     deposit_amount: int,
     *     withdrawal_amount: int,
     *     balance: ?int,
     *     row_hash: string,
     * }
     */
    private function parseDataRecord(string $record, int $rowNumber): array
    {
        $transactionDate = ZenginDateParser::parse(trim(substr($record, 9, 6)));
        $direction = trim(substr($record, 21, 1));
        $amount = $this->builder->parseUnsignedAmount(trim(substr($record, 24, 12)));
        $remitter = trim(substr($record, 81, 48));
        $summary = trim(substr($record, 159, 20));
        $description = $remitter !== '' ? $remitter : $summary;

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
