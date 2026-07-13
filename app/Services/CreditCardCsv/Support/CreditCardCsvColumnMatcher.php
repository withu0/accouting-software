<?php

namespace App\Services\CreditCardCsv\Support;

use App\Services\BankCsv\Support\BankCsvRowBuilder;

class CreditCardCsvColumnMatcher
{
    /** @var array<int, string> */
    private const DATE_HEADERS = [
        '利用日',
        'ご利用日',
        '利用年月日',
        '決済日',
        '取引日',
        '日付',
        'ご利用年月日',
    ];

    /** @var array<int, string> */
    private const DESCRIPTION_HEADERS = [
        'ご利用店名及び商品名',
        'ご利用店名・商品名',
        'ご利用店名',
        '利用店名',
        '加盟店名',
        'ご利用内容',
        '利用内容',
        '店名',
        '摘要',
        '内容',
    ];

    /** @var array<int, string> */
    private const AMOUNT_HEADERS = [
        '利用金額',
        'ご利用金額',
        '利用代金',
        '支払金額',
        'ご請求金額',
        '金額',
    ];

    public function __construct(
        private readonly BankCsvRowBuilder $rowBuilder,
    ) {}

    /**
     * @param  array<int, string>  $lines
     * @return array{
     *     index: int,
     *     headers: array<int, string>,
     *     date_index: int,
     *     description_index: int,
     *     amount_index: int,
     * }|null
     */
    public function findHeaderMapping(array $lines): ?array
    {
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $headers = $this->rowBuilder->parseCsvLine($line);
            $dateIndex = $this->findColumnIndex($headers, self::DATE_HEADERS);
            $descriptionIndex = $this->findColumnIndex($headers, self::DESCRIPTION_HEADERS);
            $amountIndex = $this->findColumnIndex($headers, self::AMOUNT_HEADERS);

            if ($dateIndex === null || $descriptionIndex === null || $amountIndex === null) {
                continue;
            }

            if (count(array_unique([$dateIndex, $descriptionIndex, $amountIndex])) < 3) {
                continue;
            }

            return [
                'index' => $index,
                'headers' => $headers,
                'date_index' => $dateIndex,
                'description_index' => $descriptionIndex,
                'amount_index' => $amountIndex,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function findColumnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
