<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * @param  array{issue_date: string, customer_name: string, notes?: string|null, lines: list<array{description: string, quantity: int, unit_price: int, tax_rate: string}>}  $data
     */
    public function create(Company $company, array $data): Invoice
    {
        return DB::transaction(function () use ($company, $data) {
            $invoice = $company->invoices()->create([
                'invoice_number' => $this->nextNumber($company, $data['issue_date']),
                'issue_date' => $data['issue_date'],
                'customer_name' => $data['customer_name'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceLines($invoice, $data['lines']);

            return $invoice->load('lines');
        });
    }

    /**
     * @param  array{issue_date: string, customer_name: string, notes?: string|null, lines: list<array{description: string, quantity: int, unit_price: int, tax_rate: string}>}  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'issue_date' => $data['issue_date'],
                'customer_name' => $data['customer_name'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceLines($invoice, $data['lines']);

            return $invoice->load('lines');
        });
    }

    public function nextNumber(Company $company, string $issueDate): string
    {
        $year = Carbon::parse($issueDate)->format('Y');

        $latest = Invoice::query()
            ->where('company_id', $company->id)
            ->where('invoice_number', 'like', $year.'%')
            ->orderByRaw('length(invoice_number) desc')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = 1;

        if (is_string($latest) && str_starts_with($latest, $year)) {
            $sequence = ((int) substr($latest, strlen($year))) + 1;
        }

        return $year.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{transaction_date: string, description: string, quantity: int, unit_price: int, tax_rate: string}>  $lines
     */
    private function replaceLines(Invoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $invoice->lines()->create([
                'line_no' => $index + 1,
                'transaction_date' => $line['transaction_date'],
                'description' => $line['description'],
                'quantity' => (int) $line['quantity'],
                'unit_price' => (int) $line['unit_price'],
                'tax_rate' => $line['tax_rate'],
            ]);
        }
    }
}
