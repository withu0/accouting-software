import InputError from '@/components/input-error';
import { type TaxCategoryOption, defaultCategoryForAccount } from '@/components/consumption-tax-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Minus, Plus } from 'lucide-react';

export interface TransferJournalSide {
    account_id: string;
    amount: string;
    consumption_tax_category: string;
}

export interface TransferJournalRow {
    key: string;
    debit: TransferJournalSide;
    credit: TransferJournalSide;
}

export interface TransferJournalLinePayload {
    account_id: number;
    debit: number;
    credit: number;
    consumption_tax_category: string;
}

interface AccountOption {
    id: number;
    name: string;
    default_consumption_tax_category?: string | null;
}

interface TransferJournalRowTableProps {
    rows: TransferJournalRow[];
    accountGroups: Record<string, AccountOption[]>;
    transferTaxCategories: TaxCategoryOption[];
    disabled?: boolean;
    lineErrors?: Record<string, string>;
    onChange: (rows: TransferJournalRow[]) => void;
}

export function createEmptySide(): TransferJournalSide {
    return {
        account_id: '',
        amount: '',
        consumption_tax_category: 'out_of_scope',
    };
}

export function createEmptyRow(): TransferJournalRow {
    return {
        key: crypto.randomUUID(),
        debit: createEmptySide(),
        credit: createEmptySide(),
    };
}

function flattenAccounts(accountGroups: Record<string, AccountOption[]>): AccountOption[] {
    return Object.values(accountGroups).flat();
}

function resolveTransferTaxCategory(
    accountId: string,
    accounts: AccountOption[],
    transferTaxCategories: TaxCategoryOption[],
): string {
    const allowed = transferTaxCategories.map((option) => option.value);
    const suggested = defaultCategoryForAccount(
        accountId ? parseInt(accountId, 10) : null,
        accounts,
        'out_of_scope',
    );

    return allowed.includes(suggested) ? suggested : 'out_of_scope';
}

export function flattenRowsToLines(rows: TransferJournalRow[]): TransferJournalLinePayload[] {
    const lines: TransferJournalLinePayload[] = [];

    for (const row of rows) {
        const debitAmount = parseInt(row.debit.amount, 10);
        if (row.debit.account_id && debitAmount > 0) {
            lines.push({
                account_id: parseInt(row.debit.account_id, 10),
                debit: debitAmount,
                credit: 0,
                consumption_tax_category: row.debit.consumption_tax_category,
            });
        }

        const creditAmount = parseInt(row.credit.amount, 10);
        if (row.credit.account_id && creditAmount > 0) {
            lines.push({
                account_id: parseInt(row.credit.account_id, 10),
                debit: 0,
                credit: creditAmount,
                consumption_tax_category: row.credit.consumption_tax_category,
            });
        }
    }

    return lines;
}

export function sumRowTotals(rows: TransferJournalRow[]): { debitTotal: number; creditTotal: number } {
    let debitTotal = 0;
    let creditTotal = 0;

    for (const row of rows) {
        const debitAmount = parseInt(row.debit.amount, 10);
        if (!Number.isNaN(debitAmount) && debitAmount > 0) {
            debitTotal += debitAmount;
        }

        const creditAmount = parseInt(row.credit.amount, 10);
        if (!Number.isNaN(creditAmount) && creditAmount > 0) {
            creditTotal += creditAmount;
        }
    }

    return { debitTotal, creditTotal };
}

