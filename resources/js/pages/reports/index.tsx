import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, Download, FileSpreadsheet, FileText } from 'lucide-react';
import { useState } from 'react';

interface ReportRow {
    account_id?: number | null;
    account_name: string;
    amount: number;
}

interface JournalLine {
    account_name: string;
    debit: number;
    credit: number;
}

interface JournalEntry {
    id: number;
    entry_date: string;
    description: string;
    source: string;
    lines: JournalLine[];
    total_amount: number;
}

interface LedgerLine {
    entry_date: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
}

interface LedgerAccount {
    account_id: number;
    account_name: string;
    account_type: string;
    opening_balance: number;
    lines: LedgerLine[];
    closing_balance: number;
}

interface ProfitAndLossReport {
    revenue_rows: ReportRow[];
    expense_rows: ReportRow[];
    total_revenue: number;
    total_expense: number;
    net_income: number;
}

interface BalanceSheetReport {
    asset_rows: ReportRow[];
    liability_rows: ReportRow[];
    equity_rows: ReportRow[];
    total_assets: number;
    total_liabilities: number;
    total_equity: number;
    total_liabilities_and_equity: number;
    is_balanced: boolean;
}

interface JournalBookReport {
    entries: JournalEntry[];
}

interface GeneralLedgerReport {
    accounts: LedgerAccount[];
}

interface ConsumptionTaxReport {
    company_name: string;
    fiscal_period: string;
    tax_method: string;
    simplified_industry: string | null;
    rows: Array<{
        category: string;
        category_label: string;
        transaction_count: number;
        gross_total: number;
        net_total: number;
        tax_total: number;
        deductible_tax_total: number;
    }>;
    summary: {
        output_tax_total: number;
        input_tax_total: number;
        estimated_tax_payable: number;
    };
}

interface Reports {
    pl: ProfitAndLossReport | null;
    bs: BalanceSheetReport | null;
    journal: JournalBookReport | null;
    ledger: GeneralLedgerReport | null;
    consumption_tax: ConsumptionTaxReport | null;
}

interface Props {
    reports: Reports;
    hasActiveFiscalYear: boolean;
    companyName: string | null;
    fiscalYear: { start_date: string; end_date: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '決算書出力', href: route('reports') },
];

const tabs = [
    { id: 'pl', label: '損益計算書' },
    { id: 'bs', label: '貸借対照表' },
    { id: 'journal', label: '仕訳帳' },
    { id: 'ledger', label: '総勘定元帳' },
    { id: 'consumption_tax', label: '消費税区分集計' },
] as const;

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP').format(amount);
}

function ExportButtons({ type }: { type: string }) {
    return (
        <div className="flex flex-wrap gap-2">
            {type !== 'consumption_tax' && (
                <Button variant="outline" size="sm" asChild>
                    <a href={route('reports.export', { type, format: 'pdf' })}>
                        <FileText className="size-4" />
                        PDF
                    </a>
                </Button>
            )}
            <Button variant="outline" size="sm" asChild>
                <a href={route('reports.export', { type, format: 'csv' })}>
                    <FileSpreadsheet className="size-4" />
                    CSV
                </a>
            </Button>
        </div>
    );
}

