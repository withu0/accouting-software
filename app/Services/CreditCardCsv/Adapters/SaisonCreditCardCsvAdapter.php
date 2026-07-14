<?php

namespace App\Services\CreditCardCsv\Adapters;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\Contracts\CreditCardCsvFormatAdapter;
use Carbon\Carbon;
use InvalidArgumentException;

class SaisonCreditCardCsvAdapter implements CreditCardCsvFormatAdapter
{
    private const REQUIRED_HEADERS = ['利用日', 'ご利用店名及び商品名', '利用金額'];

    public function __construct(
        private readonly BankCsvRowBuilder $rowBuilder,
    ) {}

    /**
     * @param  array<int, string>  $lines
     */
    public function matches(array $lines): bool
    {
        return $this->rowBuilder->findHeaderRow($lines, self::REQUIRED_HEADERS) !== null;
    }

    public function format(): CreditCardCsvFormat
    {
        return CreditCardCsvFormat::Saison;
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
        $metadata = $this->parseMetadata($lines);

        $headerInfo = $this->rowBuilder->findHeaderRow($lines, self::REQUIRED_HEADERS);
        if ($headerInfo === null) {
            throw new InvalidArgumentException('セゾンカードCSVのヘッダー行が見つかりません。');
        }

        $headers = $headerInfo['headers'];
        $dateIndex = array_search('利用日', $headers, true);
        $descriptionIndex = array_search('ご利用店名及び商品名', $headers, true);
        $amountIndex = array_search('利用金額', $headers, true);

        $rows = [];
        $rowNumber = 0;

        for ($i = $headerInfo['index'] + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $columns = $this->rowBuilder->parseCsvLine($line);
            $dateValue = trim($columns[$dateIndex] ?? '');
            $description = trim($columns[$descriptionIndex] ?? '');
            $amountValue = trim($columns[$amountIndex] ?? '');

            if ($this->shouldSkipRow($dateValue, $description)) {
                continue;
            }

            $rowNumber++;
            $transactionDate = $this->rowBuilder->parseDate($dateValue, $rowNumber);
            $amount = $this->rowBuilder->parseAmount($amountValue, $rowNumber, '利用金額', allowSigned: true);

            // Skip payment settlements, refunds, and other non-charge rows.
            if ($amount <= 0) {
                continue;
            }

            if ($description === '') {
                throw new InvalidArgumentException("Row {$rowNumber} is missing merchant name.");
            }

            $rows[] = [
                'transaction_date' => $transactionDate,
                'description' => $description,
                'amount' => $amount,
                'row_hash' => $this->computeRowHash(
                    $transactionDate->format('Y-m-d'),
                    $description,
                    $amount,
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
    private function parseMetadata(array $lines): array
    {
        $cardName = null;
        $paymentDate = null;
        $billingAmount = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $columns = $this->rowBuilder->parseCsvLine($line);
            $key = trim($columns[0] ?? '');
            $value = trim($columns[1] ?? '');

            if ($key === 'カード名称' && $value !== '') {
                $cardName = $value;
            } elseif ($key === 'お支払日' && $value !== '') {
                $paymentDate = $this->rowBuilder->parseDate($value, 0);
            } elseif ($key === '今回ご請求額' && $value !== '') {
                $billingAmount = $this->rowBuilder->parseAmount($value, 0, '今回ご請求額');
            }

            if ($key === '利用日') {
                break;
            }
        }

        return [
            'card_name' => $cardName,
            'payment_date' => $paymentDate,
            'billing_amount' => $billingAmount,
        ];
    }

    private function shouldSkipRow(string $dateValue, string $description): bool
    {
        if ($dateValue === '') {
            return true;
        }

        if (str_starts_with($description, 'ご利用者名:')) {
            return true;
        }

        if (str_starts_with($description, '【小計】') || str_starts_with($description, '【合計】')) {
            return true;
        }

        if (str_starts_with($dateValue, '【小計】') || str_starts_with($dateValue, '【合計】')) {
            return true;
        }

        return false;
    }

    public function computeRowHash(string $date, string $description, int $amount): string
    {
        return hash('sha256', "{$date}|{$description}|{$amount}");
    }
}
