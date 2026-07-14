<?php

namespace App\Services\CreditCardCsv;

use App\Enums\CreditCardCsvFormat;
use App\Services\BankCsv\BankCsvEncodingNormalizer;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use App\Services\CreditCardCsv\Adapters\GenericCreditCardCsvAdapter;
use App\Services\CreditCardCsv\Adapters\SaisonCreditCardCsvAdapter;
use App\Services\CreditCardCsv\Contracts\CreditCardCsvFormatAdapter;
use App\Services\CreditCardCsv\Support\CreditCardCsvAiColumnMapper;
use App\Services\CreditCardCsv\Support\CreditCardCsvColumnMatcher;
use App\Services\CreditCardCsv\Support\CreditCardCsvRowSupport;
use InvalidArgumentException;

class CreditCardCsvParser
{
    private GenericCreditCardCsvAdapter $genericAdapter;

    /** @var array<int, CreditCardCsvFormatAdapter> */
    private array $adapters;

    public function __construct(
        private readonly BankCsvEncodingNormalizer $encodingNormalizer,
        BankCsvRowBuilder $rowBuilder,
        private readonly CreditCardCsvAiColumnMapper $aiColumnMapper,
    ) {
        $rowSupport = new CreditCardCsvRowSupport;
        $columnMatcher = new CreditCardCsvColumnMatcher($rowBuilder);

        $this->genericAdapter = new GenericCreditCardCsvAdapter($rowBuilder, $columnMatcher, $rowSupport);
        $this->adapters = [
            new SaisonCreditCardCsvAdapter($rowBuilder),
            $this->genericAdapter,
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

        // 1) Known formats first (Saison / common header names).
        foreach ($this->adapters as $adapter) {
            if ($adapter->matches($lines)) {
                $parsed = $adapter->parse($lines);

                return [
                    'format' => $adapter->format(),
                    'card_name' => $parsed['card_name'],
                    'payment_date' => $parsed['payment_date'],
                    'billing_amount' => $parsed['billing_amount'],
                    'rows' => $parsed['rows'],
                ];
            }
        }

        // 2) Unknown format → AI interprets columns (or rejects non card CSVs).
        $aiMapping = $this->aiColumnMapper->mapColumns($lines);
        $parsed = $this->genericAdapter->parseWithMapping($lines, $aiMapping);

        return [
            'format' => CreditCardCsvFormat::AiMapped,
            'card_name' => $parsed['card_name'],
            'payment_date' => $parsed['payment_date'],
            'billing_amount' => $parsed['billing_amount'],
            'rows' => $parsed['rows'],
        ];
    }
}
