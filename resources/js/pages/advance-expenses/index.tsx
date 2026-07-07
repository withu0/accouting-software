import ConsumptionTaxFields, { defaultCategoryForAccount, type TaxCategoryOption } from '@/components/consumption-tax-fields';
import { DataTable, DataTableHeader } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SectionHeader } from '@/components/section-header';
import { SplitWorkspace } from '@/components/split-workspace';
import { SummaryStrip } from '@/components/summary-strip';
import ReceiptScanUpload from '@/components/receipt-scan-upload';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Pencil, Receipt, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

interface ExpenseAccount {
    id: number;
    name: string;
    default_consumption_tax_category?: string | null;
}

interface AdvanceExpenseEntry {
    id: number;
    entry_date: string;
    description: string;
    amount: number;
    account_id: number;
    account_name: string;
    consumption_tax_category?: string | null;
    has_qualified_invoice?: boolean | null;
}

interface Props {
    entries: AdvanceExpenseEntry[];
    expenseAccounts: ExpenseAccount[];
    purchaseTaxCategories: TaxCategoryOption[];
    hasActiveFiscalYear: boolean;
    receiptScanAvailable: boolean;
}

interface ReceiptScanResult {
    entry_date: string | null;
    amount: number | null;
    merchant_name: string | null;
    consumption_tax_category: string | null;
    confidence: {
        date: number | null;
        amount: number | null;
        consumption_tax_category: number | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '立替経費入力', href: route('advance-expenses') },
];

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

