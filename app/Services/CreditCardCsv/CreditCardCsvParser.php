<?php

namespace App\Services\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\Adapters\SaisonCreditCardCsvAdapter;
use App\Services\CreditCardCsv\Contracts\CreditCardCsvFormatAdapter;
use InvalidArgumentException;

class CreditCardCsvParser
{
    /** @var array<int, CreditCardCsvFormatAdapter> */
    private array $adapters;

    public function __construct(
        private readonly BankCsvEncodingNormalizer $encodingNormalizer,
        BankCsvRowBuilder $rowBuilder,
    ) {
        $this->adapters = [
            new SaisonCreditCardCsvAdapter($rowBuilder),
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
            '対応していないCSV形式です。セゾンカードの明細CSVをアップロードしてください。'
        );
    }
}
