<?php

namespace App\Services\BankCsv\Contracts;

use App\Enums\BankCsvFormat;

interface BankCsvFormatAdapter
{
    public function format(): BankCsvFormat;

    /**
     * @param  array<int, string>  $lines
     */
    public function matches(array $lines): bool;

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array{
     *     transaction_date: \Carbon\Carbon,
     *     description: string,
     *     deposit_amount: int,
     *     withdrawal_amount: int,
     *     balance: ?int,
     *     row_hash: string,
     * }>
     */
    public function parse(array $lines): array;
}