export default function ReportsIndex({ reports, hasActiveFiscalYear, companyName, fiscalYear }: Props) {
    const [activeTab, setActiveTab] = useState<(typeof tabs)[number]['id']>('pl');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="決算書出力" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">決算書出力</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {companyName ?? '（社名未設定）'}
                            {fiscalYear && (
                                <span className="ml-2">
                                    会計期間: {formatDate(fiscalYear.start_date)} 〜 {formatDate(fiscalYear.end_date)}
                                </span>
                            )}
                        </p>
                    </div>
                </div>

                {!hasActiveFiscalYear && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            会計期間が未設定です。
                            <Link href={route('fiscal-year.edit')} className="ml-1 underline">
                                会計期間設定
                            </Link>
                            から先に設定してください。
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap gap-2">
                    {tabs.map((tab) => (
                        <Button
                            key={tab.id}
                            variant={activeTab === tab.id ? 'default' : 'outline'}
                            onClick={() => setActiveTab(tab.id)}
                        >
                            {tab.label}
                        </Button>
                    ))}
                </div>

                {activeTab === 'pl' && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>損益計算書</CardTitle>
                                <CardDescription>当期の収益・費用・純利益</CardDescription>
                            </div>
                            {hasActiveFiscalYear && <ExportButtons type="pl" />}
                        </CardHeader>
                        <CardContent>
                            {reports.pl ? (
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="mb-2 font-semibold">【収益の部】</h3>
                                        <ReportTable rows={reports.pl.revenue_rows} totalLabel="収益合計" total={reports.pl.total_revenue} />
                                    </div>
                                    <div>
                                        <h3 className="mb-2 font-semibold">【費用の部】</h3>
                                        <ReportTable
                                            rows={reports.pl.expense_rows}
                                            totalLabel="費用合計"
                                            total={reports.pl.total_expense}
                                            footerLabel="当期純利益"
                                            footerTotal={reports.pl.net_income}
                                        />
                                    </div>
                                </div>
                            ) : (
                                <EmptyReport />
                            )}
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'bs' && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>貸借対照表</CardTitle>
                                <CardDescription>期末時点の資産・負債・純資産</CardDescription>
                            </div>
                            {hasActiveFiscalYear && <ExportButtons type="bs" />}
                        </CardHeader>
                        <CardContent>
                            {reports.bs ? (
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="mb-2 font-semibold">【資産の部】</h3>
                                        <ReportTable rows={reports.bs.asset_rows} totalLabel="資産合計" total={reports.bs.total_assets} />
                                    </div>
                                    <div>
                                        <h3 className="mb-2 font-semibold">【負債の部】</h3>
                                        <ReportTable rows={reports.bs.liability_rows} totalLabel="負債合計" total={reports.bs.total_liabilities} />
                                    </div>
                                    <div>
                                        <h3 className="mb-2 font-semibold">【純資産の部】</h3>
                                        <ReportTable
                                            rows={reports.bs.equity_rows}
                                            totalLabel="純資産合計"
                                            total={reports.bs.total_equity}
                                            footerLabel="負債・純資産合計"
                                            footerTotal={reports.bs.total_liabilities_and_equity}
                                        />
                                    </div>
                                </div>
                            ) : (
                                <EmptyReport />
                            )}
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'journal' && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>仕訳帳</CardTitle>
                                <CardDescription>当期の仕訳一覧</CardDescription>
                            </div>
                            {hasActiveFiscalYear && <ExportButtons type="journal" />}
                        </CardHeader>
                        <CardContent>
                            {reports.journal && reports.journal.entries.length > 0 ? (
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="bg-muted/50 border-b">
                                                <th className="px-4 py-3 text-left font-medium">日付</th>
                                                <th className="px-4 py-3 text-left font-medium">摘要</th>
                                                <th className="px-4 py-3 text-left font-medium">勘定科目</th>
                                                <th className="px-4 py-3 text-right font-medium">借方</th>
                                                <th className="px-4 py-3 text-right font-medium">貸方</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {reports.journal.entries.map((entry) =>
                                                entry.lines.map((line, index) => (
                                                    <tr key={`${entry.id}-${index}`} className="border-b last:border-0">
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            {index === 0 ? formatDate(entry.entry_date) : ''}
                                                        </td>
                                                        <td className="px-4 py-3">{index === 0 ? entry.description : ''}</td>
                                                        <td className="px-4 py-3">{line.account_name}</td>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                                            {line.debit ? formatAmount(line.debit) : ''}
                                                        </td>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                                            {line.credit ? formatAmount(line.credit) : ''}
                                                        </td>
                                                    </tr>
                                                )),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyReport />
                            )}
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'ledger' && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>総勘定元帳</CardTitle>
                                <CardDescription>勘定科目ごとの取引明細</CardDescription>
                            </div>
                            {hasActiveFiscalYear && <ExportButtons type="ledger" />}
                        </CardHeader>
                        <CardContent>
                            {reports.ledger && reports.ledger.accounts.length > 0 ? (
                                <div className="space-y-8">
                                    {reports.ledger.accounts.map((account) => (
                                        <div key={account.account_id}>
                                            <h3 className="mb-2 font-semibold">{account.account_name}</h3>
                                            <div className="overflow-x-auto rounded-lg border">
                                                <table className="w-full text-sm">
                                                    <thead>
                                                        <tr className="bg-muted/50 border-b">
                                                            <th className="px-4 py-3 text-left font-medium">日付</th>
                                                            <th className="px-4 py-3 text-left font-medium">摘要</th>
                                                            <th className="px-4 py-3 text-right font-medium">借方</th>
                                                            <th className="px-4 py-3 text-right font-medium">貸方</th>
                                                            <th className="px-4 py-3 text-right font-medium">残高</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {account.lines.map((line, index) => (
                                                            <tr key={index} className="border-b last:border-0">
                                                                <td className="px-4 py-3 whitespace-nowrap">{formatDate(line.entry_date)}</td>
                                                                <td className="px-4 py-3">{line.description}</td>
                                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                    {line.debit ? formatAmount(line.debit) : ''}
                                                                </td>
                                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                    {line.credit ? formatAmount(line.credit) : ''}
                                                                </td>
                                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                    {formatAmount(line.balance)}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                        <tr className="bg-muted/30 font-medium">
                                                            <td className="px-4 py-3" colSpan={4}>
                                                                期末残高
                                                            </td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                {formatAmount(account.closing_balance)}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <EmptyReport />
                            )}
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'consumption_tax' && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>消費税区分集計</CardTitle>
                                <CardDescription>税区分別の取引集計と納付税額見込</CardDescription>
                            </div>
                            {hasActiveFiscalYear && <ExportButtons type="consumption_tax" />}
                        </CardHeader>
                        <CardContent>
                            {reports.consumption_tax ? (
                                <div className="space-y-6">
                                    <div className="text-muted-foreground grid gap-1 text-sm sm:grid-cols-2">
                                        <p>課税方式: {reports.consumption_tax.tax_method}</p>
                                        {reports.consumption_tax.simplified_industry && (
                                            <p>簡易課税業種: {reports.consumption_tax.simplified_industry}</p>
                                        )}
                                    </div>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="bg-muted/50 border-b">
                                                    <th className="px-4 py-3 text-left font-medium">税区分</th>
                                                    <th className="px-4 py-3 text-right font-medium">件数</th>
                                                    <th className="px-4 py-3 text-right font-medium">税込合計</th>
                                                    <th className="px-4 py-3 text-right font-medium">税抜合計</th>
                                                    <th className="px-4 py-3 text-right font-medium">消費税額</th>
                                                    <th className="px-4 py-3 text-right font-medium">控除対象税額</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {reports.consumption_tax.rows.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={6} className="text-muted-foreground px-4 py-8 text-center">
                                                            該当データがありません
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    reports.consumption_tax.rows.map((row) => (
                                                        <tr key={row.category} className="border-b last:border-0">
                                                            <td className="px-4 py-3">{row.category_label}</td>
                                                            <td className="px-4 py-3 text-right">{row.transaction_count}</td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                {formatAmount(row.gross_total)}
                                                            </td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                {formatAmount(row.net_total)}
                                                            </td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                {formatAmount(row.tax_total)}
                                                            </td>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                                {formatAmount(row.deductible_tax_total)}
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-sm">
                                            <tbody>
                                                <tr className="border-b">
                                                    <td className="px-4 py-3 font-medium">売上税額合計</td>
                                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                                        {formatAmount(reports.consumption_tax.summary.output_tax_total)}
                                                    </td>
                                                </tr>
                                                <tr className="border-b">
                                                    <td className="px-4 py-3 font-medium">仕入税額合計（控除対象）</td>
                                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                                        {formatAmount(reports.consumption_tax.summary.input_tax_total)}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="px-4 py-3 font-medium">納付税額見込</td>
                                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                                        {formatAmount(reports.consumption_tax.summary.estimated_tax_payable)}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ) : (
                                <EmptyReport />
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function ReportTable({
    rows,
    totalLabel,
    total,
    footerLabel,
    footerTotal,
}: {
    rows: ReportRow[];
    totalLabel: string;
    total: number;
    footerLabel?: string;
    footerTotal?: number;
}) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="bg-muted/50 border-b">
                        <th className="px-4 py-3 text-left font-medium">勘定科目</th>
                        <th className="px-4 py-3 text-right font-medium">金額</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td className="text-muted-foreground px-4 py-3 text-center" colSpan={2}>
                                該当なし
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={row.account_name} className="border-b">
                                <td className="px-4 py-3">{row.account_name}</td>
                                <td className="px-4 py-3 text-right whitespace-nowrap">{formatAmount(row.amount)}</td>
                            </tr>
                        ))
                    )}
                    <tr className="bg-muted/30 font-medium">
                        <td className="px-4 py-3">{totalLabel}</td>
                        <td className="px-4 py-3 text-right whitespace-nowrap">{formatAmount(total)}</td>
                    </tr>
                    {footerLabel !== undefined && footerTotal !== undefined && (
                        <tr className="bg-muted/30 font-medium">
                            <td className="px-4 py-3">{footerLabel}</td>
                            <td className="px-4 py-3 text-right whitespace-nowrap">{formatAmount(footerTotal)}</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function EmptyReport() {
    return (
        <div className="text-muted-foreground flex flex-col items-center gap-2 py-12 text-sm">
            <Download className="size-8 opacity-40" />
            表示するデータがありません
        </div>
    );
}
