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
import { AlertCircle, CheckCircle2, Trash2, Wallet } from 'lucide-react';
import { FormEventHandler } from 'react';

interface ExpenseAccount {
    id: number;
    name: string;
}

interface AdvanceExpenseEntry {
    id: number;
    entry_date: string;
    description: string;
    amount: number;
    account_name: string;
}

interface Props {
    entries: AdvanceExpenseEntry[];
    expenseAccounts: ExpenseAccount[];
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '立替経費入力', href: route('advance-expenses') },
];

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

export default function AdvanceExpensesIndex({ entries, expenseAccounts, hasActiveFiscalYear }: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        entry_date: '',
        amount: '',
        description: '',
        account_id: '',
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
                                    onValueChange={(v) => setData('account_id', v)}
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
