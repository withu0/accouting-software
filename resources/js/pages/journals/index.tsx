import BankImportRowEditDialog from '@/components/bank-import-row-edit-dialog';
import { DataTable, DataTableHeader } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { FlashAlert } from '@/components/flash-alert';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { SummaryStrip } from '@/components/summary-strip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, FileText, Pencil, Trash2 } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';

interface AccountOption {
    id: number;
    name: string;
}

interface BankCsvEditMeta {
    row_id: number;
    is_deposit: boolean;
    amount: number;
    account_id: number | null;
    consumption_tax_category: string;
    has_qualified_invoice: boolean;
}

interface JournalEntry {
    id: number;
    entry_date: string;
    description: string;
    source: string;
    total_amount: number;
    debit_account_name: string;
    credit_account_name: string;
    bank_csv_edit?: BankCsvEditMeta;
}

interface PaginatedEntries {
    data: JournalEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    entries: PaginatedEntries;
    accountGroups: Record<string, AccountOption[]>;
    expenseAccounts: Array<{ id: number; default_consumption_tax_category?: string | null }>;
    salesTaxCategories: Array<{ value: string; label: string }>;
    purchaseTaxCategories: Array<{ value: string; label: string }>;
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '仕訳一覧', href: route('journals.index') },
];

const sourceLabels: Record<string, string> = {
    bank_csv: '銀行CSV',
    credit_card_csv: 'カードCSV',
    advance_expense: '立替経費',
    transfer: '振替伝票',
    manual: '手動',
};

const sourceBadgeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    bank_csv: 'default',
    credit_card_csv: 'outline',
    advance_expense: 'secondary',
    transfer: 'outline',
    manual: 'outline',
};

const sourceBadgeClassName: Record<string, string> = {
    credit_card_csv: 'border-transparent bg-teal-600 text-white hover:bg-teal-600/80',
};

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

function isDeletable(entry: JournalEntry): boolean {
    return entry.source === 'bank_csv' || entry.source === 'credit_card_csv';
}

