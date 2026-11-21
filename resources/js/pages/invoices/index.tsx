import { DataTable, DataTableHeader } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FlashAlert } from '@/components/flash-alert';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { formatYen } from '@/lib/yen';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ScrollText } from 'lucide-react';

interface InvoiceRate {
    rate: string;
    label: string;
    base: number;
    tax: number;
}

interface InvoiceRow {
    id: number;
    invoice_number: string;
    issue_date: string;
    customer_name: string;
    rates: InvoiceRate[];
    subtotal: number;
    tax_total: number;
    total: number;
}

interface Props {
    invoices: InvoiceRow[];
    registrationNumber: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '請求書', href: route('invoices.index') },
];

export default function InvoiceIndex({ invoices, registrationNumber }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="請求書" />
            <PageContainer size="full">
                <PageHeader
                    title="請求書"
                    description="登録番号・税率・消費税額を確認できる適格請求書を作成し、PDFで出力します。"
                    actions={
                        <Button asChild>
                            <Link href={route('invoices.create')}>新しい請求書</Link>
                        </Button>
                    }
                />

                <FlashAlert />

                {registrationNumber ? (
                    <p className="text-sm">
                        登録番号 <span className="font-mono font-semibold">{registrationNumber}</span>
                    </p>
                ) : (
                    <Alert>
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            登録番号が未設定です。PDFを出すには
                            <Link href={route('fiscal-year.edit')} className="mx-1 underline">
                                会計期間設定
                            </Link>
                            でTと13桁の番号を保存してください。
                        </AlertDescription>
                    </Alert>
                )}

                {invoices.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title="請求書はまだありません"
                        description="宛先と明細を入力すると、税率ごとの消費税額を確認してPDFにできます。"
                        actions={[{ label: '請求書を作成', href: route('invoices.create') }]}
                    />
                ) : (
                    <DataTable>
                        <DataTableHeader>
                            <TableRow>
                                <TableHead>請求番号</TableHead>
                                <TableHead>発行日</TableHead>
                                <TableHead>宛先</TableHead>
                                <TableHead>税率と消費税</TableHead>
                                <TableHead className="text-right">税抜</TableHead>
                                <TableHead className="text-right">消費税</TableHead>
                                <TableHead className="text-right">請求金額</TableHead>
                                <TableHead />
                            </TableRow>
                        </DataTableHeader>
                        <TableBody>
                            {invoices.map((invoice) => (
                                <TableRow key={invoice.id}>
                                    <TableCell className="font-mono">{invoice.invoice_number}</TableCell>
                                    <TableCell>{formatDate(invoice.issue_date)}</TableCell>
                                    <TableCell>{invoice.customer_name}</TableCell>
                                    <TableCell>
                                        <div className="space-y-1">
                                            {invoice.rates.map((rate) => (
                                                <p key={rate.rate} className="text-sm">
                                                    {rate.label}
                                                    <span className="text-muted-foreground"> 対価 </span>
                                                    {formatYen(rate.base)}円<span className="text-muted-foreground"> 消費税 </span>
                                                    {formatYen(rate.tax)}円
                                                </p>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">{formatYen(invoice.subtotal)}円</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatYen(invoice.tax_total)}円</TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">{formatYen(invoice.total)}円</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('invoices.edit', invoice.id)}>編集</Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm">
                                                <a href={route('invoices.pdf', invoice.id)}>PDF</a>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    if (window.confirm('この請求書を削除しますか？')) {
                                                        router.delete(route('invoices.destroy', invoice.id));
                                                    }
                                                }}
                                            >
                                                削除
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </DataTable>
                )}
            </PageContainer>
        </AppLayout>
    );
}