function AccountSelect({
    id,
    value,
    accountGroups,
    disabled,
    onValueChange,
}: {
    id: string;
    value: string;
    accountGroups: Record<string, AccountOption[]>;
    disabled?: boolean;
    onValueChange: (value: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onValueChange} disabled={disabled}>
            <SelectTrigger id={id}>
                <SelectValue placeholder="科目を選択" />
            </SelectTrigger>
            <SelectContent>
                {Object.entries(accountGroups).map(([groupLabel, accounts]) => (
                    <SelectGroup key={groupLabel}>
                        <SelectLabel>{groupLabel}</SelectLabel>
                        {accounts.map((account) => (
                            <SelectItem key={account.id} value={String(account.id)}>
                                {account.name}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                ))}
            </SelectContent>
        </Select>
    );
}

function TaxSelect({
    id,
    value,
    options,
    disabled,
    onValueChange,
}: {
    id: string;
    value: string;
    options: TaxCategoryOption[];
    disabled?: boolean;
    onValueChange: (value: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onValueChange} disabled={disabled}>
            <SelectTrigger id={id}>
                <SelectValue placeholder="税区分" />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export default function TransferJournalRowTable({
    rows,
    accountGroups,
    transferTaxCategories,
    disabled = false,
    lineErrors,
    onChange,
}: TransferJournalRowTableProps) {
    const accounts = flattenAccounts(accountGroups);

    const updateRow = (index: number, nextRow: TransferJournalRow) => {
        onChange(rows.map((row, rowIndex) => (rowIndex === index ? nextRow : row)));
    };

    const updateSide = (
        index: number,
        side: 'debit' | 'credit',
        patch: Partial<TransferJournalSide>,
    ) => {
        const row = rows[index];
        updateRow(index, {
            ...row,
            [side]: { ...row[side], ...patch },
        });
    };

    const addRow = () => {
        onChange([...rows, createEmptyRow()]);
    };

    const removeRow = (index: number) => {
        if (rows.length <= 1) {
            return;
        }

        onChange(rows.filter((_, rowIndex) => rowIndex !== index));
    };

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr className="bg-muted/50 border-b">
                            <th className="px-3 py-2 text-left font-medium" colSpan={3}>
                                借方
                            </th>
                            <th className="px-3 py-2 text-left font-medium" colSpan={3}>
                                貸方
                            </th>
                            <th className="px-3 py-2 text-right font-medium w-24">操作</th>
                        </tr>
                        <tr className="bg-muted/30 border-b text-muted-foreground text-xs">
                            <th className="px-3 py-2 text-left font-normal">勘定科目</th>
                            <th className="px-3 py-2 text-left font-normal w-28">金額</th>
                            <th className="px-3 py-2 text-left font-normal w-36">税区分</th>
                            <th className="px-3 py-2 text-left font-normal">勘定科目</th>
                            <th className="px-3 py-2 text-left font-normal w-28">金額</th>
                            <th className="px-3 py-2 text-left font-normal w-36">税区分</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={row.key} className="border-b align-top last:border-0">
                                <td className="px-3 py-2">
                                    <AccountSelect
                                        id={`transfer-debit-account-${row.key}`}
                                        value={row.debit.account_id}
                                        accountGroups={accountGroups}
                                        disabled={disabled}
                                        onValueChange={(value) => {
                                            updateSide(index, 'debit', {
                                                account_id: value,
                                                consumption_tax_category: resolveTransferTaxCategory(
                                                    value,
                                                    accounts,
                                                    transferTaxCategories,
                                                ),
                                            });
                                        }}
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <Input
                                        type="number"
                                        min="0"
                                        value={row.debit.amount}
                                        disabled={disabled}
                                        onChange={(event) => updateSide(index, 'debit', { amount: event.target.value })}
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <TaxSelect
                                        id={`transfer-debit-tax-${row.key}`}
                                        value={row.debit.consumption_tax_category}
                                        options={transferTaxCategories}
                                        disabled={disabled}
                                        onValueChange={(value) =>
                                            updateSide(index, 'debit', { consumption_tax_category: value })
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <AccountSelect
                                        id={`transfer-credit-account-${row.key}`}
                                        value={row.credit.account_id}
                                        accountGroups={accountGroups}
                                        disabled={disabled}
                                        onValueChange={(value) => {
                                            updateSide(index, 'credit', {
                                                account_id: value,
                                                consumption_tax_category: resolveTransferTaxCategory(
                                                    value,
                                                    accounts,
                                                    transferTaxCategories,
                                                ),
                                            });
                                        }}
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <Input
                                        type="number"
                                        min="0"
                                        value={row.credit.amount}
                                        disabled={disabled}
                                        onChange={(event) => updateSide(index, 'credit', { amount: event.target.value })}
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <TaxSelect
                                        id={`transfer-credit-tax-${row.key}`}
                                        value={row.credit.consumption_tax_category}
                                        options={transferTaxCategories}
                                        disabled={disabled}
                                        onValueChange={(value) =>
                                            updateSide(index, 'credit', { consumption_tax_category: value })
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={disabled || rows.length <= 1}
                                            onClick={() => removeRow(index)}
                                            aria-label="行を削除"
                                        >
                                            <Minus className="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={disabled}
                                            onClick={addRow}
                                            aria-label="行を追加"
                                        >
                                            <Plus className="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {lineErrors && Object.keys(lineErrors).length > 0 && (
                <div className="space-y-1">
                    {Object.entries(lineErrors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                </div>
            )}

            <Button type="button" variant="outline" disabled={disabled} onClick={addRow}>
                <Plus className="size-4" />
                行を追加
            </Button>
        </div>
    );
}
