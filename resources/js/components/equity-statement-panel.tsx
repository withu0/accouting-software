import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatYen, parseYenInput } from '@/lib/yen';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { AlertCircle, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

export interface EquityStatementRow {
    label: string;
    kind: string;
    capital: number;
    capital_surplus: number;
    retained_earnings: number;
    treasury: number;
    total: number;
}

export interface EquityMismatch {
    account_name: string;
    statement_amount: number;
    bs_amount: number;
    difference: number;
}

export interface EquityStatementReport {
    columns: Record<string, string>;
    rows: EquityStatementRow[];
    is_consistent: boolean;
    mismatches: EquityMismatch[];
    is_manual: boolean;
    editable_rows: Array<{
        label: string;
        capital: number;
        capital_surplus: number;
        retained_earnings: number;
        treasury: number;
    }>;
}

interface DraftRow {
    label: string;
    capital: string;
    capital_surplus: string;
    retained_earnings: string;
    treasury: string;
}

const emptyDraftRow = (): DraftRow => ({
    label: '',
    capital: '',
    capital_surplus: '',
    retained_earnings: '',
    treasury: '',
});

function amountToInput(amount: number): string {
    return amount === 0 ? '' : String(amount);
}

function toDraft(rows: EquityStatementReport['editable_rows']): DraftRow[] {
    if (rows.length === 0) {
        return [emptyDraftRow()];
    }

    return rows.map((row) => ({
        label: row.label,
        capital: amountToInput(row.capital),
        capital_surplus: amountToInput(row.capital_surplus),
        retained_earnings: amountToInput(row.retained_earnings),
        treasury: amountToInput(row.treasury),
    }));
}

function rowTotal(row: { capital: number; capital_surplus: number; retained_earnings: number; treasury: number }): number {
    return row.capital + row.capital_surplus + row.retained_earnings + row.treasury;
}

export function EquityStatementPanel({ report }: { report: EquityStatementReport }) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState<DraftRow[]>(() => toDraft(report.editable_rows));
    const form = useForm({ rows: report.editable_rows });

    useEffect(() => {
        if (!editing) {
            setDraft(toDraft(report.editable_rows));
        }
    }, [report, editing]);

    const beginning = report.rows.find((row) => row.kind === 'beginning');
    const netIncome = report.rows.find((row) => row.kind === 'net_income');

    const previewRows = useMemo(() => {
        const parsed = draft.map((row) => ({
            label: row.label,
            capital: parseYenInput(row.capital),
            capital_surplus: parseYenInput(row.capital_surplus),
            retained_earnings: parseYenInput(row.retained_earnings),
            treasury: parseYenInput(row.treasury),
        }));

        const movementTotals = parsed.reduce(
            (totals, row) => ({
                capital: totals.capital + row.capital,
                capital_surplus: totals.capital_surplus + row.capital_surplus,
                retained_earnings: totals.retained_earnings + row.retained_earnings,
                treasury: totals.treasury + row.treasury,
            }),
            { capital: 0, capital_surplus: 0, retained_earnings: 0, treasury: 0 },
        );

        const changeSubtotal = {
            capital: movementTotals.capital,
            capital_surplus: movementTotals.capital_surplus,
            retained_earnings: movementTotals.retained_earnings + (netIncome?.retained_earnings ?? 0),
            treasury: movementTotals.treasury,
        };

        const ending = {
            capital: (beginning?.capital ?? 0) + changeSubtotal.capital,
            capital_surplus: (beginning?.capital_surplus ?? 0) + changeSubtotal.capital_surplus,
            retained_earnings: (beginning?.retained_earnings ?? 0) + changeSubtotal.retained_earnings,
            treasury: (beginning?.treasury ?? 0) + changeSubtotal.treasury,
        };

        return { parsed, changeSubtotal, ending };
    }, [draft, beginning, netIncome]);

    const updateDraft = (index: number, field: keyof DraftRow, value: string) => {
        setDraft((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, [field]: value } : row)));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const rows = draft
            .map((row) => ({
                label: row.label.trim(),
                capital: parseYenInput(row.capital),
                capital_surplus: parseYenInput(row.capital_surplus),
                retained_earnings: parseYenInput(row.retained_earnings),
                treasury: parseYenInput(row.treasury),
            }))
            .filter((row) => row.label !== '' || rowTotal(row) !== 0);

        form.transform(() => ({ rows }));
        form.put(route('reports.equity.update'), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const revert = () => {
        router.delete(route('reports.equity.revert'), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <div className="space-y-4">
            {!report.is_consistent && (
                <Alert variant="destructive">
                    <AlertCircle className="size-4" />
                    <AlertDescription>
                        株主資本等変動計算書の期末残高と貸借対照表の純資産残高が一致していません。
                        {report.mismatches.map((mismatch) => (
                            <span key={mismatch.account_name} className="mt-1 block">
                                {mismatch.account_name}：差額 {formatYen(mismatch.difference)}円
                            </span>
                        ))}
                    </AlertDescription>
                </Alert>
            )}

            {report.is_manual && (
                <Alert>
                    <AlertCircle className="size-4" />
                    <AlertDescription>
                        変動事由を手動で保存しています。期首残高と当期純利益は引き続き自動反映されます。
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex flex-wrap items-center justify-end gap-2 print:hidden">
                {editing ? (
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setEditing(false)}>
                            キャンセル
                        </Button>
                        {report.is_manual && (
                            <Button type="button" variant="outline" size="sm" onClick={revert}>
                                <RotateCcw className="size-4" />
                                自動計算に戻す
                            </Button>
                        )}
                        <Button type="submit" size="sm" form="equity-statement-form" disabled={form.processing}>
                            {form.processing ? '保存中...' : '変動事由を保存'}
                        </Button>
                    </>
                ) : (
                    <>
                        {report.is_manual && (
                            <Button type="button" variant="outline" size="sm" onClick={revert}>
                                <RotateCcw className="size-4" />
                                自動計算に戻す
                            </Button>
                        )}
                        <Button type="button" variant="outline" size="sm" onClick={() => setEditing(true)}>
                            <Pencil className="size-4" />
                            変動事由を修正
                        </Button>
                    </>
                )}
            </div>

            {editing ? (
                <form id="equity-statement-form" onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        期首残高と当期純利益は自動です。増資・配当など自動判定できないときは、変動事由と金額を追加・修正してください。
                    </p>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="bg-muted/50 border-b">
                                    <th className="px-3 py-3 text-left font-medium">区分</th>
                                    <th className="px-3 py-3 text-right font-medium">資本金</th>
                                    <th className="px-3 py-3 text-right font-medium">資本剰余金</th>
                                    <th className="px-3 py-3 text-right font-medium">利益剰余金</th>
                                    <th className="px-3 py-3 text-right font-medium">自己株式</th>
                                    <th className="px-3 py-3 text-right font-medium">株主資本合計</th>
                                    <th className="w-10 px-2 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {beginning && <ReadOnlyRow row={beginning} />}
                                {netIncome && <ReadOnlyRow row={netIncome} />}
                                {draft.map((row, index) => {
                                    const parsed = previewRows.parsed[index];
                                    return (
                                        <tr key={index} className="border-b last:border-0">
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    value={row.label}
                                                    placeholder="例：剰余金の配当"
                                                    className="h-9"
                                                    onChange={(event) => updateDraft(index, 'label', event.target.value)}
                                                />
                                            </td>
                                            {(['capital', 'capital_surplus', 'retained_earnings', 'treasury'] as const).map((field) => (
                                                <td key={field} className="px-2 py-1.5">
                                                    <Input
                                                        inputMode="numeric"
                                                        className="h-9 text-right tabular-nums"
                                                        placeholder="0"
                                                        value={row[field]}
                                                        onChange={(event) => updateDraft(index, field, event.target.value)}
                                                    />
                                                </td>
                                            ))}
                                            <td className="px-3 py-2.5 text-right tabular-nums">{formatYen(rowTotal(parsed))}</td>
                                            <td className="px-2 py-1.5">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-muted-foreground"
                                                    onClick={() => setDraft((current) => current.filter((_, rowIndex) => rowIndex !== index))}
                                                    aria-label="行を削除"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                                <ReadOnlyRow
                                    row={{
                                        label: '当期変動額合計',
                                        kind: 'subtotal',
                                        ...previewRows.changeSubtotal,
                                        total: rowTotal(previewRows.changeSubtotal),
                                    }}
                                />
                                <ReadOnlyRow
                                    row={{
                                        label: '当期末残高',
                                        kind: 'ending',
                                        ...previewRows.ending,
                                        total: rowTotal(previewRows.ending),
                                    }}
                                />
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setDraft((current) => [...current, emptyDraftRow()])}>
                            <Plus className="size-4" />
                            変動事由を追加
                        </Button>
                        <InputError message={form.errors.rows ?? Object.values(form.errors)[0]} />
                    </div>
                </form>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-[13px]">
                        <thead>
                            <tr className="bg-muted/50 border-b">
                                <th className="px-4 py-3 text-left font-medium">区分</th>
                                <th className="px-4 py-3 text-right font-medium">資本金</th>
                                <th className="px-4 py-3 text-right font-medium">資本剰余金</th>
                                <th className="px-4 py-3 text-right font-medium">利益剰余金</th>
                                <th className="px-4 py-3 text-right font-medium">自己株式</th>
                                <th className="px-4 py-3 text-right font-medium">株主資本合計</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((row) => (
                                <tr
                                    key={`${row.kind}-${row.label}`}
                                    className={cn(
                                        'border-b last:border-0',
                                        (row.kind === 'beginning' || row.kind === 'subtotal' || row.kind === 'ending') &&
                                            'font-semibold',
                                    )}
                                >
                                    <td className="px-4 py-2.5">{row.label}</td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {row.kind === 'movement' && row.capital === 0 ? '' : formatYen(row.capital)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {row.kind === 'movement' && row.capital_surplus === 0 ? '' : formatYen(row.capital_surplus)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {row.kind === 'movement' && row.retained_earnings === 0 ? '' : formatYen(row.retained_earnings)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {row.kind === 'movement' && row.treasury === 0 ? '' : formatYen(row.treasury)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">{formatYen(row.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function ReadOnlyRow({ row }: { row: EquityStatementRow }) {
    const total = row.total || rowTotal(row);

    return (
        <tr
            className={cn(
                'border-b last:border-0',
                (row.kind === 'beginning' || row.kind === 'subtotal' || row.kind === 'ending') && 'font-semibold',
            )}
        >
            <td className="px-3 py-2.5">{row.label}</td>
            <td className="px-3 py-2.5 text-right tabular-nums">{formatYen(row.capital)}</td>
            <td className="px-3 py-2.5 text-right tabular-nums">{formatYen(row.capital_surplus)}</td>
            <td className="px-3 py-2.5 text-right tabular-nums">{formatYen(row.retained_earnings)}</td>
            <td className="px-3 py-2.5 text-right tabular-nums">{formatYen(row.treasury)}</td>
            <td className="px-3 py-2.5 text-right tabular-nums">{formatYen(total)}</td>
            <td />
        </tr>
    );
}
