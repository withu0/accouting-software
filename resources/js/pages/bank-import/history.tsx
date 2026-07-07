import { DataTable, DataTableHeader } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FlashAlert } from '@/components/flash-alert';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { History, Upload } from 'lucide-react';

interface ImportRecord {
    id: number;
    original_filename: string;
    detected_format?: string | null;
    status: string;
    row_count: number;
    confirmed_count: number;
    pending_count: number;
    skipped_count: number;
    imported_at: string;
}

interface PaginatedImports {
    data: ImportRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    imports: PaginatedImports;
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '銀行CSV取込', href: route('bank-import') },
    { title: '取込履歴', href: route('bank-import.history') },
];

const statusLabels: Record<string, string> = {
    pending: '未完了',
    completed: '完了',
    failed: '失敗',
};

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'completed') return 'default';
    if (status === 'pending') return 'secondary';
    return 'destructive';
}

function ProgressBar({ value, max }: { value: number; max: number }) {
    const pct = max > 0 ? Math.round((value / max) * 100) : 0;
    return (
        <div className="flex items-center gap-2">
            <div className="bg-muted h-1.5 w-20 overflow-hidden rounded-full">
                <div className="bg-primary h-full rounded-full transition-all" style={{ width: `${pct}%` }} />
            </div>
            <span className="text-muted-foreground text-xs tabular-nums">{pct}%</span>
        </div>
    );
}

export default function BankImportHistory({ imports }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="取込履歴" />
            <PageContainer size="full">
                <PageHeader
                    title="取込履歴"
                    description="過去の銀行CSV取込を確認・編集できます"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('bank-import')}>
                                <Upload className="size-4" />
                                新規取込
                            </Link>
                        </Button>
                    }
                />

                <FlashAlert />

                {imports.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="取込履歴はまだありません"
                        description="銀行CSVを取り込むと、ここに履歴が表示されます。"
                        actions={[{ label: 'CSVを取込む', href: route('bank-import') }]}
                    />
                ) : (
                    <>
                        <DataTable>
                            <DataTableHeader>
                                <TableRow>
                                    <TableHead>取込日時</TableHead>
                                    <TableHead>ファイル名</TableHead>
                                    <TableHead>形式</TableHead>
                                    <TableHead>状態</TableHead>
                                    <TableHead>進捗</TableHead>
                                    <TableHead>内訳</TableHead>
                                    <TableHead className="text-right">操作</TableHead>
                                </TableRow>
                            </DataTableHeader>
                            <TableBody>
                                {imports.data.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell className="text-muted-foreground whitespace-nowrap text-xs">
                                            {record.imported_at}
                                        </TableCell>
                                        <TableCell className="max-w-48 font-medium">{record.original_filename}</TableCell>
                                        <TableCell className="text-muted-foreground whitespace-nowrap text-xs">
                                            {record.detected_format ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant(record.status)}>
                                                {statusLabels[record.status] ?? record.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <ProgressBar value={record.confirmed_count} max={record.row_count} />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground whitespace-nowrap text-xs">
                                            記帳 {record.confirmed_count} / 未処理 {record.pending_count} / スキップ{' '}
                                            {record.skipped_count}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {record.pending_count > 0 ? (
                                                <Button asChild variant="default" size="sm">
                                                    <Link href={route('bank-import.review', record.id)}>記帳を続ける</Link>
                                                </Button>
                                            ) : (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('bank-import.show', record.id)}>詳細</Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </DataTable>

                        <Pagination links={imports.links} />
                    </>
                )}
            </PageContainer>
        </AppLayout>
    );
}
