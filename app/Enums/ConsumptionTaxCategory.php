<?php

namespace App\Enums;

enum ConsumptionTaxCategory: string
{
    case TaxableSales10 = 'taxable_sales_10';
    case ExportExempt = 'export_exempt';
    case TaxablePurchase10 = 'taxable_purchase_10';
    case TaxablePurchase8Reduced = 'taxable_purchase_8_reduced';
    case TaxablePurchaseDeduction8010 = 'taxable_purchase_deduction_80_10';
    case TaxablePurchaseDeduction808Reduced = 'taxable_purchase_deduction_80_8_reduced';
    case TaxablePurchaseDeduction5010 = 'taxable_purchase_deduction_50_10';
    case TaxablePurchaseDeduction508Reduced = 'taxable_purchase_deduction_50_8_reduced';
    case NonTaxable = 'non_taxable';
    case OutOfScope = 'out_of_scope';

    public function label(): string
    {
        return match ($this) {
            self::TaxableSales10 => '課対売上10%',
            self::ExportExempt => '輸出免税',
            self::TaxablePurchase10 => '課対仕入10%',
            self::TaxablePurchase8Reduced => '課対仕入8%（軽）',
            self::TaxablePurchaseDeduction8010 => '課対仕入（控80）10%',
            self::TaxablePurchaseDeduction808Reduced => '課対仕入（控80）8%（軽）',
            self::TaxablePurchaseDeduction5010 => '課対仕入（控50）10%',
            self::TaxablePurchaseDeduction508Reduced => '課対仕入（控50）8%（軽）',
            self::NonTaxable => '非課税',
            self::OutOfScope => '対象外',
        };
    }

    public function isPurchase(): bool
    {
        return in_array($this, [
            self::TaxablePurchase10,
            self::TaxablePurchase8Reduced,
            self::TaxablePurchaseDeduction8010,
            self::TaxablePurchaseDeduction808Reduced,
            self::TaxablePurchaseDeduction5010,
            self::TaxablePurchaseDeduction508Reduced,
        ], true);
    }

    public function isSales(): bool
    {
        return in_array($this, [
            self::TaxableSales10,
            self::ExportExempt,
            self::NonTaxable,
        ], true);
    }

    public function isUserSelectable(): bool
    {
        return in_array($this, [
            self::TaxableSales10,
            self::ExportExempt,
            self::TaxablePurchase10,
            self::TaxablePurchase8Reduced,
            self::NonTaxable,
            self::OutOfScope,
        ], true);
    }

    public function isBasePurchase(): bool
    {
        return in_array($this, [
            self::TaxablePurchase10,
            self::TaxablePurchase8Reduced,
        ], true);
    }

    public function ratePercent(): int
    {
        return match ($this) {
            self::TaxableSales10,
            self::TaxablePurchase10,
            self::TaxablePurchaseDeduction8010,
            self::TaxablePurchaseDeduction5010 => 10,
            self::TaxablePurchase8Reduced,
            self::TaxablePurchaseDeduction808Reduced,
            self::TaxablePurchaseDeduction508Reduced => 8,
            self::ExportExempt,
            self::NonTaxable,
            self::OutOfScope => 0,
        };
    }

    public function deductionPercent(): int
    {
        return match ($this) {
            self::TaxablePurchase10,
            self::TaxablePurchase8Reduced => 100,
            self::TaxablePurchaseDeduction8010,
            self::TaxablePurchaseDeduction808Reduced => 80,
            self::TaxablePurchaseDeduction5010,
            self::TaxablePurchaseDeduction508Reduced => 50,
            default => 0,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForSales(): array
    {
        return self::toOptions([
            self::TaxableSales10,
            self::ExportExempt,
            self::NonTaxable,
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForPurchases(): array
    {
        return self::toOptions([
            self::TaxablePurchase10,
            self::TaxablePurchase8Reduced,
            self::NonTaxable,
            self::OutOfScope,
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForTransfer(): array
    {
        return self::toOptions([
            self::OutOfScope,
            self::NonTaxable,
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForAccountType(AccountType $type): array
    {
        return match ($type) {
            AccountType::Revenue => self::optionsForSales(),
            AccountType::Expense => self::optionsForPurchases(),
            default => self::toOptions([self::OutOfScope]),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function allOptions(): array
    {
        return self::toOptions(self::cases());
    }

    /**
     * @param  list<self>  $cases
     * @return list<array{value: string, label: string}>
     */
    private static function toOptions(array $cases): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
