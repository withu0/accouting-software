<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Services\ConsumptionTaxReportService;
use App\Services\DompdfFontRegistrar;
use App\Services\ReportService;
use App\Support\JapaneseDateFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ResolvesCompany;

    private const VALID_TYPES = ['pl', 'bs', 'journal', 'ledger', 'consumption_tax'];

    private const VALID_FORMATS = ['pdf', 'csv'];

    private const TYPE_LABELS = [
        'pl' => '損益計算書',
        'bs' => '貸借対照表',
        'journal' => '仕訳帳',
        'ledger' => '総勘定元帳',
        'consumption_tax' => '消費税区分集計',
    ];

    public function __construct(
        private readonly ReportService $reportService,
        private readonly ConsumptionTaxReportService $consumptionTaxReportService,
        private readonly DompdfFontRegistrar $dompdfFontRegistrar,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $company = $this->resolveCompany($request);
        $fiscalYear = $company->activeFiscalYear();

        $reports = [
            'pl' => null,
            'bs' => null,
            'journal' => null,
            'ledger' => null,
            'consumption_tax' => null,
        ];

        if ($fiscalYear !== null) {
            $reports = [
                'pl' => $this->reportService->profitAndLoss($company, $fiscalYear),
                'bs' => $this->reportService->balanceSheet($company, $fiscalYear),
                'journal' => $this->reportService->journalBook($company, $fiscalYear),
                'ledger' => $this->reportService->generalLedger($company, $fiscalYear),
                'consumption_tax' => $this->consumptionTaxReportService->aggregate($company, $fiscalYear),
            ];
        }

        return Inertia::render('reports/index', [
            'reports' => $reports,
            'hasActiveFiscalYear' => $fiscalYear !== null,
            'companyName' => $company->name,
            'fiscalYear' => $fiscalYear ? [
                'start_date' => $fiscalYear->start_date->format('Y-m-d'),
                'end_date' => $fiscalYear->end_date->format('Y-m-d'),
            ] : null,
        ]);
    }

    public function export(Request $request, string $type, string $format): Response|StreamedResponse
    {
        abort_unless(in_array($type, self::VALID_TYPES, true), 404);
        abort_unless(in_array($format, self::VALID_FORMATS, true), 404);

        if ($type === 'consumption_tax' && $format === 'pdf') {
            abort(404);
        }

        $company = $this->resolveCompany($request);
        $fiscalYear = $company->activeFiscalYear();

        if ($fiscalYear === null) {
            abort(422, '会計期間が設定されていません。');
        }

        $reportData = $this->resolveReportData($company, $fiscalYear, $type);
        $meta = $this->reportMeta($company, $fiscalYear, $type);
        $filename = $this->buildFilename($company, $type, $format);

        if ($format === 'pdf') {
            return $this->exportPdf($type, $meta, $reportData, $filename);
        }

        return $this->exportCsv($type, $reportData, $filename);
    }

    private function exportPdf(string $type, array $meta, array $reportData, string $filename): Response
    {
        $this->dompdfFontRegistrar->ensureJapaneseFontRegistered();

        $view = match ($type) {
            'pl' => 'reports.pl',
            'bs' => 'reports.bs',
            'journal' => 'reports.journal',
            'ledger' => 'reports.ledger',
        };

        $pdf = Pdf::loadView($view, [
            'meta' => $meta,
            'report' => $reportData,
        ])->setPaper('a4', $meta['orientation']);

        return $pdf->download($filename);
    }

    private function exportCsv(string $type, array $reportData, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($type, $reportData) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            match ($type) {
                'pl' => $this->writePlCsv($handle, $reportData),
                'bs' => $this->writeBsCsv($handle, $reportData),
                'journal' => $this->writeJournalCsv($handle, $reportData),
                'ledger' => $this->writeLedgerCsv($handle, $reportData),
                'consumption_tax' => $this->writeConsumptionTaxCsv($handle, $reportData),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $handle
     */
    private function writePlCsv($handle, array $report): void
    {
        fputcsv($handle, ['区分', '勘定科目', '金額']);
        foreach ($report['revenue_rows'] as $row) {
            fputcsv($handle, ['収益', $row['account_name'], $row['amount']]);
        }
        fputcsv($handle, ['', '収益合計', $report['total_revenue']]);
        foreach ($report['expense_rows'] as $row) {
            fputcsv($handle, ['費用', $row['account_name'], $row['amount']]);
        }
        fputcsv($handle, ['', '費用合計', $report['total_expense']]);
        fputcsv($handle, ['', '当期純利益', $report['net_income']]);
    }

    /**
     * @param  resource  $handle
     */
    private function writeBsCsv($handle, array $report): void
    {
        fputcsv($handle, ['区分', '勘定科目', '金額']);
        foreach ($report['asset_rows'] as $row) {
            fputcsv($handle, ['資産', $row['account_name'], $row['amount']]);
        }
        fputcsv($handle, ['', '資産合計', $report['total_assets']]);
        foreach ($report['liability_rows'] as $row) {
            fputcsv($handle, ['負債', $row['account_name'], $row['amount']]);
        }
        fputcsv($handle, ['', '負債合計', $report['total_liabilities']]);
        foreach ($report['equity_rows'] as $row) {
            fputcsv($handle, ['純資産', $row['account_name'], $row['amount']]);
        }
        fputcsv($handle, ['', '純資産合計', $report['total_equity']]);
        fputcsv($handle, ['', '負債・純資産合計', $report['total_liabilities_and_equity']]);
    }

    /**
     * @param  resource  $handle
     */
    private function writeJournalCsv($handle, array $report): void
    {
        fputcsv($handle, ['日付', '摘要', '勘定科目', '借方', '貸方']);
        foreach ($report['entries'] as $entry) {
            foreach ($entry['lines'] as $index => $line) {
                fputcsv($handle, [
                    $index === 0 ? $entry['entry_date'] : '',
                    $index === 0 ? $entry['description'] : '',
                    $line['account_name'],
                    $line['debit'] ?: '',
                    $line['credit'] ?: '',
                ]);
            }
        }
    }

    /**
     * @param  resource  $handle
     */
    private function writeLedgerCsv($handle, array $report): void
    {
        fputcsv($handle, ['勘定科目', '日付', '摘要', '借方', '貸方', '残高']);
        foreach ($report['accounts'] as $account) {
            foreach ($account['lines'] as $index => $line) {
                fputcsv($handle, [
                    $index === 0 ? $account['account_name'] : '',
                    $line['entry_date'],
                    $line['description'],
                    $line['debit'] ?: '',
                    $line['credit'] ?: '',
                    $line['balance'],
                ]);
            }
        }
    }

    /**
     * @param  resource  $handle
     */
    private function writeConsumptionTaxCsv($handle, array $report): void
    {
        fputcsv($handle, ['会社名', $report['company_name']]);
        fputcsv($handle, ['会計期間', $report['fiscal_period']]);
        fputcsv($handle, ['課税方式', $report['tax_method']]);
        if ($report['simplified_industry'] !== null) {
            fputcsv($handle, ['簡易課税業種', $report['simplified_industry']]);
        }
        fputcsv($handle, []);
        fputcsv($handle, ['税区分', '取引件数', '税込金額合計', '税抜金額合計', '消費税額合計', '控除対象税額合計']);
        foreach ($report['rows'] as $row) {
            fputcsv($handle, [
                $row['category_label'],
                $row['transaction_count'],
                $row['gross_total'],
                $row['net_total'],
                $row['tax_total'],
                $row['deductible_tax_total'],
            ]);
        }
        fputcsv($handle, []);
        fputcsv($handle, ['売上税額合計', $report['summary']['output_tax_total']]);
        fputcsv($handle, ['仕入税額合計（控除対象）', $report['summary']['input_tax_total']]);
        fputcsv($handle, ['納付税額見込', $report['summary']['estimated_tax_payable']]);
    }

    private function resolveReportData(Company $company, FiscalYear $fiscalYear, string $type): array
    {
        return match ($type) {
            'pl' => $this->reportService->profitAndLoss($company, $fiscalYear),
            'bs' => $this->reportService->balanceSheet($company, $fiscalYear),
            'journal' => $this->reportService->journalBook($company, $fiscalYear),
            'ledger' => $this->reportService->generalLedger($company, $fiscalYear),
            'consumption_tax' => $this->consumptionTaxReportService->aggregate($company, $fiscalYear),
        };
    }

    /**
     * @return array{
     *     company_name: ?string,
     *     address: ?string,
     *     representative_name: ?string,
     *     title: string,
     *     unit_label: string,
     *     period_label: ?string,
     *     as_of_label: ?string,
     *     generated_at: string,
     *     orientation: string,
     * }
     */
    private function reportMeta(Company $company, FiscalYear $fiscalYear, string $type): array
    {
        $startLabel = JapaneseDateFormatter::format($fiscalYear->start_date);
        $endLabel = JapaneseDateFormatter::format($fiscalYear->end_date);
        $isLandscape = $type === 'ledger' || $type === 'journal';

        return [
            'company_name' => $company->name,
            'address' => $company->address,
            'representative_name' => $company->representative_name,
            'title' => self::TYPE_LABELS[$type],
            'unit_label' => '（単位：円）',
            'period_label' => $type === 'bs' ? null : "自 {$startLabel} 至 {$endLabel}",
            'as_of_label' => $type === 'bs' ? "{$endLabel}現在" : null,
            'generated_at' => JapaneseDateFormatter::format(now()),
            'orientation' => $isLandscape ? 'landscape' : 'portrait',
        ];
    }

    private function buildFilename(Company $company, string $type, string $format): string
    {
        $companySlug = Str::slug($company->name ?? 'company', '_');
        if ($companySlug === '') {
            $companySlug = 'company';
        }

        return sprintf('%s_%s.%s', $companySlug, $type, $format);
    }
}
