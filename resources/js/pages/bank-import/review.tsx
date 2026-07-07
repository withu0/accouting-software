import { defaultCategoryForAccount, type TaxCategoryOption } from '@/components/consumption-tax-fields';
import { DataTable, DataTableHeader } from '@/components/data-table';
import { FlashAlert } from '@/components/flash-alert';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { FilterToolbar } from '@/components/filter-toolbar';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { SummaryStrip } from '@/components/summary-strip';
import { WorkflowSteps, bankImportSteps } from '@/components/workflow-steps';
import { Alert, AlertDescription } from '@/components/ui/alert';
import BankImportRowEditDialog from '@/components/bank-import-row-edit-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface ExpenseAccount {
    id: number;
    name: string;
    default_consumption_tax_category?: string | null;
}

interface ImportRow {
    id: number;
    transaction_date: string;
    description: string;
    deposit_amount: number;
    withdrawal_amount: number;
    amount: number;
    is_deposit: boolean;
    suggested_account_id?: number | null;
    account_id?: number | null;
    consumption_tax_category: string;
    has_qualified_invoice: boolean;
}

interface ImportSummary {
    total: number;
    new: number;
    duplicates: number;
    out_of_period?: number;
    detected_format?: string | null;
}

interface Props {
    bankImport: {
        id: number;
        original_filename: string;
    };
    rows: ImportRow[];
    expenseAccounts: ExpenseAccount[];
    accountGroups: Record<string, { id: number; name: string; default_consumption_tax_category?: string | null }[]>;
    salesTaxCategories: TaxCategoryOption[];
    purchaseTaxCategories: TaxCategoryOption[];
    hasActiveFiscalYear: boolean;
    importSummary: ImportSummary | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '銀行CSV取込', href: route('bank-import') },
    { title: '取引確認', href: '#' },
];

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

function initialAccountSelections(rows: ImportRow[]): Record<number, string> {
    const selections: Record<number, string> = {};
    for (const row of rows) {
        if (!row.is_deposit && row.suggested_account_id) {
            selections[row.id] = String(row.suggested_account_id);
        }
    }
    return selections;
}

function initialTaxCategories(rows: ImportRow[]): Record<number, string> {
    const categories: Record<number, string> = {};
    for (const row of rows) {
        categories[row.id] = row.consumption_tax_category;
    }
    return categories;
}

function initialQualifiedInvoices(rows: ImportRow[]): Record<number, boolean> {
    const invoices: Record<number, boolean> = {};
    for (const row of rows) {
        invoices[row.id] = row.has_qualified_invoice;
    }
    return invoices;
}

