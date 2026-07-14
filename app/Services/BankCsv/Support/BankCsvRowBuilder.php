<?php

namespace App\Services\BankCsv\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class BankCsvRowBuilder
{
    /**
     * @return array<int, string>
     */
    public function parseCsvLine(string $line): array
    {
        return str_getcsv($line);
    }

    public function parseDate(string $value, int $rowNumber): Carbon
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("Row {$rowNumber} is missing date.");
        }

        if (preg_match('/^\d{6}$/', $value)) {
            return ZenginDateParser::parse($value);
        }

        // US-style M/D/YYYY (common in Excel-exported card CSVs)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches) === 1) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return Carbon::create($year, $month, $day)->startOfDay();
            }
        }

        // Japanese / ISO style YYYY/MM/DD or YYYY-MM-DD
        $normalized = str_replace(['年', '月', '日', '/'], ['-', '-', '', '-'], $value);
        $normalized = preg_replace('/-+/', '-', trim($normalized, '-')) ?? $normalized;

        try {
            return Carbon::parse($normalized)->startOfDay();
        } catch (\Exception) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Exception) {
                throw new InvalidArgumentException("Row {$rowNumber} has an invalid date: {$value}");
            }
        }
    }

    public function parseAmount(string $value, int $rowNumber, string $column, bool $allowSigned = false): int
    {
        $value = trim(str_replace(',', '', $value));
        if ($value === '') {
            return 0;
        }

        if ($allowSigned) {
            if (! preg_match('/^-?\d+$/', $value)) {
                throw new InvalidArgumentException("Row {$rowNumber} has an invalid {$column} amount: {$value}");
            }

            return (int) $value;
        }

        if (! preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException("Row {$rowNumber} has an invalid {$column} amount: {$value}");
        }

        return (int) $value;
    }

    public function parseUnsignedAmount(string $value): int
    {
        $value = trim(str_replace(',', '', $value));

        return $value === '' ? 0 : (int) $value;
    }

    /**
     * @return array{
     *     transaction_date: Carbon,
     *     description: string,
     *     deposit_amount: int,
     *     withdrawal_amount: int,
     *     balance: ?int,
     *     row_hash: string,
     * }
     */
    public function buildRow(
        Carbon $transactionDate,
        string $description,
        int $depositAmount,
        int $withdrawalAmount,
        ?int $balance,
        int $rowNumber,
    ): array {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidArgumentException("Row {$rowNumber} is missing description.");
        }

        if (($depositAmount > 0 && $withdrawalAmount > 0) || ($depositAmount === 0 && $withdrawalAmount === 0)) {
            throw new InvalidArgumentException("Row {$rowNumber} must have exactly one of deposit or withdrawal amount greater than zero.");
        }

        $rowHash = $this->computeRowHash(
            $transactionDate->format('Y-m-d'),
            $description,
            $depositAmount,
            $withdrawalAmount,
            $balance,
        );

        return [
            'transaction_date' => $transactionDate,
            'description' => $description,
            'deposit_amount' => $depositAmount,
            'withdrawal_amount' => $withdrawalAmount,
            'balance' => $balance,
            'row_hash' => $rowHash,
        ];
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
     * @param  array<int, string>  $headers
     */
    public function hasHeaders(array $headers, array $required): bool
    {
        foreach ($required as $header) {
            if (! in_array($header, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{index: int, headers: array<int, string>}|null
     */
    public function findHeaderRow(array $lines, array $requiredHeaders): ?array
    {
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $headers = $this->parseCsvLine($line);
            if ($this->hasHeaders($headers, $requiredHeaders)) {
                return ['index' => $index, 'headers' => $headers];
            }
        }

        return null;
    }
}
