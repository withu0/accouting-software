<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use InvalidArgumentException;

class NativeCsvAdapter implements BankCsvFormatAdapter
{
    private const REQUIRED_HEADERS = ['日付', '摘要', '入金額', '出金額', '残高'];

    /** @var array<string, list<string>> */
    protected const AMOUNT_ALIASES = [
        '入金額' => ['入金金額'],
        '出金額' => ['出金金額'],
    ];

    public function __construct(
        protected readonly BankCsvRowBuilder $builder,
        private readonly BankCsvFormat $format = BankCsvFormat::Native,
    ) {}

    public function format(): BankCsvFormat
    {
        return $this->format;
    }

    public function matches(array $lines): bool
    {
        return $this->findHeader($lines) !== null;
    }

    public function parse(array $lines): array
    {
        $headerInfo = $this->findHeader($lines);
        if ($headerInfo === null) {
            throw new InvalidArgumentException('CSV is missing required header columns.');
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

            if (count($columns) < count(self::REQUIRED_HEADERS)) {
                throw new InvalidArgumentException("Row {$rowNumber} has insufficient columns.");
            }

            $dateStr = trim($columns[$headerIndex['日付']] ?? '');
            if ($dateStr === '') {
                continue;
            }

            $description = trim($columns[$headerIndex['摘要']] ?? '');
            $depositStr = trim($columns[$headerIndex['入金額']] ?? '');
            $withdrawalStr = trim($columns[$headerIndex['出金額']] ?? '');
            $balanceStr = trim($columns[$headerIndex['残高']] ?? '');

            $depositAmount = $this->builder->parseAmount($depositStr, $rowNumber, '入金額');
            $withdrawalAmount = $this->builder->parseAmount($withdrawalStr, $rowNumber, '出金額');
            $balance = $balanceStr === '' ? null : $this->builder->parseAmount($balanceStr, $rowNumber, '残高');
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

    /**
     * @param  array<int, string>  $lines
     * @return array{index: int, headers: array<int, string>}|null
     */
    protected function findHeader(array $lines): ?array
    {
        return $this->builder->findHeaderRow($lines, self::REQUIRED_HEADERS, self::AMOUNT_ALIASES);
    }
}