export default function BankImportReview({
    bankImport,
    rows,
    expenseAccounts,
    accountGroups,
    salesTaxCategories,
    purchaseTaxCategories,
    hasActiveFiscalYear,
    importSummary,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set(rows.map((r) => r.id)));
    const [accountSelections, setAccountSelections] = useState<Record<number, string>>(() => initialAccountSelections(rows));
    const [taxCategories, setTaxCategories] = useState<Record<number, string>>(() => initialTaxCategories(rows));
    const [qualifiedInvoices, setQualifiedInvoices] = useState<Record<number, boolean>>(() => initialQualifiedInvoices(rows));

    const [processing, setProcessing] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const errorMessages = useMemo(
        () => Object.values(formErrors).filter((message): message is string => Boolean(message)),
        [formErrors],
    );

    const missingAccountRowIds = useMemo(() => {
        return new Set(
            rows
                .filter((row) => !row.is_deposit && selectedIds.has(row.id) && !accountSelections[row.id])
                .map((row) => row.id),
        );
    }, [rows, selectedIds, accountSelections]);

    const toggleRow = (id: number, checked: boolean) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }
            return next;
        });
    };

    const toggleAll = (checked: boolean) => {
        setSelectedIds(checked ? new Set(rows.map((r) => r.id)) : new Set());
    };

    const handleAccountChange = (rowId: number, accountId: string) => {
        setAccountSelections((prev) => ({ ...prev, [rowId]: accountId }));
        setTaxCategories((prev) => ({
            ...prev,
            [rowId]: defaultCategoryForAccount(Number(accountId), expenseAccounts, 'taxable_purchase_10'),
        }));
    };

    const handleSkip = (rowId: number) => {
        router.post(route('bank-import.rows.skip', rowId), {}, { preserveScroll: true });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const confirmations = rows
            .filter((row) => selectedIds.has(row.id))
            .map((row) => ({
                row_id: row.id,
                consumption_tax_category: taxCategories[row.id],
                has_qualified_invoice: qualifiedInvoices[row.id] ?? true,
                ...(row.is_deposit ? {} : { account_id: Number(accountSelections[row.id]) }),
            }));

        setProcessing(true);
        setFormErrors({});
        router.post(route('bank-import.confirm', bankImport.id), { rows: confirmations }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onError: (errors) => setFormErrors(errors as Record<string, string>),
        });
    };

    const [rowFilter, setRowFilter] = useState<'all' | 'deposit' | 'withdrawal' | 'missing'>('all');

    const filteredRows = useMemo(() => {
        return rows.filter((row) => {
            if (rowFilter === 'deposit') return row.is_deposit;
            if (rowFilter === 'withdrawal') return !row.is_deposit;
            if (rowFilter === 'missing') return missingAccountRowIds.has(row.id);
            return true;
        });
    }, [rows, rowFilter, missingAccountRowIds]);

    const allSelected = rows.length > 0 && selectedIds.size === rows.length;

    const selectedTotal = useMemo(() => {
        return rows
            .filter((row) => selectedIds.has(row.id))
            .reduce((sum, row) => sum + (row.is_deposit ? row.deposit_amount : row.withdrawal_amount), 0);
    }, [rows, selectedIds]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="取引確認" />
            <PageContainer size="full">
                <PageHeader
                    title="取引確認"
                    description={`${bankImport.original_filename} — 記帳内容を確認してください`}
                />

                <FlashAlert />

                <WorkflowSteps steps={bankImportSteps} currentStep="review" />

                <SummaryStrip
                    items={[
                        { label: '全件', value: `${rows.length}件` },
                        { label: '選択', value: `${selectedIds.size}件`, highlight: true },
                        { label: '選択合計', value: formatAmount(selectedTotal) },
                        {
                            label: '科目未選択',
                            value: `${missingAccountRowIds.size}件`,
                            variant: missingAccountRowIds.size > 0 ? 'warning' : 'default',
                        },
                        ...(importSummary?.detected_format
                            ? [{ label: '判別形式', value: importSummary.detected_format }]
                            : []),
                    ]}
                />

                {rows.length === 0 ? (
                    <div className="text-muted-foreground py-12 text-center text-sm">確認待ちの取引はありません</div>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        {errorMessages.length > 0 && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    <ul className="list-disc space-y-1 pl-4">
                                        {errorMessages.map((message) => (
                                            <li key={message}>{message}</li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}

                        <FilterToolbar sticky={false}>
                            {(
                                [
                                    ['all', 'すべて'],
                                    ['deposit', '入金のみ'],
                                    ['withdrawal', '出金のみ'],
                                    ['missing', '科目未選択'],
                                ] as const
                            ).map(([key, label]) => (
                                <Button
                                    key={key}
                                    type="button"
                                    size="sm"
                                    variant={rowFilter === key ? 'default' : 'outline'}
                                    onClick={() => setRowFilter(key)}
                                >
                                    {label}
                                </Button>
                            ))}
                        </FilterToolbar>

                        <DataTable>
                            <DataTableHeader>
                                <TableRow>
                                    <TableHead className="w-10">
                                        <Checkbox checked={allSelected} onCheckedChange={(c) => toggleAll(c === true)} />
                                    </TableHead>
                                    <TableHead>日付</TableHead>
                                    <TableHead>摘要</TableHead>
                                    <TableHead className="text-right">金額</TableHead>
                                    <TableHead>記帳方法</TableHead>
                                    <TableHead>税区分</TableHead>
                                    <TableHead className="text-right">操作</TableHead>
                                </TableRow>
                            </DataTableHeader>
                            <TableBody>
                                {filteredRows.map((row) => {
                                    const amount = row.is_deposit ? row.deposit_amount : row.withdrawal_amount;
                                    const categoryOptions = row.is_deposit ? salesTaxCategories : purchaseTaxCategories;

                                    return (
                                        <TableRow
                                            key={row.id}
                                            className={missingAccountRowIds.has(row.id) ? 'bg-red-50 dark:bg-red-950/20' : ''}
                                        >
                                            <TableCell>
                                                <Checkbox
                                                    checked={selectedIds.has(row.id)}
                                                    onCheckedChange={(c) => toggleRow(row.id, c === true)}
                                                />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">{formatDate(row.transaction_date)}</TableCell>
                                            <TableCell>{row.description}</TableCell>
                                            <TableCell className="text-right whitespace-nowrap tabular-nums">
                                                <span className={row.is_deposit ? 'text-green-700 dark:text-green-400' : ''}>
                                                    {row.is_deposit ? '+' : '-'}
                                                    {formatAmount(amount)}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {row.is_deposit ? (
                                                    <Badge variant="secondary">売上として記帳</Badge>
                                                ) : (
                                                    <div className="flex flex-col gap-1">
                                                        <Select
                                                            value={accountSelections[row.id] ?? ''}
                                                            onValueChange={(v) => handleAccountChange(row.id, v)}
                                                        >
                                                            <SelectTrigger
                                                                className={`w-48 ${missingAccountRowIds.has(row.id) ? 'border-red-500' : ''}`}
                                                            >
                                                                <SelectValue placeholder="経費科目を選択（必須）" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {expenseAccounts.map((account) => (
                                                                    <SelectItem key={account.id} value={String(account.id)}>
                                                                        {account.name}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                        {row.suggested_account_id &&
                                                            accountSelections[row.id] === String(row.suggested_account_id) && (
                                                                <span className="text-muted-foreground text-xs">自動提案</span>
                                                            )}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="min-w-56 space-y-2">
                                                    <Select
                                                        value={taxCategories[row.id]}
                                                        onValueChange={(v) =>
                                                            setTaxCategories((prev) => ({ ...prev, [row.id]: v }))
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {categoryOptions.map((option) => (
                                                                <SelectItem key={option.value} value={option.value}>
                                                                    {option.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    {!row.is_deposit && (
                                                        <label className="flex items-center gap-2 text-xs">
                                                            <Checkbox
                                                                checked={qualifiedInvoices[row.id] ?? true}
                                                                onCheckedChange={(checked) =>
                                                                    setQualifiedInvoices((prev) => ({
                                                                        ...prev,
                                                                        [row.id]: checked === true,
                                                                    }))
                                                                }
                                                            />
                                                            適格請求書あり
                                                        </label>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <BankImportRowEditDialog
                                                        rowId={row.id}
                                                        isDeposit={row.is_deposit}
                                                        initialValues={{
                                                            transaction_date: row.transaction_date,
                                                            description: row.description,
                                                            amount: row.amount,
                                                            account_id: row.account_id ?? null,
                                                            consumption_tax_category: taxCategories[row.id],
                                                            has_qualified_invoice: qualifiedInvoices[row.id] ?? true,
                                                        }}
                                                        accountGroups={accountGroups}
                                                        expenseAccounts={expenseAccounts}
                                                        salesTaxCategories={salesTaxCategories}
                                                        purchaseTaxCategories={purchaseTaxCategories}
                                                        hasActiveFiscalYear={hasActiveFiscalYear}
                                                        trigger={
                                                            <Button type="button" variant="ghost" size="sm">
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => handleSkip(row.id)}>
                                                        スキップ
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </DataTable>

                        <StickyActionBar>
                            <span className="text-muted-foreground text-sm">
                                <span className="text-foreground font-semibold">{selectedIds.size}件</span>を選択中
                            </span>
                            <Button type="submit" disabled={processing || selectedIds.size === 0}>
                                {processing ? '記帳中...' : `選択した${selectedIds.size}件を一括記帳`}
                            </Button>
                        </StickyActionBar>
                    </form>
                )}
            </PageContainer>
        </AppLayout>
    );
}
