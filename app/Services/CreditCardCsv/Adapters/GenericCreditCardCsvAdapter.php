<?php

namespace App\Services\CreditCardCsv\Adapters;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\Contracts\CreditCardCsvFormatAdapter;
use App\Services\CreditCardCsv\Support\CreditCardCsvColumnMatcher;
use App\Services\CreditCardCsv\Support\CreditCardCsvRowSupport;
use Carbon\Carbon;
use InvalidArgumentException;

class GenericCreditCardCsvAdapter implements CreditCardCsvFormatAdapter
{
    public function __construct(
        private readonly BankCsvRowBuilder $rowBuilder,
        private readonly CreditCardCsvColumnMatcher $columnMatcher,
        private readonly CreditCardCsvRowSupport $rowSupport,
    ) {}

    /**
     * @param  array<int, string>  $lines
     */
    public function matches(array $lines): bool
    {
        return $this->columnMatcher->findHeaderMapping($lines) !== null;
    }

    public function format(): CreditCardCsvFormat
    {
        return CreditCardCsvFormat::Generic;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{
     *     card_name: ?string,
     *     payment_date: ?Carbon,
     *     billing_amount: ?int,
     *     rows: array<int, array{
     *         transaction_date: Carbon,
     *         description: string,
     *         amount: int,
     *         row_hash: string,
     *     }>,
     * }
     */
    public function parse(array $lines): array
    {
        $headerMapping = $this->columnMatcher->findHeaderMapping($lines);
        if ($headerMapping === null) {
            throw new InvalidArgumentException('クレジットカード明細CSVのヘッダー行が見つかりません。');
        }

        return $this->parseWithMapping($lines, $headerMapping);
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array{
     *     index: int,
     *     headers: array<int, string>,
     *     date_index: int,
     *     description_index: int,
     *     amount_index: int,
     * }  $headerMapping
     * @return array{
     *     card_name: ?string,
     *     payment_date: ?Carbon,
     *     billing_amount: ?int,
     *     rows: array<int, array{
     *         transaction_date: Carbon,
     *         description: string,
     *         amount: int,
     *         row_hash: string,
     *     }>,
     * }
     */
    public function parseWithMapping(array $lines, array $headerMapping): array
    {
        $metadata = $this->parseMetadata($lines, $headerMapping['index']);

        $dateIndex = $headerMapping['date_index'];
        $descriptionIndex = $headerMapping['description_index'];
        $amountIndex = $headerMapping['amount_index'];

        $rows = [];
        $rowNumber = 0;
        /** @var array<string, int> $occurrenceCounts */
        $occurrenceCounts = [];

        for ($i = $headerMapping['index'] + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $columns = $this->rowBuilder->parseCsvLine($line);
            $dateValue = trim($columns[$dateIndex] ?? '');
            $description = trim($columns[$descriptionIndex] ?? '');
            $amountValue = trim($columns[$amountIndex] ?? '');

            if ($this->rowSupport->shouldSkipRow($dateValue, $description)) {
                continue;
            }

            $rowNumber++;
            $transactionDate = $this->rowBuilder->parseDate($dateValue, $rowNumber);
            $amount = $this->rowBuilder->parseAmount($amountValue, $rowNumber, '金額', allowSigned: true);

            // Skip payment settlements, refunds, and other non-charge rows.
            if ($amount <= 0) {
                continue;
            }

            if ($description === '') {
                throw new InvalidArgumentException("Row {$rowNumber} is missing merchant name.");
            }

            $dateKey = $transactionDate->format('Y-m-d');
            $occurrenceKey = "{$dateKey}|{$description}|{$amount}";
            $occurrence = $occurrenceCounts[$occurrenceKey] ?? 0;
            $occurrenceCounts[$occurrenceKey] = $occurrence + 1;

            $rows[] = [
                'transaction_date' => $transactionDate,
                'description' => $description,
                'amount' => $amount,
                'row_hash' => $this->rowSupport->computeRowHash(
                    $dateKey,
                    $description,
                    $amount,
                    $occurrence,
                ),
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('CSVに取引データが含まれていません。');
        }

        return array_merge($metadata, ['rows' => $rows]);
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{card_name: ?string, payment_date: ?Carbon, billing_amount: ?int}
     */
    private function parseMetadata(array $lines, int $headerIndex): array
    {
        $cardName = null;
        $paymentDate = null;
        $billingAmount = null;

        for ($i = 0; $i < $headerIndex; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $columns = $this->rowBuilder->parseCsvLine($line);
            $key = trim($columns[0] ?? '');
            $value = trim($columns[1] ?? '');

            if (in_array($key, ['カード名称', 'カード名', 'カード'], true) && $value !== '') {
                $cardName = $value;
            } elseif (in_array($key, ['お支払日', '支払日', 'ご請求日'], true) && $value !== '') {
                $paymentDate = $this->rowBuilder->parseDate($value, 0);
            } elseif (in_array($key, ['今回ご請求額', '請求額', 'ご請求金額'], true) && $value !== '') {
                $billingAmount = $this->rowBuilder->parseAmount($value, 0, '請求額');
            }
        }

        return [
            'card_name' => $cardName,
            'payment_date' => $paymentDate,
            'billing_amount' => $billingAmount,
        ];
    }
}