export default function JournalsIndex({
    entries,
    accountGroups,
    expenseAccounts,
    salesTaxCategories,
    purchaseTaxCategories,
    hasActiveFiscalYear,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());
    const [sourceFilter, setSourceFilter] = useState<string>('all');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const filteredEntries = useMemo(() => {
        return entries.data.filter((entry) => {
            if (sourceFilter !== 'all' && entry.source !== sourceFilter) {
                return false;
            }
            if (dateFrom && entry.entry_date < dateFrom) {
                return false;
            }
            if (dateTo && entry.entry_date > dateTo) {
                return false;
            }
            return true;
        });
    }, [entries.data, sourceFilter, dateFrom, dateTo]);

    const deletableEntries = useMemo(() => filteredEntries.filter(isDeletable), [filteredEntries]);
    const hasDeletableEntries = deletableEntries.length > 0;

    const allDeletableSelected =
        deletableEntries.length > 0 && deletableEntries.every((entry) => selectedIds.has(entry.id));
    const someDeletableSelected = deletableEntries.some((entry) => selectedIds.has(entry.id));

    const selectedTotal = useMemo(
        () => filteredEntries.filter((e) => selectedIds.has(e.id)).reduce((sum, e) => sum + e.total_amount, 0),
        [filteredEntries, selectedIds],
    );

    const toggleExpanded = (id: number) => {
        setExpandedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

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

    const toggleAllDeletable = (checked: boolean) => {
        setSelectedIds(checked ? new Set(deletableEntries.map((entry) => entry.id)) : new Set());
    };

    const handleBulkDelete = () => {
        setBulkDeleting(true);
        router.delete(route('journals.destroy-bulk'), {
            data: { ids: Array.from(selectedIds) },
            preserveScroll: true,
            onSuccess: () => setSelectedIds(new Set()),
            onFinish: () => setBulkDeleting(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="仕訳一覧" />
            <PageContainer size="full">
                <PageHeader title="仕訳一覧" description="登録済みの仕訳を確認できます" />

                <FlashAlert />

                {entries.data.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="仕訳がまだありません"
                        description="銀行CSV取込や立替経費入力から仕訳を登録できます。"
                        actions={[
                            { label: '銀行CSV取込', href: route('bank-import'), variant: 'default' },
                            { label: '立替経費入力', href: route('advance-expenses'), variant: 'outline' },
                        ]}
                    />
                ) : (
                    <>
                        <FilterToolbar>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="source-filter" className="text-muted-foreground shrink-0 text-xs">
                                    ソース
                                </Label>
                                <Select value={sourceFilter} onValueChange={setSourceFilter}>
                                    <SelectTrigger id="source-filter" className="h-8 w-[140px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">すべて</SelectItem>
                                        <SelectItem value="bank_csv">銀行CSV</SelectItem>
                                        <SelectItem value="credit_card_csv">カードCSV</SelectItem>
                                        <SelectItem value="advance_expense">立替経費</SelectItem>
                                        <SelectItem value="transfer">振替伝票</SelectItem>
                                        <SelectItem value="manual">手動</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="date-from" className="text-muted-foreground shrink-0 text-xs">
                                    期間
                                </Label>
                                <Input
                                    id="date-from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    className="h-8 w-[140px]"
                                />
                                <span className="text-muted-foreground text-xs">〜</span>
                                <Input
                                    id="date-to"
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    className="h-8 w-[140px]"
                                />
                            </div>
                            {(sourceFilter !== 'all' || dateFrom || dateTo) && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSourceFilter('all');
                                        setDateFrom('');
                                        setDateTo('');
                                    }}
                                >
                                    クリア
                                </Button>
                            )}
                        </FilterToolbar>

                        {selectedIds.size > 0 && (
                            <SummaryStrip
                                items={[
                                    { label: '選択', value: `${selectedIds.size}件` },
                                    { label: '合計金額', value: formatAmount(selectedTotal), highlight: true },
                                ]}
                            />
                        )}

                        {filteredEntries.length === 0 ? (
                            <EmptyState
                                icon={FileText}
                                title="条件に一致する仕訳がありません"
                                description="フィルター条件を変更するか、クリアして再度お試しください。"
                                actions={[{ label: 'フィルターをクリア', onClick: () => { setSourceFilter('all'); setDateFrom(''); setDateTo(''); }, variant: 'outline' }]}
                            />
                        ) : (
                            <DataTable>
                            <DataTableHeader>
                                <TableRow>
                                    {hasDeletableEntries && (
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={allDeletableSelected ? true : someDeletableSelected ? 'indeterminate' : false}
                                                onCheckedChange={(checked) => toggleAllDeletable(checked === true)}
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead>日付</TableHead>
                                    <TableHead>摘要</TableHead>
                                    <TableHead>勘定科目</TableHead>
                                    <TableHead>ソース</TableHead>
                                    <TableHead className="text-right">金額</TableHead>
                                    <TableHead className="text-right">操作</TableHead>
                                </TableRow>
                            </DataTableHeader>
                            <TableBody>
                                {filteredEntries.map((entry) => {
                                    const isExpanded = expandedIds.has(entry.id);

                                    return (
                                        <Fragment key={entry.id}>
                                            <TableRow
                                                className="cursor-pointer"
                                                onClick={() => toggleExpanded(entry.id)}
                                            >
                                                {hasDeletableEntries && (
                                                    <TableCell onClick={(e) => e.stopPropagation()}>
                                                        {isDeletable(entry) ? (
                                                            <Checkbox
                                                                checked={selectedIds.has(entry.id)}
                                                                onCheckedChange={(checked) => toggleRow(entry.id, checked === true)}
                                                            />
                                                        ) : null}
                                                    </TableCell>
                                                )}
                                                <TableCell className="whitespace-nowrap">
                                                    <div className="flex items-center gap-1.5">
                                                        <ChevronDown
                                                            className={cn(
                                                                'text-muted-foreground size-4 shrink-0 transition-transform',
                                                                isExpanded && 'rotate-180',
                                                            )}
                                                        />
                                                        {formatDate(entry.entry_date)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">{entry.description}</TableCell>
                                                <TableCell>
                                                    <span className="block text-[13px]">借 {entry.debit_account_name}</span>
                                                    <span className="text-muted-foreground block text-[13px]">貸 {entry.credit_account_name}</span>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    <Badge
                                                        variant={sourceBadgeVariant[entry.source] ?? 'outline'}
                                                        className={sourceBadgeClassName[entry.source]}
                                                    >
                                                        {sourceLabels[entry.source] ?? entry.source}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right whitespace-nowrap tabular-nums">
                                                    {formatAmount(entry.total_amount)}
                                                </TableCell>
                                                <TableCell className="text-right" onClick={(e) => e.stopPropagation()}>
                                                    {isDeletable(entry) ? (
                                                        <div className="flex items-center justify-end gap-1">
                                                            {entry.source === 'bank_csv' && entry.bank_csv_edit ? (
                                                                <BankImportRowEditDialog
                                                                    journalId={entry.id}
                                                                    isDeposit={entry.bank_csv_edit.is_deposit}
                                                                    initialValues={{
                                                                        transaction_date: entry.entry_date,
                                                                        description: entry.description,
                                                                        amount: entry.bank_csv_edit.amount,
                                                                        account_id: entry.bank_csv_edit.account_id,
                                                                        consumption_tax_category: entry.bank_csv_edit.consumption_tax_category,
                                                                        has_qualified_invoice: entry.bank_csv_edit.has_qualified_invoice,
                                                                    }}
                                                                    accountGroups={accountGroups}
                                                                    expenseAccounts={expenseAccounts}
                                                                    salesTaxCategories={salesTaxCategories}
                                                                    purchaseTaxCategories={purchaseTaxCategories}
                                                                    hasActiveFiscalYear={hasActiveFiscalYear}
                                                                    trigger={
                                                                        <Button variant="ghost" size="sm">
                                                                            <Pencil className="size-4" />
                                                                        </Button>
                                                                    }
                                                                />
                                                            ) : null}
                                                            <Dialog>
                                                                <DialogTrigger asChild>
                                                                    <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                                        <Trash2 className="size-4" />
                                                                    </Button>
                                                                </DialogTrigger>
                                                                <DialogContent>
                                                                    <DialogTitle>仕訳を削除しますか？</DialogTitle>
                                                                    <DialogDescription>
                                                                        {formatDate(entry.entry_date)} — {entry.description}（
                                                                        {formatAmount(entry.total_amount)}）を削除します。同じCSV行を再度取込できるようになります。
                                                                    </DialogDescription>
                                                                    <DialogFooter>
                                                                        <DialogClose asChild>
                                                                            <Button variant="outline">キャンセル</Button>
                                                                        </DialogClose>
                                                                        <Button
                                                                            variant="destructive"
                                                                            onClick={() => {
                                                                                router.delete(route('journals.destroy', entry.id), {
                                                                                    preserveScroll: true,
                                                                                    onSuccess: () => {
                                                                                        setSelectedIds((prev) => {
                                                                                            if (!prev.has(entry.id)) {
                                                                                                return prev;
                                                                                            }
                                                                                            const next = new Set(prev);
                                                                                            next.delete(entry.id);
                                                                                            return next;
                                                                                        });
                                                                                    },
                                                                                });
                                                                            }}
                                                                        >
                                                                            削除する
                                                                        </Button>
                                                                    </DialogFooter>
                                                                </DialogContent>
                                                            </Dialog>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">—</span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                            {isExpanded && (
                                                <TableRow className="bg-muted/20 hover:bg-muted/20">
                                                    <TableCell colSpan={hasDeletableEntries ? 7 : 6} className="py-3">
                                                        <div className="grid gap-2 text-[13px] sm:grid-cols-2">
                                                            <div className="rounded-lg border bg-background px-4 py-3">
                                                                <p className="text-muted-foreground mb-1 text-xs font-medium">借方</p>
                                                                <p className="font-medium">{entry.debit_account_name}</p>
                                                                <p className="mt-1 tabular-nums">{formatAmount(entry.total_amount)}</p>
                                                            </div>
                                                            <div className="rounded-lg border bg-background px-4 py-3">
                                                                <p className="text-muted-foreground mb-1 text-xs font-medium">貸方</p>
                                                                <p className="font-medium">{entry.credit_account_name}</p>
                                                                <p className="mt-1 tabular-nums">{formatAmount(entry.total_amount)}</p>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </TableBody>
                        </DataTable>
                        )}

                        <Pagination links={entries.links} onPageChange={() => setSelectedIds(new Set())} />

                        {selectedIds.size > 0 && (
                            <StickyActionBar>
                                <span className="text-sm">{selectedIds.size}件を選択中</span>
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button variant="destructive" size="sm" disabled={bulkDeleting}>
                                            <Trash2 className="size-4" />
                                            {bulkDeleting ? '削除中...' : `選択した${selectedIds.size}件を削除`}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>選択した仕訳を削除しますか？</DialogTitle>
                                        <DialogDescription>
                                            {selectedIds.size}件の銀行CSV取込仕訳を削除します。同じCSV行を再度取込できるようになります。
                                        </DialogDescription>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button variant="outline">キャンセル</Button>
                                            </DialogClose>
                                            <Button variant="destructive" onClick={handleBulkDelete} disabled={bulkDeleting}>
                                                削除する
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </StickyActionBar>
                        )}
                    </>
                )}
            </PageContainer>
        </AppLayout>
    );
}
