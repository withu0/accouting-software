<?php

namespace App\Services\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\Adapters\GenericCreditCardCsvAdapter;
use App\Services\CreditCardCsv\Adapters\SaisonCreditCardCsvAdapter;
use App\Services\CreditCardCsv\Contracts\CreditCardCsvFormatAdapter;
use App\Services\CreditCardCsv\Support\CreditCardCsvColumnMatcher;
use App\Services\CreditCardCsv\Support\CreditCardCsvRowSupport;
use InvalidArgumentException;

class CreditCardCsvParser
{
    /** @var array<int, CreditCardCsvFormatAdapter> */
    private array $adapters;

    public function __construct(
        private readonly BankCsvEncodingNormalizer $encodingNormalizer,
        BankCsvRowBuilder $rowBuilder,
    ) {
        $rowSupport = new CreditCardCsvRowSupport;
        $columnMatcher = new CreditCardCsvColumnMatcher($rowBuilder);

        $this->adapters = [
            new SaisonCreditCardCsvAdapter($rowBuilder),
            new GenericCreditCardCsvAdapter($rowBuilder, $columnMatcher, $rowSupport),
        ];
    }

    /**
     * @return array{
     *     format: CreditCardCsvFormat,
     *     card_name: ?string,
     *     payment_date: ?\Carbon\Carbon,
     *     billing_amount: ?int,
     *     rows: array<int, array{
     *         transaction_date: \Carbon\Carbon,
     *         description: string,
     *         amount: int,
     *         row_hash: string,
     *     }>,
     * }
     */
    public function parse(string $csvContent): array
    {
        $csvContent = trim($this->encodingNormalizer->normalize($csvContent));
        if ($csvContent === '') {
            throw new InvalidArgumentException('CSVファイルが空です。');
        }

        $lines = preg_split('/\r\n|\r|\n/', $csvContent);
        if ($lines === false || $lines === []) {
            throw new InvalidArgumentException('CSVファイルが空です。');
        }

        $adapter = $this->detectAdapter($lines);
        $parsed = $adapter->parse($lines);

        return [
            'format' => $adapter->format(),
            'card_name' => $parsed['card_name'],
            'payment_date' => $parsed['payment_date'],
            'billing_amount' => $parsed['billing_amount'],
            'rows' => $parsed['rows'],
        ];
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function detectAdapter(array $lines): CreditCardCsvFormatAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->matches($lines)) {
                return $adapter;
            }
        }

        throw new InvalidArgumentException(
            '対応していないCSV形式です。利用日・店名・金額の列を含むクレジットカード明細CSVをアップロードしてください。'
        );
    }
}