function AdvanceExpenseEditDialog({
    entry,
    expenseAccounts,
    purchaseTaxCategories,
    hasActiveFiscalYear,
}: {
    entry: AdvanceExpenseEntry;
    expenseAccounts: ExpenseAccount[];
    purchaseTaxCategories: TaxCategoryOption[];
    hasActiveFiscalYear: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [entryDate, setEntryDate] = useState(entry.entry_date);
    const [description, setDescription] = useState(entry.description);
    const [amount, setAmount] = useState(String(entry.amount));
    const [accountId, setAccountId] = useState(String(entry.account_id));
    const [consumptionTaxCategory, setConsumptionTaxCategory] = useState(
        entry.consumption_tax_category ?? 'taxable_purchase_10',
    );
    const [hasQualifiedInvoice, setHasQualifiedInvoice] = useState(entry.has_qualified_invoice ?? true);

    useEffect(() => {
        if (open) {
            setEntryDate(entry.entry_date);
            setDescription(entry.description);
            setAmount(String(entry.amount));
            setAccountId(String(entry.account_id));
            setConsumptionTaxCategory(entry.consumption_tax_category ?? 'taxable_purchase_10');
            setHasQualifiedInvoice(entry.has_qualified_invoice ?? true);
            setErrors({});
        }
    }, [open, entry]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        setProcessing(true);
        setErrors({});

        router.patch(
            route('advance-expenses.update', entry.id),
            {
                entry_date: entryDate,
                description,
                amount: parseInt(amount, 10),
                account_id: parseInt(accountId, 10),
                consumption_tax_category: consumptionTaxCategory,
                has_qualified_invoice: hasQualifiedInvoice,
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (formErrors) => setErrors(formErrors as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>立替経費を編集</DialogTitle>
                <DialogDescription>日付・金額・摘要・経費科目を変更します。</DialogDescription>

                {!hasActiveFiscalYear && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>会計期間が未設定のため編集できません。</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`edit-date-${entry.id}`}>日付</Label>
                        <Input
                            id={`edit-date-${entry.id}`}
                            type="date"
                            value={entryDate}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setEntryDate(e.target.value)}
                        />
                        <InputError message={errors.entry_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-amount-${entry.id}`}>金額</Label>
                        <Input
                            id={`edit-amount-${entry.id}`}
                            type="number"
                            min={1}
                            value={amount}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-description-${entry.id}`}>摘要</Label>
                        <Input
                            id={`edit-description-${entry.id}`}
                            value={description}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setDescription(e.target.value)}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-account-${entry.id}`}>経費科目</Label>
                        <Select
                            value={accountId}
                            onValueChange={(value) => {
                                setAccountId(value);
                                setConsumptionTaxCategory(
                                    defaultCategoryForAccount(Number(value), expenseAccounts, 'taxable_purchase_10'),
                                );
                            }}
                            disabled={!hasActiveFiscalYear || processing}
                        >
                            <SelectTrigger id={`edit-account-${entry.id}`}>
                                <SelectValue placeholder="経費科目を選択" />
                            </SelectTrigger>
                            <SelectContent>
                                {expenseAccounts.map((account) => (
                                    <SelectItem key={account.id} value={String(account.id)}>
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.account_id} />
                    </div>

                    <ConsumptionTaxFields
                        idPrefix={`edit-tax-${entry.id}`}
                        category={consumptionTaxCategory}
                        hasQualifiedInvoice={hasQualifiedInvoice}
                        categoryOptions={purchaseTaxCategories}
                        onCategoryChange={setConsumptionTaxCategory}
                        onQualifiedInvoiceChange={setHasQualifiedInvoice}
                        categoryError={errors.consumption_tax_category}
                        qualifiedInvoiceError={errors.has_qualified_invoice}
                    />

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline" disabled={processing}>
                                キャンセル
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={!hasActiveFiscalYear || processing || !accountId}>
                            {processing ? '保存中...' : '保存する'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdvanceExpensesIndex({
    entries,
    expenseAccounts,
    purchaseTaxCategories,
    hasActiveFiscalYear,
    receiptScanAvailable,
}: Props) {
    const { flash, errors: pageErrors } = usePage<
        SharedData & {
            flash?: {
                success?: string;
                receiptScan?: ReceiptScanResult;
            };
            errors?: Record<string, string>;
        }
    >().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        entry_date: '',
        amount: '',
        description: '',
        account_id: '',
        consumption_tax_category: 'taxable_purchase_10',
        has_qualified_invoice: true,
    });

    const [scanNotice, setScanNotice] = useState<string | null>(null);

    useEffect(() => {
        const scan = flash?.receiptScan;
        if (!scan) {
            return;
        }

        if (scan.entry_date) {
            setData('entry_date', scan.entry_date);
        }
        if (scan.amount != null) {
            setData('amount', String(scan.amount));
        }
        if (scan.merchant_name) {
            setData('description', scan.merchant_name);
        }
        if (scan.consumption_tax_category) {
            setData('consumption_tax_category', scan.consumption_tax_category);
        }

        const lowConfidence =
            (scan.entry_date != null && scan.confidence.date != null && scan.confidence.date < 0.7) ||
            (scan.amount != null && scan.confidence.amount != null && scan.confidence.amount < 0.7) ||
            (scan.consumption_tax_category != null &&
                scan.confidence.consumption_tax_category != null &&
                scan.confidence.consumption_tax_category < 0.7);

        if (scan.entry_date == null || scan.amount == null) {
            setScanNotice('一部の項目を読み取れませんでした。内容を確認してから登録してください。');
        } else if (lowConfidence) {
            setScanNotice('読み取り結果の信頼度が低い可能性があります。内容を確認してください。');
        } else {
            setScanNotice('領収書から読み取った内容をフォームに反映しました。内容を確認してから登録してください。');
        }
    }, [flash?.receiptScan, setData]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('advance-expenses.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setScanNotice(null);
            },
        });
    };

    const monthTotals = useMemo(() => {
        const totals = new Map<string, number>();
        for (const entry of entries) {
            const month = entry.entry_date.slice(0, 7);
            totals.set(month, (totals.get(month) ?? 0) + entry.amount);
        }
        return Array.from(totals.entries()).sort((a, b) => b[0].localeCompare(a[0]));
    }, [entries]);

    const totalAmount = useMemo(() => entries.reduce((sum, e) => sum + e.amount, 0), [entries]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="立替経費入力" />
            <PageContainer size="full">
                <PageHeader title="立替経費入力" description="社長個人が支払った経費を登録します" />

                <FlashAlert />

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

                <SplitWorkspace
                    left={
                        <FormSection
                            title="立替経費を登録"
                            description="領収書画像をアップロードし、領収書部分を切り取って確認すると日付・金額・税区分を自動入力できます。経費科目などは手入力してください。"
                        >
                            <form onSubmit={submit} className="space-y-4">
                                <ReceiptScanUpload
                                    available={receiptScanAvailable}
                                    disabled={!hasActiveFiscalYear || processing}
                                    error={pageErrors?.receipt}
                                />

                                {scanNotice && (
                                    <Alert>
                                        <AlertCircle className="size-4" />
                                        <AlertDescription>{scanNotice}</AlertDescription>
                                    </Alert>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="entry_date">日付</Label>
                                    <Input
                                        id="entry_date"
                                        type="date"
                                        value={data.entry_date}
                                        disabled={!hasActiveFiscalYear || processing}
                                        onChange={(e) => setData('entry_date', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.entry_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="amount">金額</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        min="1"
                                        value={data.amount}
                                        disabled={!hasActiveFiscalYear || processing}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">摘要</Label>
                                    <Input
                                        id="description"
                                        type="text"
                                        value={data.description}
                                        disabled={!hasActiveFiscalYear || processing}
                                        onChange={(e) => setData('description', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="account_id">経費科目</Label>
                                    <Select
                                        value={data.account_id}
                                        onValueChange={(v) => {
                                            setData({
                                                ...data,
                                                account_id: v,
                                                consumption_tax_category: defaultCategoryForAccount(
                                                    Number(v),
                                                    expenseAccounts,
                                                    'taxable_purchase_10',
                                                ),
                                            });
                                        }}
                                        disabled={!hasActiveFiscalYear || processing}
                                    >
                                        <SelectTrigger id="account_id">
                                            <SelectValue placeholder="経費科目を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {expenseAccounts.map((account) => (
                                                <SelectItem key={account.id} value={String(account.id)}>
                                                    {account.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.account_id} />
                                </div>

                                <ConsumptionTaxFields
                                    idPrefix="create-tax"
                                    category={data.consumption_tax_category}
                                    hasQualifiedInvoice={data.has_qualified_invoice}
                                    categoryOptions={purchaseTaxCategories}
                                    onCategoryChange={(value) => setData('consumption_tax_category', value)}
                                    onQualifiedInvoiceChange={(value) => setData('has_qualified_invoice', value)}
                                    categoryError={errors.consumption_tax_category}
                                    qualifiedInvoiceError={errors.has_qualified_invoice}
                                />

                                <Button type="submit" disabled={!hasActiveFiscalYear || processing}>
                                    {processing ? '登録中...' : '登録する'}
                                </Button>
                            </form>
                        </FormSection>
                    }
                    right={
                        <>
                            <SectionHeader
                                title="登録済みの立替経費"
                                description={entries.length > 0 ? `${entries.length}件の立替経費が登録されています` : undefined}
                            />

                            {entries.length > 0 && (
                                <SummaryStrip
                                    items={[
                                        { label: '件数', value: `${entries.length}件` },
                                        { label: '合計金額', value: formatAmount(totalAmount), highlight: true },
                                        ...monthTotals.slice(0, 2).map(([month, amount]) => ({
                                            label: month.replace('-', '年') + '月',
                                            value: formatAmount(amount),
                                        })),
                                    ]}
                                />
                            )}

                            {entries.length === 0 ? (
                                <EmptyState
                                    icon={Receipt}
                                    title="登録された立替経費はありません"
                                    description="左のフォームから立替経費を登録するか、領収書をスキャンして自動入力できます。"
                                />
                            ) : (
                                <DataTable>
                                <DataTableHeader>
                                    <TableRow>
                                        <TableHead>日付</TableHead>
                                        <TableHead>摘要</TableHead>
                                        <TableHead>経費科目</TableHead>
                                        <TableHead className="text-right">金額</TableHead>
                                        <TableHead className="text-right">操作</TableHead>
                                    </TableRow>
                                </DataTableHeader>
                                <TableBody>
                                    {entries.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="whitespace-nowrap">{formatDate(entry.entry_date)}</TableCell>
                                            <TableCell>{entry.description}</TableCell>
                                            <TableCell>{entry.account_name}</TableCell>
                                            <TableCell className="text-right whitespace-nowrap tabular-nums">
                                                {formatAmount(entry.amount)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <AdvanceExpenseEditDialog
                                                        entry={entry}
                                                        expenseAccounts={expenseAccounts}
                                                        purchaseTaxCategories={purchaseTaxCategories}
                                                        hasActiveFiscalYear={hasActiveFiscalYear}
                                                    />
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogTitle>立替経費を削除しますか？</DialogTitle>
                                                            <DialogDescription>
                                                                {formatDate(entry.entry_date)} — {entry.description}（{formatAmount(entry.amount)}）を削除します。この操作は取り消せません。
                                                            </DialogDescription>
                                                            <DialogFooter>
                                                                <DialogClose asChild>
                                                                    <Button variant="outline">キャンセル</Button>
                                                                </DialogClose>
                                                                <Button
                                                                    variant="destructive"
                                                                    onClick={() => {
                                                                        router.delete(route('advance-expenses.destroy', entry.id), {
                                                                            preserveScroll: true,
                                                                        });
                                                                    }}
                                                                >
                                                                    削除する
                                                                </Button>
                                                            </DialogFooter>
                                                        </DialogContent>
                                                    </Dialog>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                </DataTable>
                            )}
                        </>
                    }
                />
            </PageContainer>
        </AppLayout>
    );
}
