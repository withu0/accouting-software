import { type TaxCategoryOption } from '@/components/consumption-tax-fields';
import { DataTable, DataTableHeader } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SectionHeader } from '@/components/section-header';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { SummaryStrip } from '@/components/summary-strip';
import TransferJournalRowTable, {
    createEmptyRow,
    flattenRowsToLines,
    sumRowTotals,
    type TransferJournalRow,
} from '@/components/transfer-journal-row-table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Receipt, Trash2 } from 'lucide-react';
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
            <PageContainer size="full">
                <PageHeader title="臨時仕訳（振替伝票）" description="通常処理で対応できない例外仕訳を入力します" />

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

                {presets.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        <span className="text-muted-foreground self-center text-xs font-medium">プリセット:</span>
                        {presets.map((preset) => (
                            <button
                                key={preset.id}
                                type="button"
                                disabled={!hasActiveFiscalYear || processing}
                                onClick={() => applyPreset(preset)}
                                className={cn(
                                    'rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                                    'border-border/60 bg-muted/40 text-foreground hover:border-primary/40 hover:bg-primary/5',
                                    'disabled:pointer-events-none disabled:opacity-50',
                                )}
                            >
                                {preset.label}
                            </button>
                        ))}
                    </div>
                )}

                <div className="flex flex-col gap-8">
                    <FormSection title="振替伝票を登録" description="発生日・借方・貸方・摘要を入力してください">
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

                            <SummaryStrip
                                items={[
                                    { label: '借方合計', value: formatAmount(debitTotal) },
                                    { label: '貸方合計', value: formatAmount(creditTotal) },
                                    {
                                        label: '差額',
                                        value: amountsMismatch ? formatAmount(shortageAmount) : '一致',
                                        variant: amountsMismatch ? 'warning' : 'success',
                                        highlight: amountsMismatch,
                                    },
                                ]}
                            />

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
                    </FormSection>

                    <section className="space-y-4">
                        <SectionHeader
                            title="登録済みの振替伝票"
                            description={entries.length > 0 ? `${entries.length}件の振替伝票が登録されています` : undefined}
                        />

                        {entries.length === 0 ? (
                            <EmptyState
                                icon={Receipt}
                                title="登録された振替伝票はありません"
                                description="上のフォームから振替伝票を登録してください。プリセットを使うとよく使う仕訳を素早く入力できます。"
                            />
                        ) : (
                            <DataTable>
                                <DataTableHeader>
                                    <TableRow>
                                        <TableHead>日付</TableHead>
                                        <TableHead>摘要</TableHead>
                                        <TableHead>仕訳明細</TableHead>
                                        <TableHead className="text-right">合計</TableHead>
                                        <TableHead className="text-right">操作</TableHead>
                                    </TableRow>
                                </DataTableHeader>
                                <TableBody>
                                    {entries.map((entry) => (
                                        <TableRow key={entry.id} className="align-top">
                                            <TableCell className="whitespace-nowrap">{formatDate(entry.entry_date)}</TableCell>
                                            <TableCell className="font-medium">{entry.description}</TableCell>
                                            <TableCell>
                                                <div className="space-y-2 text-[13px]">
                                                    {entry.lines.map((line, lineIndex) => (
                                                        <div key={`${entry.id}-${lineIndex}`} className="grid gap-1">
                                                            {line.debit > 0 && (
                                                                <span>
                                                                    借 {line.account_name}{' '}
                                                                    <span className="tabular-nums">{formatAmount(line.debit)}</span>
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
                                                                    貸 {line.account_name}{' '}
                                                                    <span className="tabular-nums">{formatAmount(line.credit)}</span>
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
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap tabular-nums">
                                                {formatAmount(entry.amount)}
                                            </TableCell>
                                            <TableCell className="text-right">
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
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </DataTable>
                        )}
                    </section>
                </div>

                {amountsMismatch && (
                    <StickyActionBar variant="warning">
                        <span className="text-sm">
                            借方合計と貸方合計が一致していません（差額: {formatAmount(shortageAmount)}）
                        </span>
                    </StickyActionBar>
                )}
            </PageContainer>
        </AppLayout>
    );
}
