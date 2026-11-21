<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\JapaneseDateFormatter;

class QualifiedInvoiceDocument
{
    public function __construct(private readonly InvoiceTaxCalculator $calculator) {}

    /**
     * @return array{
     *     lines: list<array{description: string, quantity: int, unit_price: int, tax_rate: string, tax_rate_label: string, short_label: string, is_reduced: bool, amount: int}>,
     *     rates: list<array{rate: string, label: string, base: int, tax: int}>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int
     * }
     */
    public function summary(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');

        return $this->calculator->calculate(
            $invoice->lines
                ->map(fn (InvoiceLine $line) => [
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate->value,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{
     *     companyName: string,
     *     address: string|null,
     *     representativeName: string|null,
     *     registrationNumber: string|null,
     *     invoiceNumber: string,
     *     issueDate: string,
     *     customerName: string,
     *     notes: string|null,
     *     summary: array{
     *         lines: list<array{description: string, quantity: int, unit_price: int, tax_rate: string, tax_rate_label: string, short_label: string, is_reduced: bool, amount: int}>,
     *         rates: list<array{rate: string, label: string, base: int, tax: int}>,
     *         subtotal: int,
     *         tax_total: int,
     *         total: int
     *     }
     * }
     */
    public function data(Invoice $invoice, Company $company): array
    {
        return [
            'companyName' => $company->name !== null && $company->name !== '' ? $company->name : '（社名未設定）',
            'address' => $company->address,
            'representativeName' => $company->representative_name,
            'registrationNumber' => $company->invoice_registration_number,
            'invoiceNumber' => $invoice->invoice_number,
            'issueDate' => JapaneseDateFormatter::western($invoice->issue_date),
            'customerName' => $invoice->customer_name,
            'notes' => $invoice->notes,
            'summary' => $this->summary($invoice),
        ];
    }
}
