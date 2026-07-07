import { DataTable, DataTableHeader } from '@/components/data-table';
import { FilterToolbar } from '@/components/filter-toolbar';
import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

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
    const { errors } = usePage<SharedData & { flash?: { success?: string }; errors?: Record<string, string> }>().props;
    const [editingAccount, setEditingAccount] = useState<AccountItem | null>(null);
    const [deletingAccount, setDeletingAccount] = useState<AccountItem | null>(null);
    const [activeTab, setActiveTab] = useState('expense');
    const [searchQuery, setSearchQuery] = useState('');

    const activeGroupLabel = accountTypes.find((type) => type.value === activeTab)?.label;
    const activeAccounts = activeGroupLabel ? (accountGroups[activeGroupLabel] ?? []) : [];

    const filteredAccounts = useMemo(() => {
        if (!searchQuery.trim()) {
            return activeAccounts;
        }
        const query = searchQuery.trim().toLowerCase();
        return activeAccounts.filter(
            (account) => account.name.toLowerCase().includes(query) || account.code.toLowerCase().includes(query),
        );
    }, [activeAccounts, searchQuery]);

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
        router.delete(route('accounts.destroy', account.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingAccount(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="勘定科目設定" />
            <PageContainer size="full">
                <PageHeader title="勘定科目設定" description="仕訳で使用する勘定科目の追加・編集・削除" />

                <FlashAlert />

                {errors?.account && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.account}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-8 lg:grid-cols-[200px_1fr]">
                    <nav className="surface-card space-y-1 p-2 lg:sticky lg:top-20 lg:self-start">
                        {accountTypes.map((type) => (
                            <button
                                key={type.value}
                                type="button"
                                onClick={() => {
                                    setActiveTab(type.value);
                                    setSearchQuery('');
                                }}
                                className={cn(
                                    'flex w-full items-center rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors',
                                    activeTab === type.value
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                )}
                            >
                                {type.label}
                            </button>
                        ))}
                    </nav>

                    <div className="min-w-0 space-y-6">
                        <FormSection title="新規科目" description="新しい勘定科目を登録します。">
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
                        </FormSection>

                        <div className="space-y-4">
                            <FilterToolbar sticky={false}>
                                <div className="flex flex-1 items-center gap-2">
                                    <Label htmlFor="account-search" className="text-muted-foreground shrink-0 text-xs">
                                        検索
                                    </Label>
                                    <Input
                                        id="account-search"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="科目名・コードで検索"
                                        className="h-8 max-w-xs"
                                    />
                                </div>
                            </FilterToolbar>

                            <DataTable>
                                <DataTableHeader>
                                    <TableRow>
                                        <TableHead>コード</TableHead>
                                        <TableHead>科目名</TableHead>
                                        <TableHead>デフォルト税区分</TableHead>
                                        <TableHead className="text-right">表示順</TableHead>
                                        <TableHead className="text-right">操作</TableHead>
                                    </TableRow>
                                </DataTableHeader>
                                <TableBody>
                                    {filteredAccounts.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                                {searchQuery ? '検索条件に一致する科目がありません' : 'この区分の勘定科目はありません'}
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filteredAccounts.map((account) => (
                                            <TableRow key={account.id}>
                                                <TableCell className="font-mono text-[13px]">{account.code}</TableCell>
                                                <TableCell className="font-medium">{account.name}</TableCell>
                                                <TableCell className="text-muted-foreground text-[13px]">
                                                    {optionsForType(account.type).find(
                                                        (option) => option.value === account.default_consumption_tax_category,
                                                    )?.label ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">{account.display_order}</TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(account)}>
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={account.is_in_use}
                                                            onClick={() => setDeletingAccount(account)}
                                                            title={account.is_in_use ? '仕訳等で使用中のため削除できません' : undefined}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </DataTable>
                        </div>
                    </div>
                </div>

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
                                <Select
                                    value={editForm.data.type}
                                    onValueChange={(v) => {
                                        const defaultCategory = optionsForType(v)[0]?.value ?? 'out_of_scope';
                                        editForm.setData({
                                            ...editForm.data,
                                            type: v,
                                            default_consumption_tax_category: defaultCategory,
                                        });
                                    }}
                                >
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

                <Dialog open={deletingAccount !== null} onOpenChange={(open) => !open && setDeletingAccount(null)}>
                    <DialogContent>
                        <DialogTitle>勘定科目を削除しますか？</DialogTitle>
                        <DialogDescription>
                            「{deletingAccount?.name}」を削除します。この操作は取り消せません。
                        </DialogDescription>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">キャンセル</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                onClick={() => deletingAccount && handleDelete(deletingAccount)}
                            >
                                削除する
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageContainer>
        </AppLayout>
    );
}
