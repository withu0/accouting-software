import { Alert, AlertDescription } from '@/components/ui/alert';
import BankImportRowEditDialog from '@/components/bank-import-row-edit-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Pencil } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface ExpenseAccount {
    id: number;
    name: string;
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
    accountGroups: Record<string, { id: number; name: string }[]>;
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

export default function BankImportReview({
    bankImport,
    rows,
    expenseAccounts,
    accountGroups,
    hasActiveFiscalYear,
    importSummary,
}: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set(rows.map((r) => r.id)));
    const [accountSelections, setAccountSelections] = useState<Record<number, string>>(() => initialAccountSelections(rows));

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

    const allSelected = rows.length > 0 && selectedIds.size === rows.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="取引確認" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">取引確認</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {bankImport.original_filename} — 記帳内容を確認してください
                    </p>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {importSummary && (
                    <div className="bg-muted/50 rounded-lg border px-4 py-3 text-sm">
                        {importSummary.detected_format && (
                            <p className="mb-1 font-medium">判別形式: {importSummary.detected_format}</p>
                        )}
                        <span>
                            {importSummary.total}件中 {importSummary.new}件を取込
                            {importSummary.duplicates > 0 && `（${importSummary.duplicates}件は重複のためスキップ）`}
                            {(importSummary.out_of_period ?? 0) > 0 &&
                                `（${importSummary.out_of_period}件は会計期間外のためスキップ）`}
                        </span>
                    </div>
                )}

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

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="px-4 py-3 text-left">
                                            <Checkbox checked={allSelected} onCheckedChange={(c) => toggleAll(c === true)} />
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">日付</th>
                                        <th className="px-4 py-3 text-left font-medium">摘要</th>
                                        <th className="px-4 py-3 text-right font-medium">金額</th>
                                        <th className="px-4 py-3 text-left font-medium">記帳方法</th>
                                        <th className="px-4 py-3 text-right font-medium">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => {
                                        const amount = row.is_deposit ? row.deposit_amount : row.withdrawal_amount;

                                        return (
                                            <tr
                                                key={row.id}
                                                className={`border-b last:border-0 ${missingAccountRowIds.has(row.id) ? 'bg-red-50 dark:bg-red-950/20' : ''}`}
                                            >
                                                <td className="px-4 py-3">
                                                    <Checkbox
                                                        checked={selectedIds.has(row.id)}
                                                        onCheckedChange={(c) => toggleRow(row.id, c === true)}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">{formatDate(row.transaction_date)}</td>
                                                <td className="px-4 py-3">{row.description}</td>
                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                    <span className={row.is_deposit ? 'text-green-700 dark:text-green-400' : ''}>
                                                        {row.is_deposit ? '+' : '-'}
                                                        {formatAmount(amount)}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
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
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <BankImportRowEditDialog
                                                            rowId={row.id}
                                                            isDeposit={row.is_deposit}
                                                            initialValues={{
                                                                transaction_date: row.transaction_date,
                                                                description: row.description,
                                                                amount: row.amount,
                                                                account_id: row.account_id ?? null,
                                                            }}
                                                            accountGroups={accountGroups}
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
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing || selectedIds.size === 0}>
                                {processing ? '記帳中...' : `選択した${selectedIds.size}件を一括記帳`}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
