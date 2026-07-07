import { EmptyState } from '@/components/empty-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { ReportDocument, ReportSection } from '@/components/report-document';
import { StatCard } from '@/components/stat-card';
import { SummaryStrip } from '@/components/summary-strip';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    BookOpen,
    Building2,
    CalendarDays,
    FileSpreadsheet,
    FileText,
    Layers,
    Receipt,
    Scale,
    TrendingUp,
} from 'lucide-react';
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
    { id: 'pl', label: '損益計算書', icon: TrendingUp },
    { id: 'bs', label: '貸借対照表', icon: Scale },
    { id: 'journal', label: '仕訳帳', icon: BookOpen },
    { id: 'ledger', label: '総勘定元帳', icon: Layers },
    { id: 'consumption_tax', label: '消費税区分集計', icon: Receipt },
] as const;

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP').format(amount);
}

function ExportButtons({ type }: { type: string }) {
    return (
        <div className="flex flex-wrap gap-2">
            {type !== 'consumption_tax' && (
                <Button size="sm" asChild>
                    <a href={route('reports.export', { type, format: 'pdf' })}>
                        <FileText className="size-4" />
                        PDF出力
                    </a>
                </Button>
            )}
            <Button variant="outline" size="sm" asChild>
                <a href={route('reports.export', { type, format: 'csv' })}>
                    <FileSpreadsheet className="size-4" />
                    CSV出力
                </a>
            </Button>
        </div>
    );
}

