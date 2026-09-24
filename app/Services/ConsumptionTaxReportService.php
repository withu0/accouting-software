<?php

namespace App\Services;

use App\Enums\ConsumptionTaxCategory;
use App\Enums\ConsumptionTaxMethod;
use App\Enums\TaxEntityStatus;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Carbon\Carbon;

class ConsumptionTaxReportService
{
    public function __construct(
        private readonly ConsumptionTaxService $consumptionTaxService,
    ) {}

    /**
     * @return array{
     *     company_name: string,
     *     fiscal_period: string,
     *     tax_entity_status: string,
     *     tax_entity_status_label: string,
     *     is_tax_exempt: bool,
     *     tax_method: string,
     *     simplified_industry: string|null,
     *     rows: list<array{
     *         category: string,
     *         category_label: string,
     *         transaction_count: int,
     *         gross_total: int,
     *         net_total: int,
     *         tax_total: int,
     *         deductible_tax_total: int
     *     }>,
     *     summary: array{
     *         output_tax_total: int,
     *         input_tax_total: int,
     *         estimated_tax_payable: int
     *     }
     * }
     */
    public function aggregate(Company $company, FiscalYear $fiscalYear): array
    {
        $isTaxExempt = $company->isTaxExempt();
        $taxEntityStatus = $company->tax_entity_status ?? TaxEntityStatus::Taxable;

        $entries = $company->journalEntries()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where(function ($query) {
                $query->whereNotNull('consumption_tax_category')
                    ->orWhereHas('lines', fn ($lines) => $lines->whereNotNull('consumption_tax_category'));
            })
            ->with(['lines.account'])
            ->orderBy('entry_date')
            ->get();

        /** @var array<string, array{category: string, category_label: string, transaction_count: int, gross_total: int, net_total: int, tax_total: int, deductible_tax_total: int}> $aggregated */
        $aggregated = [];

        foreach ($entries as $entry) {
            if ($isTaxExempt) {
                $gross = $this->resolveGrossAmount($entry);
                if ($gross > 0) {
                    $this->aggregateAmount(
                        $aggregated,
                        ConsumptionTaxCategory::OutOfScope,
                        null,
                        Carbon::parse($entry->entry_date),
                        $gross,
                        true,
                    );
                }

                continue;
            }

            if ($this->hasLineLevelTax($entry)) {
                $this->aggregateLineLevelEntry($entry, $aggregated, false);

                continue;
            }

            $baseCategory = $entry->consumption_tax_category;
            if ($baseCategory === null) {
                continue;
            }

            $gross = $this->resolveGrossAmount($entry);
            $this->aggregateAmount(
                $aggregated,
                $baseCategory,
                $entry->has_qualified_invoice,
                Carbon::parse($entry->entry_date),
                $gross,
                false,
            );
        }

        $rows = array_values($aggregated);
        usort($rows, fn (array $a, array $b) => $a['category'] <=> $b['category']);

        if ($isTaxExempt) {
            return [
                'company_name' => $company->name ?? '',
                'fiscal_period' => $fiscalYear->start_date->format('Y-m-d').' 〜 '.$fiscalYear->end_date->format('Y-m-d'),
                'tax_entity_status' => $taxEntityStatus->value,
                'tax_entity_status_label' => $taxEntityStatus->label(),
                'is_tax_exempt' => true,
                'tax_method' => TaxEntityStatus::Exempt->label(),
                'simplified_industry' => null,
                'rows' => $rows,
                'summary' => [
                    'output_tax_total' => 0,
                    'input_tax_total' => 0,
                    'estimated_tax_payable' => 0,
                ],
            ];
        }

        $outputTax = 0;
        $inputTax = 0;

        foreach ($rows as $row) {
            $category = ConsumptionTaxCategory::from($row['category']);
            if ($category->isSales() && $category->ratePercent() > 0) {
                $outputTax += $row['tax_total'];
            }
            if ($category->isPurchase()) {
                $inputTax += $row['deductible_tax_total'];
            }
        }

        $taxMethod = $company->consumption_tax_method ?? ConsumptionTaxMethod::Standard;
        $estimatedPayable = $taxMethod === ConsumptionTaxMethod::Simplified && $company->simplified_tax_industry !== null
            ? (int) round($outputTax * (1 - $company->simplified_tax_industry->deemedPurchaseRatioPercent() / 100))
            : $outputTax - $inputTax;

        return [
            'company_name' => $company->name ?? '',
            'fiscal_period' => $fiscalYear->start_date->format('Y-m-d').' 〜 '.$fiscalYear->end_date->format('Y-m-d'),
            'tax_entity_status' => $taxEntityStatus->value,
            'tax_entity_status_label' => $taxEntityStatus->label(),
            'is_tax_exempt' => false,
            'tax_method' => $taxMethod->label(),
            'simplified_industry' => $company->simplified_tax_industry?->label(),
            'rows' => $rows,
            'summary' => [
                'output_tax_total' => $outputTax,
                'input_tax_total' => $inputTax,
                'estimated_tax_payable' => $estimatedPayable,
            ],
        ];
    }

