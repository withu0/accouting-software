<?php

namespace App\Services;

use App\Enums\InvoiceTaxRate;

class InvoiceTaxCalculator
{
    /**
     * 税抜金額を税率ごとに合計し、消費税は税率ごとに切り捨てる。
     *
     * @param  list<array{description?: string, quantity: int, unit_price: int, tax_rate: string}>  $lines
     * @return array{
     *     lines: list<array{description: string, quantity: int, unit_price: int, tax_rate: string, tax_rate_label: string, short_label: string, is_reduced: bool, amount: int}>,
     *     rates: list<array{rate: string, label: string, base: int, tax: int}>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int
     * }
     */
    public function calculate(array $lines): array
    {
        $prepared = [];
        $bases = [];

        foreach ($lines as $line) {
            $rate = InvoiceTaxRate::from((string) $line['tax_rate']);
            $quantity = (int) $line['quantity'];
            $unitPrice = (int) $line['unit_price'];
            $amount = $quantity * $unitPrice;

            $prepared[] = [
                'transaction_date' => (string) ($line['transaction_date'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $rate->value,
                'tax_rate_label' => $rate->label(),
                'short_label' => $rate->shortLabel(),
                'is_reduced' => $rate->isReduced(),
                'amount' => $amount,
            ];

            $bases[$rate->value] = ($bases[$rate->value] ?? 0) + $amount;
        }

        $rates = [];

        foreach ([InvoiceTaxRate::Standard, InvoiceTaxRate::Reduced] as $rate) {
            if (! array_key_exists($rate->value, $bases)) {
                continue;
            }

            $base = $bases[$rate->value];

            $rates[] = [
                'rate' => $rate->value,
                'label' => $rate->label(),
                'base' => $base,
                'tax' => intdiv($base * $rate->percent(), 100),
            ];
        }

        $subtotal = array_sum(array_column($prepared, 'amount'));
        $taxTotal = array_sum(array_column($rates, 'tax'));

        return [
            'lines' => $prepared,
            'rates' => $rates,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
        ];
    }
}
