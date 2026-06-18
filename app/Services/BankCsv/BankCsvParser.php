<?php

namespace App\Services\BankCsv;

use App\Enums\BankCsvFormat;
use App\Services\BankCsv\Support\BankCsvRowBuilder;
use InvalidArgumentException;

class BankCsvParser
{
    public function __construct(
        private readonly BankCsvEncodingNormalizer $encodingNormalizer,
        private readonly BankCsvFormatDetector $formatDetector,
        private readonly BankCsvRowBuilder $rowBuilder,
    ) {}

    /**
     * @return array{
     *     format: BankCsvFormat,
     *     rows: array<int, array{
     *         transaction_date: \Carbon\Carbon,
     *         description: string,
     *         deposit_amount: int,
     *         withdrawal_amount: int,
     *         balance: ?int,
     *         row_hash: string,
     *     }>,
     * }
     */
    public function parse(string $csvContent): array
    {
        $csvContent = trim($this->encodingNormalizer->normalize($csvContent));
        if ($csvContent === '') {
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $csvContent);
        if ($lines === false || $lines === []) {
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $adapter = $this->formatDetector->detect($lines);
        $rows = $adapter->parse($lines);

        return [
            'format' => $adapter->format(),
            'rows' => $rows,
        ];
    }

    public function computeRowHash(
        string $date,
        string $description,
        int $depositAmount,
        int $withdrawalAmount,
        ?int $balance,
    ): string {
        return $this->rowBuilder->computeRowHash(
            $date,
            $description,
            $depositAmount,
            $withdrawalAmount,
            $balance,
        );
    }
}