    /**
     * @param  array<string, array{category: string, category_label: string, transaction_count: int, gross_total: int, net_total: int, tax_total: int, deductible_tax_total: int}>  $aggregated
     */
    private function aggregateLineLevelEntry(JournalEntry $entry, array &$aggregated, bool $isTaxExempt = false): void
    {
        foreach ($entry->lines as $line) {
            if ($line->consumption_tax_category === null) {
                continue;
            }

            $gross = max($line->debit, $line->credit);
            if ($gross === 0) {
                continue;
            }

            $this->aggregateAmount(
                $aggregated,
                $line->consumption_tax_category,
                $entry->has_qualified_invoice,
                Carbon::parse($entry->entry_date),
                $gross,
                $isTaxExempt,
            );
        }
    }

    /**
     * @param  array<string, array{category: string, category_label: string, transaction_count: int, gross_total: int, net_total: int, tax_total: int, deductible_tax_total: int}>  $aggregated
     */
    private function aggregateAmount(
        array &$aggregated,
        ConsumptionTaxCategory $baseCategory,
        ?bool $hasQualifiedInvoice,
        Carbon $entryDate,
        int $gross,
        bool $isTaxExempt = false,
    ): void {
        if ($isTaxExempt) {
            $key = ConsumptionTaxCategory::OutOfScope->value;

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'category' => $key,
                    'category_label' => ConsumptionTaxCategory::OutOfScope->label(),
                    'transaction_count' => 0,
                    'gross_total' => 0,
                    'net_total' => 0,
                    'tax_total' => 0,
                    'deductible_tax_total' => 0,
                ];
            }

            $aggregated[$key]['transaction_count']++;
            $aggregated[$key]['gross_total'] += $gross;
            $aggregated[$key]['net_total'] += $gross;

            return;
        }

        $split = $this->consumptionTaxService->summarizeEntry(
            $baseCategory,
            $hasQualifiedInvoice,
            $entryDate,
            $gross,
        );

        $key = $split['effective_category'];

        if (! isset($aggregated[$key])) {
            $aggregated[$key] = [
                'category' => $split['effective_category'],
                'category_label' => $split['effective_category_label'],
                'transaction_count' => 0,
                'gross_total' => 0,
                'net_total' => 0,
                'tax_total' => 0,
                'deductible_tax_total' => 0,
            ];
        }

        $aggregated[$key]['transaction_count']++;
        $aggregated[$key]['gross_total'] += $split['gross'];
        $aggregated[$key]['net_total'] += $split['net'];
        $aggregated[$key]['tax_total'] += $split['tax'];
        $aggregated[$key]['deductible_tax_total'] += $split['deductible_tax'];
    }

    private function hasLineLevelTax(JournalEntry $entry): bool
    {
        return $entry->lines->contains(fn ($line) => $line->consumption_tax_category !== null);
    }

    private function resolveGrossAmount(JournalEntry $entry): int
    {
        foreach ($entry->lines as $line) {
            $name = $line->account?->name;
            if (in_array($name, ['普通預金', '当座預金', '現金'], true)) {
                return max($line->debit, $line->credit);
            }
            if ($name === '役員借入金' && $line->credit > 0) {
                return $line->credit;
            }
        }

        return (int) ($entry->lines->max('debit') ?? 0);
    }
}