export default function ReportsIndex({ reports, hasActiveFiscalYear, companyName, fiscalYear }: Props) {
    const [activeTab, setActiveTab] = useState<(typeof tabs)[number]['id']>('pl');

    const periodLabel =
        fiscalYear != null ? `${formatDate(fiscalYear.start_date)} 〜 ${formatDate(fiscalYear.end_date)}` : '未設定';
    const displayCompanyName = companyName ?? '（社名未設定）';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="決算書出力" />
            <PageContainer size="full">
                <PageHeader title="決算書出力" description="損益計算書・貸借対照表などの決算書類を確認・出力します" />

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="会社" icon={Building2}>
                        <p className="truncate text-base font-semibold">{displayCompanyName}</p>
                    </StatCard>
                    <StatCard label="会計期間" icon={CalendarDays}>
                        <p className="text-base font-semibold">{periodLabel}</p>
                    </StatCard>
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

                <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                    <nav className="surface-card space-y-1 p-2 lg:sticky lg:top-20 lg:self-start">
                        {tabs.map((tab) => {
                            const Icon = tab.icon;
                            const isActive = activeTab === tab.id;

                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => setActiveTab(tab.id)}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors',
                                        isActive
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-4 shrink-0" />
                                    <span>{tab.label}</span>
                                </button>
                            );
                        })}
                    </nav>

                    <div className="min-w-0">
                        {activeTab === 'pl' && (
                            <ReportDocument
                                companyName={displayCompanyName}
                                periodLabel={periodLabel}
                                title="損益計算書"
                                description="当期の収益・費用・純利益"
                                toolbar={hasActiveFiscalYear ? <ExportButtons type="pl" /> : undefined}
                            >
                                {reports.pl ? (
                                    <div className="space-y-6">
                                        <div className="grid gap-6 lg:grid-cols-2">
                                            <ReportSection title="【収益の部】">
                                                <ReportTable
                                                    rows={reports.pl.revenue_rows}
                                                    totalLabel="収益合計"
                                                    total={reports.pl.total_revenue}
                                                />
                                            </ReportSection>
                                            <ReportSection title="【費用の部】">
                                                <ReportTable
                                                    rows={reports.pl.expense_rows}
                                                    totalLabel="費用合計"
                                                    total={reports.pl.total_expense}
                                                />
                                            </ReportSection>
                                        </div>
                                        <SummaryStrip
                                            items={[
                                                { label: '収益合計', value: formatAmount(reports.pl.total_revenue) },
                                                { label: '費用合計', value: formatAmount(reports.pl.total_expense) },
                                                {
                                                    label: '当期純利益',
                                                    value: formatAmount(reports.pl.net_income),
                                                    highlight: true,
                                                    variant: reports.pl.net_income >= 0 ? 'success' : 'warning',
                                                },
                                            ]}
                                        />
                                    </div>
                                ) : (
                                    <EmptyReport />
                                )}
                            </ReportDocument>
                        )}

                        {activeTab === 'bs' && (
                            <ReportDocument
                                companyName={displayCompanyName}
                                periodLabel={periodLabel}
                                title="貸借対照表"
                                description="期末時点の資産・負債・純資産"
                                toolbar={hasActiveFiscalYear ? <ExportButtons type="bs" /> : undefined}
                            >
                                {reports.bs ? (
                                    <div className="space-y-6">
                                        <div className="grid gap-6 lg:grid-cols-2">
                                            <ReportSection title="【資産の部】">
                                                <ReportTable
                                                    rows={reports.bs.asset_rows}
                                                    totalLabel="資産合計"
                                                    total={reports.bs.total_assets}
                                                />
                                            </ReportSection>
                                            <div className="space-y-6">
                                                <ReportSection title="【負債の部】">
                                                    <ReportTable
                                                        rows={reports.bs.liability_rows}
                                                        totalLabel="負債合計"
                                                        total={reports.bs.total_liabilities}
                                                    />
                                                </ReportSection>
                                                <ReportSection title="【純資産の部】">
                                                    <ReportTable
                                                        rows={reports.bs.equity_rows}
                                                        totalLabel="純資産合計"
                                                        total={reports.bs.total_equity}
                                                    />
                                                </ReportSection>
                                            </div>
                                        </div>
                                        <SummaryStrip
                                            items={[
                                                { label: '資産合計', value: formatAmount(reports.bs.total_assets) },
                                                {
                                                    label: '負債・純資産合計',
                                                    value: formatAmount(reports.bs.total_liabilities_and_equity),
                                                    highlight: true,
                                                },
                                                {
                                                    label: '貸借一致',
                                                    value: reports.bs.is_balanced ? '一致' : '不一致',
                                                    variant: reports.bs.is_balanced ? 'success' : 'warning',
                                                },
                                            ]}
                                        />
                                    </div>
                                ) : (
                                    <EmptyReport />
                                )}
                            </ReportDocument>
                        )}

                        {activeTab === 'journal' && (
                            <ReportDocument
                                companyName={displayCompanyName}
                                periodLabel={periodLabel}
                                title="仕訳帳"
                                description="当期の仕訳一覧"
                                toolbar={hasActiveFiscalYear ? <ExportButtons type="journal" /> : undefined}
                            >
                                {reports.journal && reports.journal.entries.length > 0 ? (
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-[13px]">
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
                                                        <tr key={`${entry.id}-${index}`} className="border-b last:border-0 hover:bg-muted/30">
                                                            <td className="px-4 py-2.5 whitespace-nowrap">
                                                                {index === 0 ? formatDate(entry.entry_date) : ''}
                                                            </td>
                                                            <td className="px-4 py-2.5 font-medium">
                                                                {index === 0 ? entry.description : ''}
                                                            </td>
                                                            <td className="px-4 py-2.5">{line.account_name}</td>
                                                            <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                {line.debit ? formatAmount(line.debit) : ''}
                                                            </td>
                                                            <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
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
                            </ReportDocument>
                        )}

                        {activeTab === 'ledger' && (
                            <ReportDocument
                                companyName={displayCompanyName}
                                periodLabel={periodLabel}
                                title="総勘定元帳"
                                description="勘定科目ごとの取引明細"
                                toolbar={hasActiveFiscalYear ? <ExportButtons type="ledger" /> : undefined}
                            >
                                {reports.ledger && reports.ledger.accounts.length > 0 ? (
                                    <div className="space-y-8">
                                        {reports.ledger.accounts.map((account) => (
                                            <ReportSection key={account.account_id} title={account.account_name}>
                                                <div className="overflow-x-auto rounded-lg border">
                                                    <table className="w-full text-[13px]">
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
                                                                <tr key={index} className="border-b last:border-0 hover:bg-muted/30">
                                                                    <td className="px-4 py-2.5 whitespace-nowrap">
                                                                        {formatDate(line.entry_date)}
                                                                    </td>
                                                                    <td className="px-4 py-2.5">{line.description}</td>
                                                                    <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                        {line.debit ? formatAmount(line.debit) : ''}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                        {line.credit ? formatAmount(line.credit) : ''}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                        {formatAmount(line.balance)}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                            <tr className="border-t-2 font-semibold">
                                                                <td className="px-4 py-3" colSpan={4}>
                                                                    期末残高
                                                                </td>
                                                                <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                                    {formatAmount(account.closing_balance)}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </ReportSection>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyReport />
                                )}
                            </ReportDocument>
                        )}

                        {activeTab === 'consumption_tax' && (
                            <ReportDocument
                                companyName={displayCompanyName}
                                periodLabel={periodLabel}
                                title="消費税区分集計"
                                description="税区分別の取引集計と納付税額見込"
                                toolbar={hasActiveFiscalYear ? <ExportButtons type="consumption_tax" /> : undefined}
                            >
                                {reports.consumption_tax ? (
                                    <div className="space-y-6">
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <StatCard label="売上税額合計">
                                                <p className="text-base font-semibold tabular-nums">
                                                    {formatAmount(reports.consumption_tax.summary.output_tax_total)}
                                                </p>
                                            </StatCard>
                                            <StatCard label="仕入税額合計">
                                                <p className="text-base font-semibold tabular-nums">
                                                    {formatAmount(reports.consumption_tax.summary.input_tax_total)}
                                                </p>
                                            </StatCard>
                                            <StatCard label="納付税額見込">
                                                <p className="text-primary text-base font-semibold tabular-nums">
                                                    {formatAmount(reports.consumption_tax.summary.estimated_tax_payable)}
                                                </p>
                                            </StatCard>
                                        </div>

                                        <div className="text-muted-foreground grid gap-1 text-sm sm:grid-cols-2">
                                            <p>課税方式: {reports.consumption_tax.tax_method}</p>
                                            {reports.consumption_tax.simplified_industry && (
                                                <p>簡易課税業種: {reports.consumption_tax.simplified_industry}</p>
                                            )}
                                        </div>

                                        <div className="overflow-x-auto rounded-lg border">
                                            <table className="w-full text-[13px]">
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
                                                            <tr key={row.category} className="border-b last:border-0 hover:bg-muted/30">
                                                                <td className="px-4 py-2.5 font-medium">{row.category_label}</td>
                                                                <td className="px-4 py-2.5 text-right tabular-nums">{row.transaction_count}</td>
                                                                <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                    {formatAmount(row.gross_total)}
                                                                </td>
                                                                <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                    {formatAmount(row.net_total)}
                                                                </td>
                                                                <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                    {formatAmount(row.tax_total)}
                                                                </td>
                                                                <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                                    {formatAmount(row.deductible_tax_total)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ) : (
                                    <EmptyReport />
                                )}
                            </ReportDocument>
                        )}
                    </div>
                </div>
            </PageContainer>
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
            <table className="w-full text-[13px]">
                <thead>
                    <tr className="bg-muted/50 border-b">
                        <th className="px-4 py-2.5 text-left font-medium">勘定科目</th>
                        <th className="px-4 py-2.5 text-right font-medium">金額</th>
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
                            <tr key={row.account_name} className="border-b hover:bg-muted/30">
                                <td className="px-4 py-2.5">{row.account_name}</td>
                                <td className="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">{formatAmount(row.amount)}</td>
                            </tr>
                        ))
                    )}
                    <tr className="border-t-2 font-semibold">
                        <td className="px-4 py-3">{totalLabel}</td>
                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">{formatAmount(total)}</td>
                    </tr>
                    {footerLabel !== undefined && footerTotal !== undefined && (
                        <tr className="border-t-2 bg-muted/20 font-semibold">
                            <td className="px-4 py-3">{footerLabel}</td>
                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">{formatAmount(footerTotal)}</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function EmptyReport() {
    return (
        <EmptyState
            icon={FileText}
            title="表示するデータがありません"
            description="会計期間内の仕訳が登録されると、ここに決算書が表示されます。"
        />
    );
}
