import BankImportRowEditDialog from '@/components/bank-import-row-edit-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Pencil } from 'lucide-react';

interface AccountOption {
    id: number;
    name: string;
}

interface ImportRow {
    id: number;
    transaction_date: string;
    description: string;
    amount: number;
    is_deposit: boolean;
    status: string;
    account_id: number | null;
    journal_entry_id: number | null;
}

interface Props {
    bankImport: {
        id: number;
        original_filename: string;
        status: string;
        imported_at: string;
    };
    rows: ImportRow[];
    accountGroups: Record<string, AccountOption[]>;
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '銀行CSV取込', href: route('bank-import') },
    { title: '取込履歴', href: route('bank-import.history') },
    { title: '取込詳細', href: '#' },
];

const statusLabels: Record<string, string> = {
    pending: '未記帳',
    confirmed: '記帳済',
    skipped: 'スキップ',
};

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'confirmed') {
        return 'default';
    }
    if (status === 'pending') {
        return 'secondary';
    }
    return 'outline';
}

export default function BankImportShow({
    bankImport,
    rows,
    accountGroups,
    hasActiveFiscalYear,
}: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    const handleSkip = (rowId: number) => {
        router.post(route('bank-import.rows.skip', rowId), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="取込詳細" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">取込詳細</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {bankImport.original_filename} — {bankImport.imported_at}
                    </p>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {rows.length === 0 ? (
                    <div className="text-muted-foreground py-12 text-center text-sm">取引データがありません</div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b">
                                    <th className="px-4 py-3 text-left font-medium">日付</th>
                                    <th className="px-4 py-3 text-left font-medium">摘要</th>
                                    <th className="px-4 py-3 text-right font-medium">金額</th>
                                    <th className="px-4 py-3 text-left font-medium">状態</th>
                                    <th className="px-4 py-3 text-right font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id} className="border-b last:border-b-0">
                                        <td className="px-4 py-3 whitespace-nowrap">{formatDate(row.transaction_date)}</td>
                                        <td className="px-4 py-3">{row.description}</td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <span className={row.is_deposit ? 'text-green-700 dark:text-green-400' : ''}>
                                                {row.is_deposit ? '+' : '-'}
                                                {formatAmount(row.amount)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={statusVariant(row.status)}>
                                                {statusLabels[row.status] ?? row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {row.status !== 'skipped' && (
                                                    <BankImportRowEditDialog
                                                        rowId={row.id}
                                                        isDeposit={row.is_deposit}
                                                        initialValues={{
                                                            transaction_date: row.transaction_date,
                                                            description: row.description,
                                                            amount: row.amount,
                                                            account_id: row.account_id,
                                                        }}
                                                        accountGroups={accountGroups}
                                                        hasActiveFiscalYear={hasActiveFiscalYear}
                                                        trigger={
                                                            <Button variant="ghost" size="sm">
                                                                <Pencil className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                                {row.status === 'pending' && (
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => handleSkip(row.id)}>
                                                        スキップ
                                                    </Button>
                                                )}
                                                {row.status === 'confirmed' && row.journal_entry_id !== null && (
                                                    <Button asChild variant="ghost" size="sm">
                                                        <Link href={route('journals.index')}>仕訳</Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
