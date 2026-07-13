<?php

namespace App\Services\CreditCardCsv\Contracts;

use App\Enums\CreditCardCsvFormat;

interface CreditCardCsvFormatAdapter
{
    /**
     * @param  array<int, string>  $lines
     */
    public function matches(array $lines): bool;

    public function format(): CreditCardCsvFormat;

    /**
     * @param  array<int, string>  $lines
     * @return array{
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
    public function parse(array $lines): array;
}
