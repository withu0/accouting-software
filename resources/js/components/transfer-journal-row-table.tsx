import InputError from '@/components/input-error';
import { type TaxCategoryOption, defaultCategoryForAccount } from '@/components/consumption-tax-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useIsTaxExempt } from '@/hooks/use-is-tax-exempt';
import { cn } from '@/lib/utils';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Check, ChevronsUpDown, Minus, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface TransferJournalSide {
    account_id: string;
    amount: string;
    consumption_tax_category: string;
}

export interface TransferJournalRow {
    key: string;
    debit: TransferJournalSide;
    credit: TransferJournalSide;
    creditAmountAutoFilled?: boolean;
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
        creditAmountAutoFilled: true,
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

function AccountCombobox({
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
    const [query, setQuery] = useState('');
    const accounts = useMemo(() => flattenAccounts(accountGroups), [accountGroups]);
    const selected = accounts.find((account) => String(account.id) === value) ?? null;

    const filteredGroups = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        if (!normalized) {
            return accountGroups;
        }

        const next: Record<string, AccountOption[]> = {};
        for (const [groupLabel, groupAccounts] of Object.entries(accountGroups)) {
            const matched = groupAccounts.filter((account) => account.name.toLowerCase().includes(normalized));
            if (matched.length > 0) {
                next[groupLabel] = matched;
            }
        }

        return next;
    }, [accountGroups, query]);

    return (
        <Combobox
            value={selected}
            onChange={(account: AccountOption | null) => {
                onValueChange(account ? String(account.id) : '');
                setQuery('');
            }}
            disabled={disabled}
            immediate
        >
            <div className="relative">
                <ComboboxInput
                    id={id}
                    className={cn(
                        'border-input bg-background ring-offset-background placeholder:text-muted-foreground flex h-9 w-full rounded-md border px-3 py-1 pr-8 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                    )}
                    displayValue={(account: AccountOption | null) => account?.name ?? ''}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="科目を入力または選択"
                    autoComplete="off"
                />
                <ComboboxButton className="absolute inset-y-0 right-0 flex items-center px-2">
                    <ChevronsUpDown className="text-muted-foreground size-4" />
                </ComboboxButton>

                <ComboboxOptions
                    anchor="bottom start"
                    portal
                    className={cn(
                        'border-border bg-popover z-50 max-h-60 w-[var(--input-width)] overflow-auto rounded-md border py-1 shadow-md',
                        'empty:invisible [--anchor-gap:4px]',
                    )}
                >
                    {Object.keys(filteredGroups).length === 0 ? (
                        <div className="text-muted-foreground px-3 py-2 text-sm">該当する科目がありません</div>
                    ) : (
                        Object.entries(filteredGroups).map(([groupLabel, groupAccounts]) => (
                            <div key={groupLabel}>
                                <div className="text-muted-foreground px-3 py-1.5 text-xs font-medium">{groupLabel}</div>
                                {groupAccounts.map((account) => (
                                    <ComboboxOption
                                        key={account.id}
                                        value={account}
                                        className="data-focus:bg-accent data-focus:text-accent-foreground relative flex cursor-pointer items-center gap-2 px-3 py-2 text-sm outline-hidden select-none"
                                    >
                                        {({ selected: isSelected }) => (
                                            <>
                                                <Check
                                                    className={cn('size-4 shrink-0', isSelected ? 'opacity-100' : 'opacity-0')}
                                                />
                                                <span>{account.name}</span>
                                            </>
                                        )}
                                    </ComboboxOption>
                                ))}
                            </div>
                        ))
                    )}
                </ComboboxOptions>
            </div>
        </Combobox>
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
    const isTaxExempt = useIsTaxExempt();
    const accounts = flattenAccounts(accountGroups);
    const taxColSpan = isTaxExempt ? 2 : 3;

    const updateRow = (index: number, nextRow: TransferJournalRow) => {
        onChange(rows.map((row, rowIndex) => (rowIndex === index ? nextRow : row)));
    };

    const updateSide = (
        index: number,
        side: 'debit' | 'credit',
        patch: Partial<TransferJournalSide>,
        options?: { creditAmountAutoFilled?: boolean },
    ) => {
        const row = rows[index];
        const nextRow: TransferJournalRow = {
            ...row,
            [side]: { ...row[side], ...patch },
        };

        if (options && 'creditAmountAutoFilled' in options) {
            nextRow.creditAmountAutoFilled = options.creditAmountAutoFilled;
        }

        updateRow(index, nextRow);
    };

    const handleDebitAmountChange = (index: number, amount: string) => {
        const row = rows[index];
        const shouldAutoFill = row.creditAmountAutoFilled !== false
            || row.credit.amount === ''
            || row.credit.amount === row.debit.amount;

        if (shouldAutoFill) {
            updateRow(index, {
                ...row,
                debit: { ...row.debit, amount },
                credit: { ...row.credit, amount },
                creditAmountAutoFilled: true,
            });
            return;
        }

        updateSide(index, 'debit', { amount });
    };

    const handleCreditAmountChange = (index: number, amount: string) => {
        updateSide(index, 'credit', { amount }, { creditAmountAutoFilled: false });
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
            <div className="rounded-lg border">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b">
                                <th className="px-3 py-2 text-left font-medium" colSpan={taxColSpan}>
                                    借方
                                </th>
                                <th className="px-3 py-2 text-left font-medium" colSpan={taxColSpan}>
                                    貸方
                                </th>
                                <th className="px-3 py-2 text-right font-medium w-24">操作</th>
                            </tr>
                            <tr className="bg-muted/30 border-b text-muted-foreground text-xs">
                                <th className="px-3 py-2 text-left font-normal min-w-[160px]">勘定科目</th>
                                <th className="px-3 py-2 text-left font-normal w-28">金額</th>
                                {!isTaxExempt && <th className="px-3 py-2 text-left font-normal w-36">税区分</th>}
                                <th className="px-3 py-2 text-left font-normal min-w-[160px]">勘定科目</th>
                                <th className="px-3 py-2 text-left font-normal w-28">金額</th>
                                {!isTaxExempt && <th className="px-3 py-2 text-left font-normal w-36">税区分</th>}
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={row.key} className="border-b align-top last:border-0">
                                    <td className="px-3 py-2 overflow-visible">
                                        <AccountCombobox
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
                                            onChange={(event) => handleDebitAmountChange(index, event.target.value)}
                                        />
                                    </td>
                                    {!isTaxExempt && (
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
                                    )}
                                    <td className="px-3 py-2 overflow-visible">
                                        <AccountCombobox
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
                                            onChange={(event) => handleCreditAmountChange(index, event.target.value)}
                                        />
                                    </td>
                                    {!isTaxExempt && (
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
                                    )}
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
