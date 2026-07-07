import BankImportRowEditDialog from '@/components/bank-import-row-edit-dialog';
import { DataTable, DataTableHeader } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FlashAlert } from '@/components/flash-alert';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SummaryStrip } from '@/components/summary-strip';
import { WorkflowSteps, bankImportSteps } from '@/components/workflow-steps';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Pencil } from 'lucide-react';
import { useMemo } from 'react';

interface AccountOption {
    id: number;
    name: string;
}

interface ImportRow {
    id: number;
    transaction_date: string;
    description: string;
    amount: number;
    is_deposit: boolean;
    status: string;
    account_id: number | null;
    journal_entry_id: number | null;
    consumption_tax_category?: string | null;
    has_qualified_invoice?: boolean;
}

interface Props {
    bankImport: {
        id: number;
        original_filename: string;
        status: string;
        imported_at: string;
    };
    rows: ImportRow[];
    accountGroups: Record<string, AccountOption[]>;
    salesTaxCategories: Array<{ value: string; label: string }>;
    purchaseTaxCategories: Array<{ value: string; label: string }>;
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '銀行CSV取込', href: route('bank-import') },
    { title: '取込履歴', href: route('bank-import.history') },
    { title: '取込詳細', href: '#' },
];

const statusLabels: Record<string, string> = {
    pending: '未記帳',
    confirmed: '記帳済',
    skipped: 'スキップ',
};

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'confirmed') return 'default';
    if (status === 'pending') return 'secondary';
    return 'outline';
}

export default function BankImportShow({
    bankImport,
    rows,
    accountGroups,
    salesTaxCategories,
    purchaseTaxCategories,
    hasActiveFiscalYear,
}: Props) {
    const counts = useMemo(() => {
        return rows.reduce(
            (acc, row) => {
                acc[row.status] = (acc[row.status] ?? 0) + 1;
                return acc;
            },
            {} as Record<string, number>,
        );
    }, [rows]);

    const pendingCount = counts.pending ?? 0;

    const handleSkip = (rowId: number) => {
        router.post(route('bank-import.rows.skip', rowId), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="取込詳細" />
            <PageContainer size="full">
                <PageHeader
                    title="取込詳細"
                    description={bankImport.original_filename}
                    actions={
                        pendingCount > 0 ? (
                            <Button asChild>
                                <Link href={route('bank-import.review', bankImport.id)}>記帳を続ける</Link>
                            </Button>
                        ) : undefined
                    }
                />

                <FlashAlert />

                <WorkflowSteps steps={bankImportSteps} currentStep={pendingCount > 0 ? 'review' : 'complete'} />

                <SummaryStrip
                    items={[
                        { label: '取込日時', value: bankImport.imported_at },
                        { label: '全件', value: `${rows.length}件` },
                        { label: '記帳済', value: `${counts.confirmed ?? 0}件`, variant: 'success' },
                        { label: '未記帳', value: `${pendingCount}件`, variant: pendingCount > 0 ? 'warning' : 'default' },
                        { label: 'スキップ', value: `${counts.skipped ?? 0}件` },
                    ]}
                />

                {rows.length === 0 ? (
                    <EmptyState icon={FileText} title="取引データがありません" description="この取込には表示できる取引がありません。" />
                ) : (
                    <DataTable>
                        <DataTableHeader>
                            <TableRow>
                                <TableHead>日付</TableHead>
                                <TableHead>摘要</TableHead>
                                <TableHead className="text-right">金額</TableHead>
                                <TableHead>状態</TableHead>
                                <TableHead className="text-right">操作</TableHead>
                            </TableRow>
                        </DataTableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="whitespace-nowrap">{formatDate(row.transaction_date)}</TableCell>
                                    <TableCell>{row.description}</TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        <span className={row.is_deposit ? 'text-green-700 dark:text-green-400' : ''}>
                                            {row.is_deposit ? '+' : '-'}
                                            {formatAmount(row.amount)}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant(row.status)}>
                                            {statusLabels[row.status] ?? row.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {row.status !== 'skipped' && (
                                                <BankImportRowEditDialog
                                                    rowId={row.id}
                                                    isDeposit={row.is_deposit}
                                                    initialValues={{
                                                        transaction_date: row.transaction_date,
                                                        description: row.description,
                                                        amount: row.amount,
                                                        account_id: row.account_id,
                                                        consumption_tax_category:
                                                            row.consumption_tax_category ??
                                                            (row.is_deposit ? 'taxable_sales_10' : 'taxable_purchase_10'),
                                                        has_qualified_invoice: row.has_qualified_invoice ?? true,
                                                    }}
                                                    accountGroups={accountGroups}
                                                    salesTaxCategories={salesTaxCategories}
                                                    purchaseTaxCategories={purchaseTaxCategories}
                                                    hasActiveFiscalYear={hasActiveFiscalYear}
                                                    trigger={
                                                        <Button variant="ghost" size="sm">
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                    }
                                                />
                                            )}
                                            {row.status === 'pending' && (
                                                <Button type="button" variant="ghost" size="sm" onClick={() => handleSkip(row.id)}>
                                                    スキップ
                                                </Button>
                                            )}
                                            {row.status === 'confirmed' && row.journal_entry_id !== null && (
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={route('journals.index')}>仕訳</Link>
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </DataTable>
                )}
            </PageContainer>
        </AppLayout>
    );
}
