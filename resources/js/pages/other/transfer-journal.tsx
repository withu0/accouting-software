import { type TaxCategoryOption } from '@/components/consumption-tax-fields';
import InputError from '@/components/input-error';
import TransferJournalRowTable, {
    createEmptyRow,
    flattenRowsToLines,
    sumRowTotals,
    type TransferJournalRow,
} from '@/components/transfer-journal-row-table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Receipt, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface TransferPreset {
    id: string;
    label: string;
    debit_account_id: number;
    credit_account_id: number;
    description: string;
}

interface TransferEntryLine {
    account_name: string;
    debit: number;
    credit: number;
    consumption_tax_category?: string | null;
}

interface TransferEntry {
    id: number;
    entry_date: string;
    description: string;
    amount: number;
    lines: TransferEntryLine[];
}

interface Props {
    entries: TransferEntry[];
    accountGroups: Record<string, { id: number; name: string; default_consumption_tax_category?: string | null }[]>;
    presets: TransferPreset[];
    transferTaxCategories: TaxCategoryOption[];
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'その他', href: route('other') },
    { title: '臨時仕訳（振替伝票）', href: route('transfer-journal.index') },
];

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

function taxCategoryLabel(value: string | null | undefined, options: TaxCategoryOption[]): string {
    if (!value) {
        return '—';
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

export default function TransferJournal({
    entries,
    accountGroups,
    presets,
    transferTaxCategories,
    hasActiveFiscalYear,
}: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;
    const [rows, setRows] = useState<TransferJournalRow[]>([createEmptyRow()]);

    const { data, setData, post, processing, errors, reset, transform } = useForm({
        entry_date: '',
        description: '',
        lines: [] as Array<{
            account_id: number;
            debit: number;
            credit: number;
            consumption_tax_category: string;
        }>,
    });

    transform((formData) => ({
        ...formData,
        lines: flattenRowsToLines(rows),
    }));

    const { debitTotal, creditTotal } = useMemo(() => sumRowTotals(rows), [rows]);

    const amountsMismatch = useMemo(() => {
        if (debitTotal === 0 && creditTotal === 0) {
            return false;
        }

        return debitTotal !== creditTotal;
    }, [debitTotal, creditTotal]);

    const shortageAmount = useMemo(() => {
        if (!amountsMismatch) {
            return 0;
        }

        return Math.abs(debitTotal - creditTotal);
    }, [amountsMismatch, debitTotal, creditTotal]);

    const applyPreset = (preset: TransferPreset) => {
        setRows([
            {
                key: crypto.randomUUID(),
                debit: {
                    account_id: String(preset.debit_account_id),
                    amount: '',
                    consumption_tax_category: 'out_of_scope',
                },
                credit: {
                    account_id: String(preset.credit_account_id),
                    amount: '',
                    consumption_tax_category: 'out_of_scope',
                },
            },
        ]);
        setData({
            ...data,
            description: preset.description,
        });
    };

    const resetForm = () => {
        reset();
        setRows([createEmptyRow()]);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('transfer-journal.store'), {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="臨時仕訳（振替伝票）" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">臨時仕訳（振替伝票）</h1>
                    <p className="text-muted-foreground mt-1 text-sm">通常処理で対応できない例外仕訳を入力します</p>
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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Receipt className="size-5" />
                            振替伝票を登録
                        </CardTitle>
                        <CardDescription>発生日・借方・貸方・摘要を入力してください</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {presets.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {presets.map((preset) => (
                                    <Button
                                        key={preset.id}
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={!hasActiveFiscalYear || processing}
                                        onClick={() => applyPreset(preset)}
                                    >
                                        {preset.label}
                                    </Button>
                                ))}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2 max-w-xs">
                                <Label htmlFor="entry_date">発生日</Label>
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

                            <TransferJournalRowTable
                                rows={rows}
                                accountGroups={accountGroups}
                                transferTaxCategories={transferTaxCategories}
                                disabled={!hasActiveFiscalYear || processing}
                                lineErrors={
                                    typeof errors.lines === 'string'
                                        ? { lines: errors.lines }
                                        : Object.fromEntries(
                                              Object.entries(errors).filter(([key]) => key.startsWith('lines.')),
                                          )
                                }
                                onChange={setRows}
                            />

                            <div className="flex flex-wrap items-center gap-4 text-sm">
                                <span>
                                    借方合計: <strong>{formatAmount(debitTotal)}</strong>
                                </span>
                                <span>
                                    貸方合計: <strong>{formatAmount(creditTotal)}</strong>
                                </span>
                                {amountsMismatch && (
                                    <span className="text-destructive font-medium">不足額: {formatAmount(shortageAmount)}</span>
                                )}
                            </div>

                            {amountsMismatch && (
                                <Alert variant="destructive">
                                    <AlertCircle className="size-4" />
                                    <AlertDescription>借方合計と貸方合計が一致していません。</AlertDescription>
                                </Alert>
                            )}

                            <div className="grid gap-2 max-w-xl">
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

                            <Button
                                type="submit"
                                disabled={!hasActiveFiscalYear || processing || amountsMismatch || debitTotal === 0}
                            >
                                {processing ? '登録中...' : '登録する'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div>
                    <h2 className="mb-4 text-lg font-semibold">登録済みの振替伝票</h2>
                    {entries.length === 0 ? (
                        <div className="text-muted-foreground py-8 text-center text-sm">登録された振替伝票はありません</div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="px-4 py-3 text-left font-medium">日付</th>
                                        <th className="px-4 py-3 text-left font-medium">摘要</th>
                                        <th className="px-4 py-3 text-left font-medium">仕訳明細</th>
                                        <th className="px-4 py-3 text-right font-medium">合計</th>
                                        <th className="px-4 py-3 text-right font-medium">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entries.map((entry) => (
                                        <tr key={entry.id} className="border-b align-top last:border-0">
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(entry.entry_date)}</td>
                                            <td className="px-4 py-3">{entry.description}</td>
                                            <td className="px-4 py-3">
                                                <div className="space-y-2">
                                                    {entry.lines.map((line, lineIndex) => (
                                                        <div key={`${entry.id}-${lineIndex}`} className="grid gap-1">
                                                            {line.debit > 0 && (
                                                                <span>
                                                                    借 {line.account_name} {formatAmount(line.debit)}
                                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                                        {taxCategoryLabel(
                                                                            line.consumption_tax_category,
                                                                            transferTaxCategories,
                                                                        )}
                                                                    </span>
                                                                </span>
                                                            )}
                                                            {line.credit > 0 && (
                                                                <span>
                                                                    貸 {line.account_name} {formatAmount(line.credit)}
                                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                                        {taxCategoryLabel(
                                                                            line.consumption_tax_category,
                                                                            transferTaxCategories,
                                                                        )}
                                                                    </span>
                                                                </span>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">{formatAmount(entry.amount)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Dialog>
                                                    <DialogTrigger asChild>
                                                        <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogTitle>振替伝票を削除しますか？</DialogTitle>
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
                                                                    router.delete(route('transfer-journal.destroy', entry.id), {
                                                                        preserveScroll: true,
                                                                    });
                                                                }}
                                                            >
                                                                削除する
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
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
