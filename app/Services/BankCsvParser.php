<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class BankCsvParser
{
    private const REQUIRED_HEADERS = ['日付', '摘要', '入金額', '出金額', '残高'];

    /**
     * @return array<int, array{
     *     transaction_date: Carbon,
     *     description: string,
     *     deposit_amount: int,
     *     withdrawal_amount: int,
     *     balance: ?int,
     *     row_hash: string,
     * }>
     */
    public function parse(string $csvContent): array
    {
        $csvContent = trim($csvContent);
        if ($csvContent === '') {
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $csvContent);
        if ($lines === false || count($lines) < 2) {
            throw new InvalidArgumentException('CSV file must contain a header row and at least one data row.');
        }

        $headerLine = array_shift($lines);
        $headers = $this->parseCsvLine($headerLine);
        $this->validateHeaders($headers);

        $headerIndex = array_flip($headers);
        $rows = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $columns = $this->parseCsvLine($line);
            $rowNumber = $lineNumber + 2;

            if (count($columns) < count(self::REQUIRED_HEADERS)) {
                throw new InvalidArgumentException("Row {$rowNumber} has insufficient columns.");
            }

            $dateStr = trim($columns[$headerIndex['日付']]);
            $description = trim($columns[$headerIndex['摘要']]);
            $depositStr = trim($columns[$headerIndex['入金額']]);
            $withdrawalStr = trim($columns[$headerIndex['出金額']]);
            $balanceStr = trim($columns[$headerIndex['残高']]);

            if ($dateStr === '' || $description === '') {
                throw new InvalidArgumentException("Row {$rowNumber} is missing date or description.");
            }

            $depositAmount = $this->parseAmount($depositStr, $rowNumber, '入金額');
            $withdrawalAmount = $this->parseAmount($withdrawalStr, $rowNumber, '出金額');
            $balance = $balanceStr === '' ? null : $this->parseAmount($balanceStr, $rowNumber, '残高');

            if (($depositAmount > 0 && $withdrawalAmount > 0) || ($depositAmount === 0 && $withdrawalAmount === 0)) {
                throw new InvalidArgumentException("Row {$rowNumber} must have exactly one of deposit or withdrawal amount greater than zero.");
            }

            try {
                $transactionDate = Carbon::parse($dateStr)->startOfDay();
            } catch (\Exception) {
                throw new InvalidArgumentException("Row {$rowNumber} has an invalid date: {$dateStr}");
            }

            $rowHash = $this->computeRowHash(
                $transactionDate->format('Y-m-d'),
                $description,
                $depositAmount,
                $withdrawalAmount,
                $balance,
            );

            $rows[] = [
                'transaction_date' => $transactionDate,
                'description' => $description,
                'deposit_amount' => $depositAmount,
                'withdrawal_amount' => $withdrawalAmount,
                'balance' => $balance,
                'row_hash' => $rowHash,
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('CSV file contains no data rows.');
        }

        return $rows;
    }

    public function computeRowHash(
        string $date,
        string $description,
        int $depositAmount,
        int $withdrawalAmount,
        ?int $balance,
    ): string {
        $balanceStr = $balance === null ? '' : (string) $balance;

        return hash('sha256', "{$date}|{$description}|{$depositAmount}|{$withdrawalAmount}|{$balanceStr}");
    }

    /**
     * @return array<int, string>
     */
    private function parseCsvLine(string $line): array
    {
        return str_getcsv($line);
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function validateHeaders(array $headers): void
    {
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                throw new InvalidArgumentException("CSV is missing required header: {$required}");
            }
        }
    }

    private function parseAmount(string $value, int $rowNumber, string $column): int
    {
        if ($value === '') {
            return 0;
        }

        if (! preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException("Row {$rowNumber} has an invalid {$column} amount: {$value}");
        }

        return (int) $value;
    }
}
