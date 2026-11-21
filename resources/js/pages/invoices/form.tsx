import { DataTable, DataTableHeader } from '@/components/data-table';
import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TableBody, TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { calculateInvoiceTax, taxRateLabel, type InvoiceTaxRate } from '@/lib/invoice-tax';
import { formatYen } from '@/lib/yen';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, Trash2 } from 'lucide-react';
import { FormEvent, useMemo } from 'react';

interface InvoiceLineValues {
    description: string;
    quantity: number;
    unit_price: number;
    tax_rate: InvoiceTaxRate;
}

interface InvoiceValues {
    id: number;
    invoice_number: string;
    issue_date: string;
    customer_name: string;
    notes: string;
    lines: InvoiceLineValues[];
}

interface Props {
    invoice: InvoiceValues | null;
    registrationNumber: string | null;
    companyName: string | null;
}

function today(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

function blankLine(): InvoiceLineValues {
    return { description: '', quantity: 1, unit_price: 0, tax_rate: '10' };
}

export default function InvoiceForm({ invoice, registrationNumber, companyName }: Props) {
    const isEdit = invoice !== null;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ホーム', href: route('home') },
        { title: '請求書', href: route('invoices.index') },
        { title: isEdit ? invoice.invoice_number : '新規', href: isEdit ? route('invoices.edit', invoice.id) : route('invoices.create') },
    ];

    const form = useForm({
        issue_date: invoice?.issue_date ?? today(),
        customer_name: invoice?.customer_name ?? '',
        notes: invoice?.notes ?? '',
        lines: invoice?.lines.length
            ? invoice.lines.map((line) => ({
                  description: line.description,
                  quantity: line.quantity,
                  unit_price: line.unit_price,
                  tax_rate: line.tax_rate,
              }))
            : [blankLine()],
    });

    const summary = useMemo(() => calculateInvoiceTax(form.data.lines), [form.data.lines]);
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            lines: data.lines.map((line) => ({
                description: line.description,
                quantity: Math.trunc(Number(line.quantity)) || 0,
                unit_price: Math.trunc(Number(line.unit_price)) || 0,
                tax_rate: line.tax_rate,
            })),
        }));

        if (isEdit) {
            form.put(route('invoices.update', invoice.id));
        } else {
            form.post(route('invoices.store'));
        }
    };

    const updateLine = (index: number, patch: Partial<InvoiceLineValues>) => {
        form.setData(
            'lines',
            form.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? `請求書 ${invoice.invoice_number}` : '請求書の作成'} />
            <PageContainer size="full">
                <PageHeader
                    title={isEdit ? `請求書 ${invoice.invoice_number}` : '請求書の作成'}
                    description="単価は税抜です。消費税は税率ごとに切り捨てて表示します。"
                    actions={
                        isEdit ? (
                            registrationNumber ? (
                                <Button asChild variant="outline">
                                    <a href={route('invoices.pdf', invoice.id)}>PDFを出力</a>
                                </Button>
                            ) : (
                                <Button asChild variant="outline">
                                    <Link href={route('fiscal-year.edit')}>登録番号を設定</Link>
                                </Button>
                            )
                        ) : null
                    }
                />

                <FlashAlert />

                {registrationNumber ? (
                    <div className="border-border/50 bg-card grid gap-2 rounded-xl border px-5 py-4 text-sm sm:grid-cols-2">
                        <p>
                            <span className="text-muted-foreground">発行者 </span>
                            {companyName || '（社名未設定）'}
                        </p>
                        <p>
                            <span className="text-muted-foreground">登録番号 </span>
                            <span className="font-mono font-semibold">{registrationNumber}</span>
                        </p>
                    </div>
                ) : (
                    <Alert>
                        <AlertCircle className="size-4" />
                        <AlertDescription>
                            登録番号が未設定のため、PDFは出力できません。
                            <Link href={route('fiscal-year.edit')} className="mx-1 underline">
                                会計期間設定
                            </Link>
                            で保存してください。請求書自体は先に保存できます。
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="space-y-6">
                    <FormSection title="請求情報">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="issue_date">発行日</Label>
                                <Input
                                    id="issue_date"
                                    type="date"
                                    value={form.data.issue_date}
                                    onChange={(event) => form.setData('issue_date', event.target.value)}
                                    required
                                />
                                <InputError message={form.errors.issue_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="customer_name">宛先</Label>
                                <Input
                                    id="customer_name"
                                    value={form.data.customer_name}
                                    onChange={(event) => form.setData('customer_name', event.target.value)}
                                    placeholder="株式会社サンプル"
                                    required
                                />
                                <InputError message={form.errors.customer_name} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="notes">備考</Label>
                                <textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(event) => form.setData('notes', event.target.value)}
                                    rows={3}
                                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                />
                                <InputError message={form.errors.notes} />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="明細" description="軽減税率の行には「軽」と出します。">
                        <DataTable>
                            <DataTableHeader>
                                <TableRow>
                                    <TableHead>取引内容</TableHead>
                                    <TableHead className="w-24">数量</TableHead>
                                    <TableHead className="w-36">単価（税抜）</TableHead>
                                    <TableHead className="w-44">税率</TableHead>
                                    <TableHead className="w-32 text-right">金額（税抜）</TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </DataTableHeader>
                            <TableBody>
                                {form.data.lines.map((line, index) => (
                                    <TableRow key={index}>
                                        <TableCell>
                                            <Input
                                                value={line.description}
                                                onChange={(event) => updateLine(index, { description: event.target.value })}
                                                aria-label={`取引内容 ${index + 1}`}
                                            />
                                            <InputError message={errors[`lines.${index}.description`]} />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={9999}
                                                value={line.quantity}
                                                onChange={(event) => updateLine(index, { quantity: Number(event.target.value) })}
                                                aria-label={`数量 ${index + 1}`}
                                            />
                                            <InputError message={errors[`lines.${index}.quantity`]} />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={99999999}
                                                value={line.unit_price}
                                                onChange={(event) => updateLine(index, { unit_price: Number(event.target.value) })}
                                                aria-label={`単価 ${index + 1}`}
                                            />
                                            <InputError message={errors[`lines.${index}.unit_price`]} />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Select
                                                    value={line.tax_rate}
                                                    onValueChange={(value) => updateLine(index, { tax_rate: value as InvoiceTaxRate })}
                                                >
                                                    <SelectTrigger aria-label={`税率 ${index + 1}`}>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="10">10%</SelectItem>
                                                        <SelectItem value="8">8%（軽減）</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {line.tax_rate === '8' && <span className="text-xs font-semibold whitespace-nowrap">軽</span>}
                                            </div>
                                            <InputError message={errors[`lines.${index}.tax_rate`]} />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatYen(Math.trunc(line.quantity || 0) * Math.trunc(line.unit_price || 0))}円
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={form.data.lines.length === 1}
                                                onClick={() =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.filter((_, lineIndex) => lineIndex !== index),
                                                    )
                                                }
                                                aria-label={`明細 ${index + 1} を削除`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </DataTable>
                        <InputError message={errors.lines} />
                        <Button
                            type="button"
                            variant="outline"
                            className="mt-4"
                            disabled={form.data.lines.length >= 30}
                            onClick={() => form.setData('lines', [...form.data.lines, blankLine()])}
                        >
                            明細を追加
                        </Button>
                    </FormSection>

                    <FormSection title="税率ごとの消費税" description="保存前の内容です。PDFにも同じ内訳が出ます。">
                        <DataTable>
                            <DataTableHeader>
                                <TableRow>
                                    <TableHead>適用税率</TableHead>
                                    <TableHead className="text-right">税抜対価</TableHead>
                                    <TableHead className="text-right">消費税額</TableHead>
                                </TableRow>
                            </DataTableHeader>
                            <TableBody>
                                {summary.rates.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={3} className="text-muted-foreground">
                                            明細を入力すると、税率と消費税額を表示します。
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    summary.rates.map((rate) => (
                                        <TableRow key={rate.rate}>
                                            <TableCell>
                                                {taxRateLabel(rate.rate)}
                                                {rate.rate === '8' && <span className="ml-2 text-xs font-semibold">軽</span>}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{formatYen(rate.base)}円</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatYen(rate.tax)}円</TableCell>
                                        </TableRow>
                                    ))
                                )}
                                <TableRow>
                                    <TableCell>合計</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatYen(summary.subtotal)}円</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatYen(summary.tax_total)}円</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell className="font-semibold">請求金額（税込）</TableCell>
                                    <TableCell colSpan={2} className="text-right font-semibold tabular-nums">
                                        {formatYen(summary.total)}円
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </DataTable>
                    </FormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? '保存中...' : '保存する'}
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={route('invoices.index')}>一覧へ戻る</Link>
                        </Button>
                    </div>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
