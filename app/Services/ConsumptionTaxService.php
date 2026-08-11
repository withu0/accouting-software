<?php

namespace App\Services;

use App\Enums\ConsumptionTaxCategory;
use App\Models\Account;
use Carbon\Carbon;
use InvalidArgumentException;

class ConsumptionTaxService
{
    private const DEDUCTION_50_START = '2026-10-01';

    /**
     * @return array{net: int, tax: int, deductible_tax: int, non_deductible_tax: int}
     */
    public function splitInclusive(int $gross, ConsumptionTaxCategory $category): array
    {
        $rate = $category->ratePercent();

        if ($rate === 0) {
            return [
                'net' => $gross,
                'tax' => 0,
                'deductible_tax' => 0,
                'non_deductible_tax' => 0,
            ];
        }

        $tax = intdiv($gross * $rate, 100 + $rate);
        $net = $gross - $tax;
        $deductionPercent = $category->deductionPercent();
        $deductibleTax = intdiv($tax * $deductionPercent, 100);
        $nonDeductibleTax = $tax - $deductibleTax;

        return [
            'net' => $net,
            'tax' => $tax,
            'deductible_tax' => $deductibleTax,
            'non_deductible_tax' => $nonDeductibleTax,
        ];
    }

    public function resolveEffectiveCategory(
        ConsumptionTaxCategory $base,
        ?bool $hasQualifiedInvoice,
        Carbon $entryDate,
    ): ConsumptionTaxCategory {
        if (! $base->isBasePurchase()) {
            return $base;
        }

        if ($hasQualifiedInvoice !== false) {
            return $base;
        }

        $use50Percent = $entryDate->gte(Carbon::parse(self::DEDUCTION_50_START));

        return match ($base) {
            ConsumptionTaxCategory::TaxablePurchase10 => $use50Percent
                ? ConsumptionTaxCategory::TaxablePurchaseDeduction5010
                : ConsumptionTaxCategory::TaxablePurchaseDeduction8010,
            ConsumptionTaxCategory::TaxablePurchase8Reduced => $use50Percent
                ? ConsumptionTaxCategory::TaxablePurchaseDeduction508Reduced
                : ConsumptionTaxCategory::TaxablePurchaseDeduction808Reduced,
            default => $base,
        };
    }

    /**
     * @return array<int, array{account_id: int, debit: int, credit: int, consumption_tax_category?: string|null}>
     */
    public function buildJournalLines(
        ConsumptionTaxCategory $effectiveCategory,
        int $gross,
        int $primaryAccountId,
        int $counterAccountId,
        bool $isRevenue,
        bool $taxExempt = false,
    ): array {
        if ($taxExempt) {
            $effectiveCategory = ConsumptionTaxCategory::OutOfScope;
        }

        if ($isRevenue) {
            return $this->buildRevenueLines($effectiveCategory, $gross, $primaryAccountId, $counterAccountId);
        }

        return $this->buildExpenseLines($effectiveCategory, $gross, $primaryAccountId, $counterAccountId);
    }

    /**
     * @return array<int, array{account_id: int, debit: int, credit: int}>
     */
    public function buildTaxableRevenueLines(
        int $gross,
        int $depositAccountId,
        int $revenueAccountId,
        bool $taxExempt = false,
    ): array {
        return $this->buildJournalLines(
            ConsumptionTaxCategory::TaxableSales10,
            $gross,
            $revenueAccountId,
            $depositAccountId,
            true,
            $taxExempt,
        );
    }

    /**
     * @return array<int, array{account_id: int, debit: int, credit: int}>
     */
    public function buildTaxableExpenseLines(
        int $gross,
        int $expenseAccountId,
        int $creditAccountId,
        bool $taxExempt = false,
    ): array {
        return $this->buildJournalLines(
            ConsumptionTaxCategory::TaxablePurchase10,
            $gross,
            $expenseAccountId,
            $creditAccountId,
            false,
            $taxExempt,
        );
    }

