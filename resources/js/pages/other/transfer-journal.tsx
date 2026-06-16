import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Receipt, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo } from 'react';

interface TransferPreset {
    id: string;
    label: string;
    debit_account_id: number;
    credit_account_id: number;
    description: string;
}

interface TransferEntry {
    id: number;
    entry_date: string;
    description: string;
    amount: number;
    debit_account_name: string;
    credit_account_name: string;
}

interface Props {
    entries: TransferEntry[];
    accountGroups: Record<string, { id: number; name: string }[]>;
    presets: TransferPreset[];
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

export default function TransferJournal({ entries, accountGroups, presets, hasActiveFiscalYear }: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        entry_date: '',
        debit_account_id: '',
        debit_amount: '',
        credit_account_id: '',
        credit_amount: '',
        description: '',
    });

    const amountsMismatch = useMemo(() => {
        const debit = parseInt(data.debit_amount, 10);
        const credit = parseInt(data.credit_amount, 10);
        if (!data.debit_amount || !data.credit_amount) {
            return false;
        }
        return debit !== credit;
    }, [data.debit_amount, data.credit_amount]);

    const applyPreset = (preset: TransferPreset) => {
        setData({
            ...data,
            debit_account_id: String(preset.debit_account_id),
            credit_account_id: String(preset.credit_account_id),
            description: preset.description,
            debit_amount: '',
            credit_amount: '',
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('transfer-journal.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
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

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Receipt className="size-5" />
                            振替伝票を登録
                        </CardTitle>
                        <CardDescription>日付・借方・貸方・摘要を入力してください</CardDescription>
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

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="debit_account_id">借方科目</Label>
                                    <Select
                                        value={data.debit_account_id}
                                        onValueChange={(v) => setData('debit_account_id', v)}
                                        disabled={!hasActiveFiscalYear || processing}
                                    >
                                        <SelectTrigger id="debit_account_id">
                                            <SelectValue placeholder="借方科目を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(accountGroups).map(([groupLabel, accounts]) => (
                                                <SelectGroup key={groupLabel}>
                                                    <SelectLabel>{groupLabel}</SelectLabel>
                                                    {accounts.map((account) => (
                                                        <SelectItem key={account.id} value={String(account.id)}>
                                                            {account.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.debit_account_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="debit_amount">借方金額</Label>
                                    <Input
                                        id="debit_amount"
                                        type="number"
                                        min="1"
                                        value={data.debit_amount}
                                        disabled={!hasActiveFiscalYear || processing}
                                        onChange={(e) => setData('debit_amount', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.debit_amount} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="credit_account_id">貸方科目</Label>
                                    <Select
                                        value={data.credit_account_id}
                                        onValueChange={(v) => setData('credit_account_id', v)}
                                        disabled={!hasActiveFiscalYear || processing}
                                    >
                                        <SelectTrigger id="credit_account_id">
                                            <SelectValue placeholder="貸方科目を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(accountGroups).map(([groupLabel, accounts]) => (
                                                <SelectGroup key={groupLabel}>
                                                    <SelectLabel>{groupLabel}</SelectLabel>
                                                    {accounts.map((account) => (
                                                        <SelectItem key={account.id} value={String(account.id)}>
                                                            {account.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.credit_account_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="credit_amount">貸方金額</Label>
                                    <Input
                                        id="credit_amount"
                                        type="number"
                                        min="1"
                                        value={data.credit_amount}
                                        disabled={!hasActiveFiscalYear || processing}
                                        onChange={(e) => setData('credit_amount', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.credit_amount} />
                                </div>
                            </div>

                            {amountsMismatch && (
                                <Alert variant="destructive">
                                    <AlertCircle className="size-4" />
                                    <AlertDescription>借方金額と貸方金額が一致していません。</AlertDescription>
                                </Alert>
                            )}

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

                            <Button type="submit" disabled={!hasActiveFiscalYear || processing || amountsMismatch}>
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
                                        <th className="px-4 py-3 text-left font-medium">借方科目</th>
                                        <th className="px-4 py-3 text-left font-medium">貸方科目</th>
                                        <th className="px-4 py-3 text-right font-medium">金額</th>
                                        <th className="px-4 py-3 text-right font-medium">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entries.map((entry) => (
                                        <tr key={entry.id} className="border-b last:border-0">
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(entry.entry_date)}</td>
                                            <td className="px-4 py-3">{entry.description}</td>
                                            <td className="px-4 py-3">{entry.debit_account_name}</td>
                                            <td className="px-4 py-3">{entry.credit_account_name}</td>
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
