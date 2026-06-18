<?php

namespace App\Services;

use App\Models\Account;
use InvalidArgumentException;

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
        $outputTaxAccount = $this->requireAccount('仮受消費税');

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
        $inputTaxAccount = $this->requireAccount('仮払消費税');

        return [
            ['account_id' => $expenseAccountId, 'debit' => $split['net'], 'credit' => 0],
            ['account_id' => $inputTaxAccount->id, 'debit' => $split['tax'], 'credit' => 0],
            ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $gross],
        ];
    }

    private function requireAccount(string $name): Account
    {
        $account = Account::where('name', $name)->first();

        if ($account === null) {
            throw new InvalidArgumentException("勘定科目「{$name}」が登録されていません。勘定科目設定を確認してください。");
        }

        return $account;
    }
}
