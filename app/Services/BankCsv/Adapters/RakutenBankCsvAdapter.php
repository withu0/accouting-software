<?php

namespace App\Services\BankCsv\Adapters;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Contracts\BankCsvFormatAdapter;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use InvalidArgumentException;

class RakutenBankCsvAdapter implements BankCsvFormatAdapter
{
    private const REQUIRED_HEADERS = ['取引日', '入出金(円)', '残高(円)', '入出金先内容'];

    public function __construct(
        private readonly BankCsvRowBuilder $builder,
    ) {}

    public function format(): BankCsvFormat
    {
        return BankCsvFormat::Rakuten;
    }

    public function matches(array $lines): bool
    {
        return $this->builder->findHeaderRow($lines, self::REQUIRED_HEADERS) !== null;
    }

    public function parse(array $lines): array
    {
        $headerInfo = $this->builder->findHeaderRow($lines, self::REQUIRED_HEADERS);
        if ($headerInfo === null) {
            throw new InvalidArgumentException('CSV is missing required Rakuten header columns.');
        }

        $headerIndex = array_flip($headerInfo['headers']);
        $rows = [];
        $previousBalance = null;

        foreach (array_slice($lines, $headerInfo['index'] + 1) as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $columns = $this->builder->parseCsvLine($line);
            $rowNumber = $headerInfo['index'] + $lineNumber + 2;

            $dateStr = trim($columns[$headerIndex['取引日']] ?? '');
            if ($dateStr === '') {
                continue;
            }

            $signedAmount = $this->builder->parseAmount(
                trim($columns[$headerIndex['入出金(円)']] ?? ''),
                $rowNumber,
                '入出金(円)',
                allowSigned: true,
            );
            $balanceStr = trim($columns[$headerIndex['残高(円)']] ?? '');
            $balance = $balanceStr === '' ? null : $this->builder->parseAmount($balanceStr, $rowNumber, '残高(円)');
            $description = trim($columns[$headerIndex['入出金先内容']] ?? '');

            [$depositAmount, $withdrawalAmount] = $this->resolveAmounts($signedAmount, $balance, $previousBalance);
            $previousBalance = $balance;

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
     * @return array{0: int, 1: int}
     */
    private function resolveAmounts(int $signedAmount, ?int $balance, ?int $previousBalance): array
    {
        if ($signedAmount > 0) {
            return [$signedAmount, 0];
        }

        if ($signedAmount < 0) {
            return [0, abs($signedAmount)];
        }

        if ($balance !== null && $previousBalance !== null) {
            if ($balance > $previousBalance) {
                return [$balance - $previousBalance, 0];
            }

            if ($balance < $previousBalance) {
                return [0, $previousBalance - $balance];
            }
        }

        throw new InvalidArgumentException('Unable to determine deposit or withdrawal amount for Rakuten row.');
    }
}
