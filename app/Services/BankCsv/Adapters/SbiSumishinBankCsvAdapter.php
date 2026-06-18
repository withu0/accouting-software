<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use InvalidArgumentException;

class SbiSumishinBankCsvAdapter implements BankCsvFormatAdapter
{
    private const REQUIRED_HEADERS = ['日付', '内容', '出金金額(円)', '入金金額(円)', '残高(円)'];

    public function __construct(
        private readonly BankCsvRowBuilder $builder,
    ) {}

    public function format(): BankCsvFormat
    {
        return BankCsvFormat::SbiSumishin;
    }

    public function matches(array $lines): bool
    {
        return $this->builder->findHeaderRow($lines, self::REQUIRED_HEADERS) !== null;
    }

    public function parse(array $lines): array
    {
        $headerInfo = $this->builder->findHeaderRow($lines, self::REQUIRED_HEADERS);
        if ($headerInfo === null) {
            throw new InvalidArgumentException('CSV is missing required SBI header columns.');
        }

        $headerIndex = array_flip($headerInfo['headers']);
        $rows = [];

        foreach (array_slice($lines, $headerInfo['index'] + 1) as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $columns = $this->builder->parseCsvLine($line);
            $rowNumber = $headerInfo['index'] + $lineNumber + 2;

            $dateStr = trim($columns[$headerIndex['日付']] ?? '');
            if ($dateStr === '') {
                continue;
            }

            $description = trim($columns[$headerIndex['内容']] ?? '');
            $withdrawalStr = trim($columns[$headerIndex['出金金額(円)']] ?? '');
            $depositStr = trim($columns[$headerIndex['入金金額(円)']] ?? '');
            $balanceStr = trim($columns[$headerIndex['残高(円)']] ?? '');

            $depositAmount = $this->builder->parseAmount($depositStr, $rowNumber, '入金金額(円)');
            $withdrawalAmount = $this->builder->parseAmount($withdrawalStr, $rowNumber, '出金金額(円)');
            $balance = $balanceStr === '' ? null : $this->builder->parseAmount($balanceStr, $rowNumber, '残高(円)');
            $transactionDate = $this->builder->parseDate($dateStr, $rowNumber);

            $rows[] = $this->builder->buildRow(
                $transactionDate,
                $description,
                $depositAmount,
                $withdrawalAmount,
                $balance,
                $rowNumber,
            );
        }

        if ($rows === []) {
            throw new InvalidArgumentException('CSV file contains no data rows.');
        }

        return $rows;
    }
}
