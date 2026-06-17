<?php

namespace App\Services;

use App\Models\Account;

class ConsumptionTaxService
{
    public function ratePercent(): int
    {
        return (int) config('consumption_tax.rate_percent');
    }

    /**
     * @return array{net: int, tax: int}
     */
    public function splitInclusive(int $gross): array
    {
        $rate = $this->ratePercent();
        $tax = intdiv($gross * $rate, 100 + $rate);
        $net = $gross - $tax;

        return ['net' => $net, 'tax' => $tax];
    }

    /**
     * @return array<int, array{account_id: int, debit: int, credit: int}>
     */
    public function buildTaxableRevenueLines(int $gross, int $depositAccountId, int $revenueAccountId): array
    {
        $split = $this->splitInclusive($gross);
        $outputTaxAccount = Account::findByName('仮受消費税');

        return [
            ['account_id' => $depositAccountId, 'debit' => $gross, 'credit' => 0],
            ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $split['net']],
            ['account_id' => $outputTaxAccount->id, 'debit' => 0, 'credit' => $split['tax']],
        ];
    }

    /**
     * @return array<int, array{account_id: int, debit: int, credit: int}>
     */
    public function buildTaxableExpenseLines(int $gross, int $expenseAccountId, int $creditAccountId): array
    {
        $split = $this->splitInclusive($gross);
        $inputTaxAccount = Account::findByName('仮払消費税');

        return [
            ['account_id' => $expenseAccountId, 'debit' => $split['net'], 'credit' => 0],
            ['account_id' => $inputTaxAccount->id, 'debit' => $split['tax'], 'credit' => 0],
            ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $gross],
        ];
    }
}
