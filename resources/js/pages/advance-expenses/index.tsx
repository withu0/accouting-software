import ConsumptionTaxFields, { defaultCategoryForAccount, type TaxCategoryOption } from '@/components/consumption-tax-fields';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Pencil, Trash2, Wallet } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

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
}: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        entry_date: '',
        amount: '',
        description: '',
        account_id: '',
        consumption_tax_category: 'taxable_purchase_10',
        has_qualified_invoice: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('advance-expenses.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="立替経費入力" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">立替経費入力</h1>
                    <p className="text-muted-foreground mt-1 text-sm">社長個人が支払った経費を登録します</p>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

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

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Wallet className="size-5" />
                            立替経費を登録
                        </CardTitle>
                        <CardDescription>日付・金額・摘要・経費科目を入力してください</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
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
                    </CardContent>
                </Card>

                <div>
                    <h2 className="mb-4 text-lg font-semibold">登録済みの立替経費</h2>
                    {entries.length === 0 ? (
                        <div className="text-muted-foreground py-8 text-center text-sm">登録された立替経費はありません</div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="px-4 py-3 text-left font-medium">日付</th>
                                        <th className="px-4 py-3 text-left font-medium">摘要</th>
                                        <th className="px-4 py-3 text-left font-medium">経費科目</th>
                                        <th className="px-4 py-3 text-right font-medium">金額</th>
                                        <th className="px-4 py-3 text-right font-medium">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entries.map((entry) => (
                                        <tr key={entry.id} className="border-b last:border-0">
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(entry.entry_date)}</td>
                                            <td className="px-4 py-3">{entry.description}</td>
                                            <td className="px-4 py-3">{entry.account_name}</td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">{formatAmount(entry.amount)}</td>
                                            <td className="px-4 py-3 text-right">
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
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
