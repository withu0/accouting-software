import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Pencil, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface AccountItem {
    id: number;
    code: string;
    name: string;
    type: string;
    display_order: number;
    default_consumption_tax_category?: string | null;
    is_in_use: boolean;
}

interface TaxCategoryOption {
    value: string;
    label: string;
}

interface AccountTypeOption {
    value: string;
    label: string;
}

interface Props {
    accountGroups: Record<string, AccountItem[]>;
    accountTypes: AccountTypeOption[];
    taxCategoryOptions: Record<string, TaxCategoryOption[]>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'その他', href: route('other') },
    { title: '勘定科目設定', href: route('accounts.edit') },
];

export default function AccountSettings({ accountGroups, accountTypes, taxCategoryOptions }: Props) {
    const { flash, errors } = usePage<SharedData & { flash?: { success?: string }; errors?: Record<string, string> }>().props;
    const [editingAccount, setEditingAccount] = useState<AccountItem | null>(null);
    const [activeTab, setActiveTab] = useState('expense');

    const activeGroupLabel = accountTypes.find((type) => type.value === activeTab)?.label;
    const activeAccounts = activeGroupLabel ? (accountGroups[activeGroupLabel] ?? []) : [];

    const createForm = useForm({
        code: '',
        name: '',
        type: 'expense',
        default_consumption_tax_category: 'taxable_purchase_10',
    });

    const editForm = useForm({
        code: '',
        name: '',
        type: 'expense',
        display_order: 1,
        default_consumption_tax_category: '',
    });

    const optionsForType = (type: string) => taxCategoryOptions[type] ?? [];

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('accounts.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const openEdit = (account: AccountItem) => {
        setEditingAccount(account);
        setActiveTab(account.type);
        editForm.setData({
            code: account.code,
            name: account.name,
            type: account.type,
            display_order: account.display_order,
            default_consumption_tax_category: account.default_consumption_tax_category ?? '',
        });
    };

    const submitEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editingAccount) {
            return;
        }

        editForm.patch(route('accounts.update', editingAccount.id), {
            preserveScroll: true,
            onSuccess: () => {
                setActiveTab(editForm.data.type);
                setEditingAccount(null);
            },
        });
    };

    const handleDelete = (account: AccountItem) => {
        if (!confirm(`「${account.name}」を削除しますか？`)) {
            return;
        }

        router.delete(route('accounts.destroy', account.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="勘定科目設定" />
            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">勘定科目設定</h1>
                    <p className="text-muted-foreground mt-1 text-sm">仕訳で使用する勘定科目の追加・編集・削除</p>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {errors?.account && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.account}</AlertDescription>
                    </Alert>
                )}

                <section className="max-w-2xl space-y-4">
                    <HeadingSmall title="勘定科目を追加" description="新しい勘定科目を登録します。" />

                    <form onSubmit={submitCreate} className="grid gap-4 sm:grid-cols-4">
                        <div className="grid gap-2">
                            <Label htmlFor="create_code">科目コード</Label>
                            <Input
                                id="create_code"
                                value={createForm.data.code}
                                onChange={(e) => createForm.setData('code', e.target.value)}
                                placeholder="5120"
                                required
                            />
                            <InputError message={createForm.errors.code} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="create_name">科目名</Label>
                            <Input
                                id="create_name"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                placeholder="新規経費科目"
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="create_type">区分</Label>
                            <Select
                                value={createForm.data.type}
                                onValueChange={(v) => {
                                    const defaultCategory = optionsForType(v)[0]?.value ?? 'out_of_scope';
                                    createForm.setData({
                                        ...createForm.data,
                                        type: v,
                                        default_consumption_tax_category: defaultCategory,
                                    });
                                    setActiveTab(v);
                                }}
                            >
                                <SelectTrigger id="create_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {accountTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={createForm.errors.type} />
                        </div>

                        <div className="grid gap-2 sm:col-span-4">
                            <Label htmlFor="create_tax_category">デフォルト税区分</Label>
                            <Select
                                value={createForm.data.default_consumption_tax_category}
                                onValueChange={(v) => createForm.setData('default_consumption_tax_category', v)}
                            >
                                <SelectTrigger id="create_tax_category">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {optionsForType(createForm.data.type).map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={createForm.errors.default_consumption_tax_category} />
                        </div>

                        <div className="sm:col-span-4">
                            <Button type="submit" disabled={createForm.processing}>
                                追加する
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="space-y-4">
                    <HeadingSmall title="勘定科目一覧" description="区分ごとに表示を切り替えて確認・編集できます。" />

                    <div className="flex flex-wrap gap-1 rounded-lg bg-muted p-1">
                        {accountTypes.map((type) => (
                            <button
                                key={type.value}
                                type="button"
                                onClick={() => setActiveTab(type.value)}
                                className={cn(
                                    'rounded-md px-3.5 py-1.5 text-sm transition-colors',
                                    activeTab === type.value
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                                )}
                            >
                                {type.label}
                            </button>
                        ))}
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b">
                                    <th className="px-4 py-3 text-left font-medium">コード</th>
                                    <th className="px-4 py-3 text-left font-medium">科目名</th>
                                    <th className="px-4 py-3 text-left font-medium">デフォルト税区分</th>
                                    <th className="px-4 py-3 text-right font-medium">表示順</th>
                                    <th className="px-4 py-3 text-right font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {activeAccounts.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                            この区分の勘定科目はありません
                                        </td>
                                    </tr>
                                ) : (
                                    activeAccounts.map((account) => (
                                        <tr key={account.id} className="border-b last:border-0">
                                            <td className="px-4 py-3 font-mono">{account.code}</td>
                                            <td className="px-4 py-3">{account.name}</td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {optionsForType(account.type).find(
                                                    (option) => option.value === account.default_consumption_tax_category,
                                                )?.label ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">{account.display_order}</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(account)}>
                                                        <Pencil className="size-4" />
                                                        編集
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={account.is_in_use}
                                                        onClick={() => handleDelete(account)}
                                                        title={account.is_in_use ? '仕訳等で使用中のため削除できません' : undefined}
                                                    >
                                                        <Trash2 className="size-4" />
                                                        削除
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <Dialog open={editingAccount !== null} onOpenChange={(open) => !open && setEditingAccount(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>勘定科目を編集</DialogTitle>
                        </DialogHeader>

                        <form onSubmit={submitEdit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="edit_code">科目コード</Label>
                                <Input
                                    id="edit_code"
                                    value={editForm.data.code}
                                    onChange={(e) => editForm.setData('code', e.target.value)}
                                    required
                                />
                                <InputError message={editForm.errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit_name">科目名</Label>
                                <Input
                                    id="edit_name"
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={editForm.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit_type">区分</Label>
                                <Select value={editForm.data.type} onValueChange={(v) => {
                                    const defaultCategory = optionsForType(v)[0]?.value ?? 'out_of_scope';
                                    editForm.setData({
                                        ...editForm.data,
                                        type: v,
                                        default_consumption_tax_category: defaultCategory,
                                    });
                                }}>
                                    <SelectTrigger id="edit_type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accountTypes.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={editForm.errors.type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit_tax_category">デフォルト税区分</Label>
                                <Select
                                    value={editForm.data.default_consumption_tax_category}
                                    onValueChange={(v) => editForm.setData('default_consumption_tax_category', v)}
                                >
                                    <SelectTrigger id="edit_tax_category">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {optionsForType(editForm.data.type).map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={editForm.errors.default_consumption_tax_category} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit_display_order">表示順</Label>
                                <Input
                                    id="edit_display_order"
                                    type="number"
                                    min={1}
                                    value={editForm.data.display_order}
                                    onChange={(e) => editForm.setData('display_order', Number(e.target.value))}
                                    required
                                />
                                <InputError message={editForm.errors.display_order} />
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setEditingAccount(null)}>
                                    キャンセル
                                </Button>
                                <Button type="submit" disabled={editForm.processing}>
                                    保存する
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
