import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, History, Upload } from 'lucide-react';

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
    if (status === 'completed') {
        return 'default';
    }
    if (status === 'pending') {
        return 'secondary';
    }
    return 'destructive';
}

export default function BankImportHistory({ imports }: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="取込履歴" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">取込履歴</h1>
                        <p className="text-muted-foreground mt-1 text-sm">過去の銀行CSV取込を確認・編集できます</p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={route('bank-import')}>
                            <Upload className="size-4" />
                            新規取込
                        </Link>
                    </Button>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {imports.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                        <div className="bg-muted flex size-16 items-center justify-center rounded-full">
                            <History className="text-muted-foreground size-8" />
                        </div>
                        <p className="text-muted-foreground text-sm">取込履歴はまだありません</p>
                        <Button asChild>
                            <Link href={route('bank-import')}>CSVを取込む</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="px-4 py-3 text-left font-medium">取込日時</th>
                                        <th className="px-4 py-3 text-left font-medium">ファイル名</th>
                                        <th className="px-4 py-3 text-left font-medium">形式</th>
                                        <th className="px-4 py-3 text-left font-medium">状態</th>
                                        <th className="px-4 py-3 text-right font-medium">件数</th>
                                        <th className="px-4 py-3 text-right font-medium">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {imports.data.map((record) => (
                                        <tr key={record.id} className="border-b last:border-b-0">
                                            <td className="px-4 py-3 whitespace-nowrap">{record.imported_at}</td>
                                            <td className="px-4 py-3">{record.original_filename}</td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                                {record.detected_format ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={statusVariant(record.status)}>
                                                    {statusLabels[record.status] ?? record.status}
                                                </Badge>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 text-right whitespace-nowrap">
                                                記帳 {record.confirmed_count} / 未処理 {record.pending_count} / スキップ{' '}
                                                {record.skipped_count}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {record.pending_count > 0 ? (
                                                    <Button asChild variant="outline" size="sm">
                                                        <Link href={route('bank-import.review', record.id)}>記帳を続ける</Link>
                                                    </Button>
                                                ) : (
                                                    <Button asChild variant="ghost" size="sm">
                                                        <Link href={route('bank-import.show', record.id)}>詳細</Link>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {imports.last_page > 1 && (
                            <div className="flex items-center justify-center gap-2">
                                {imports.links.map((link, index) => {
                                    if (link.url === null) {
                                        return (
                                            <span
                                                key={index}
                                                className="text-muted-foreground px-3 py-1 text-sm"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }

                                    return (
                                        <Button key={index} asChild variant={link.active ? 'default' : 'outline'} size="sm">
                                            <Link href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                        </Button>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
