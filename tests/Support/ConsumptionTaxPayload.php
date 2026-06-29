<?php

namespace Tests\Support;

trait ConsumptionTaxPayload
{
    /**
     * @return array{consumption_tax_category: string, has_qualified_invoice: bool}
     */
    protected function purchaseTaxPayload(bool $hasQualifiedInvoice = true): array
    {
        return [
            'consumption_tax_category' => 'taxable_purchase_10',
            'has_qualified_invoice' => $hasQualifiedInvoice,
        ];
    }

    /**
     * @return array{consumption_tax_category: string}
     */
    protected function salesTaxPayload(string $category = 'taxable_sales_10'): array
    {
        return [
            'consumption_tax_category' => $category,
        ];
    }

    /**
     * @return array{consumption_tax_category: string}
     */
    protected function transferTaxPayload(): array
    {
        return [
            'consumption_tax_category' => 'out_of_scope',
        ];
    }
}