    /**
     * Expand one-sided transfer journal lines (税込) into net + 仮受/仮払 companion lines.
     *
     * @param  list<array{account_id: int, debit: int, credit: int, consumption_tax_category: ConsumptionTaxCategory|string|null, has_qualified_invoice?: bool|null}>  $lines
     * @return list<array{account_id: int, debit: int, credit: int, consumption_tax_category: ConsumptionTaxCategory|null}>
     */
    public function expandTransferLines(array $lines, Carbon $entryDate, bool $taxExempt = false): array
    {
        $expanded = [];

        foreach ($lines as $line) {
            $category = $line['consumption_tax_category'] ?? ConsumptionTaxCategory::OutOfScope;
            if (is_string($category)) {
                $category = ConsumptionTaxCategory::from($category);
            }

            if ($taxExempt) {
                $category = ConsumptionTaxCategory::OutOfScope;
            }

            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);
            $gross = $debit > 0 ? $debit : $credit;
            $isDebit = $debit > 0;

            $hasQualifiedInvoice = array_key_exists('has_qualified_invoice', $line)
                ? $line['has_qualified_invoice']
                : null;

            $effective = $this->resolveEffectiveCategory($category, $hasQualifiedInvoice, $entryDate);
            $split = $this->splitInclusive($gross, $effective);

            if ($split['tax'] === 0) {
                $expanded[] = [
                    'account_id' => (int) $line['account_id'],
                    'debit' => $isDebit ? $gross : 0,
                    'credit' => $isDebit ? 0 : $gross,
                    'consumption_tax_category' => $effective,
                ];

                continue;
            }

            if ($effective->isPurchase()) {
                $primaryAmount = $split['net'] + $split['non_deductible_tax'];
                $expanded[] = [
                    'account_id' => (int) $line['account_id'],
                    'debit' => $isDebit ? $primaryAmount : 0,
                    'credit' => $isDebit ? 0 : $primaryAmount,
                    'consumption_tax_category' => $effective,
                ];

                if ($split['deductible_tax'] > 0) {
                    $inputTaxAccount = $this->requireAccount('仮払消費税');
                    $expanded[] = [
                        'account_id' => $inputTaxAccount->id,
                        'debit' => $isDebit ? $split['deductible_tax'] : 0,
                        'credit' => $isDebit ? 0 : $split['deductible_tax'],
                        'consumption_tax_category' => ConsumptionTaxCategory::OutOfScope,
                    ];
                }

                continue;
            }

            // Sales / output tax
            $expanded[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $isDebit ? $split['net'] : 0,
                'credit' => $isDebit ? 0 : $split['net'],
                'consumption_tax_category' => $effective,
            ];

            $outputTaxAccount = $this->requireAccount('仮受消費税');
            $expanded[] = [
                'account_id' => $outputTaxAccount->id,
                'debit' => $isDebit ? $split['tax'] : 0,
                'credit' => $isDebit ? 0 : $split['tax'],
                'consumption_tax_category' => ConsumptionTaxCategory::OutOfScope,
            ];
        }

        return $expanded;
    }

    /**
     * @return array{gross: int, net: int, tax: int, deductible_tax: int, non_deductible_tax: int, effective_category: string, effective_category_label: string}
     */
    public function summarizeEntry(
        ConsumptionTaxCategory $baseCategory,
        ?bool $hasQualifiedInvoice,
        Carbon $entryDate,
        int $gross,
    ): array {
        $effective = $this->resolveEffectiveCategory($baseCategory, $hasQualifiedInvoice, $entryDate);
        $split = $this->splitInclusive($gross, $effective);

        return [
            'gross' => $gross,
            'net' => $split['net'],
            'tax' => $split['tax'],
            'deductible_tax' => $split['deductible_tax'],
            'non_deductible_tax' => $split['non_deductible_tax'],
            'effective_category' => $effective->value,
            'effective_category_label' => $effective->label(),
        ];
    }

    /**
     * @return array<int, array{account_id: int, debit: int, credit: int}>
     */
    private function buildRevenueLines(
        ConsumptionTaxCategory $category,
        int $gross,
        int $revenueAccountId,
        int $depositAccountId,
    ): array {
        $split = $this->splitInclusive($gross, $category);

        if ($category === ConsumptionTaxCategory::ExportExempt
            || $category === ConsumptionTaxCategory::NonTaxable
            || $category === ConsumptionTaxCategory::OutOfScope) {
            return [
                ['account_id' => $depositAccountId, 'debit' => $gross, 'credit' => 0],
                ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $gross],
            ];
        }

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
    private function buildExpenseLines(
        ConsumptionTaxCategory $category,
        int $gross,
        int $expenseAccountId,
        int $creditAccountId,
    ): array {
        $split = $this->splitInclusive($gross, $category);

        if ($category === ConsumptionTaxCategory::NonTaxable
            || $category === ConsumptionTaxCategory::OutOfScope) {
            return [
                ['account_id' => $expenseAccountId, 'debit' => $gross, 'credit' => 0],
                ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $gross],
            ];
        }

        $expenseDebit = $split['net'] + $split['non_deductible_tax'];
        $lines = [
            ['account_id' => $expenseAccountId, 'debit' => $expenseDebit, 'credit' => 0],
        ];

        if ($split['deductible_tax'] > 0) {
            $inputTaxAccount = $this->requireAccount('仮払消費税');
            $lines[] = ['account_id' => $inputTaxAccount->id, 'debit' => $split['deductible_tax'], 'credit' => 0];
        }

        $lines[] = ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $gross];

        return $lines;
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
