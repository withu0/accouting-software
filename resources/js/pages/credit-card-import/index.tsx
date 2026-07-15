import { DataTable, DataTableHeader } from '@/components/data-table';
import { FileDropZone } from '@/components/file-drop-zone';
import InputError from '@/components/input-error';
import { FormSection } from '@/components/form-section';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SummaryStrip } from '@/components/summary-strip';
import { WorkflowSteps, creditCardImportSteps } from '@/components/workflow-steps';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface RecentImport {
    id: number;
    original_filename: string;
    detected_format?: string | null;
    card_name?: string | null;
    status: string;
    row_count: number;
    confirmed_count: number;
    pending_count: number;
    skipped_count: number;
    imported_at: string;
}

interface Props {
    hasActiveFiscalYear: boolean;
    recentImports: RecentImport[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'クレジットカードCSV取込', href: route('credit-card-import') },
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

export default function CreditCardImportIndex({ hasActiveFiscalYear, recentImports }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('credit-card-import.store'), {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="クレジットカードCSV取込" />
            <PageContainer size="full">
                <PageHeader
                    title="クレジットカードCSV取込"
                    description="クレジットカード明細CSVを取り込み、経費仕訳（未払金計上）を作成します"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('credit-card-import.history')}>取込履歴</Link>
                        </Button>
                    }
                />

                <WorkflowSteps steps={creditCardImportSteps} currentStep="upload" />

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

                <div className="mx-auto w-full max-w-2xl space-y-6">
                    <FormSection
                        title="CSVファイルをアップロード"
                        description="既知のクレジットカード明細形式は自動判別し、未知の形式はAIが列を判定します"
                    >
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="file">CSVファイル</Label>
                                <FileDropZone
                                    file={data.file}
                                    disabled={!hasActiveFiscalYear || processing}
                                    onFileChange={(file) => setData('file', file)}
                                />
                                <InputError message={errors.file} />
                            </div>
                            <Button type="submit" disabled={!hasActiveFiscalYear || processing || !data.file}>
                                {processing ? '取込中...' : 'アップロードして確認'}
                            </Button>
                        </form>
                    </FormSection>

                    <SummaryStrip
                        items={[
                            { label: '形式', value: '既知形式 → AI判定' },
                            { label: '記帳', value: '経費 / 未払金' },
                        ]}
                    />
                </div>

                <FormSection
                    title="最近の取込履歴"
                    description="直近5件のクレジットカードCSV取込"
                >
                    <div className="mb-4 flex justify-end">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={route('credit-card-import.history')}>すべて見る</Link>
                        </Button>
                    </div>
                    {recentImports.length === 0 ? (
                        <p className="text-muted-foreground text-sm">取込履歴はまだありません。</p>
                    ) : (
                        <DataTable>
                            <DataTableHeader>
                                <TableRow>
                                    <TableHead>取込日時</TableHead>
                                    <TableHead>ファイル名</TableHead>
                                    <TableHead>カード</TableHead>
                                    <TableHead>状態</TableHead>
                                    <TableHead>内訳</TableHead>
                                    <TableHead className="text-right">操作</TableHead>
                                </TableRow>
                            </DataTableHeader>
                            <TableBody>
                                {recentImports.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell className="text-muted-foreground whitespace-nowrap text-xs">
                                            {record.imported_at}
                                        </TableCell>
                                        <TableCell className="max-w-48 font-medium">{record.original_filename}</TableCell>
                                        <TableCell className="text-muted-foreground max-w-40 truncate text-xs">
                                            {record.card_name ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant(record.status)}>
                                                {statusLabels[record.status] ?? record.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground whitespace-nowrap text-xs">
                                            記帳 {record.confirmed_count} / 未処理 {record.pending_count} / スキップ{' '}
                                            {record.skipped_count}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {record.pending_count > 0 ? (
                                                <Button asChild variant="default" size="sm">
                                                    <Link href={route('credit-card-import.review', record.id)}>
                                                        記帳を続ける
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('credit-card-import.show', record.id)}>詳細</Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </DataTable>
                    )}
                </FormSection>
            </PageContainer>
        </AppLayout>
    );
}
