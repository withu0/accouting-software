import ConsumptionTaxFields, { defaultCategoryForAccount, type TaxCategoryOption } from '@/components/consumption-tax-fields';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { AlertCircle, Pencil } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

export interface CreditCardImportRowEditValues {
    transaction_date: string;
    description: string;
    amount: number;
    account_id: number | null;
    consumption_tax_category: string;
    has_qualified_invoice: boolean;
}

interface CreditCardImportRowEditDialogProps {
    rowId: number;
    initialValues: CreditCardImportRowEditValues;
    accountGroups: Record<string, { id: number; name: string; default_consumption_tax_category?: string | null }[]>;
    expenseAccounts?: Array<{ id: number; default_consumption_tax_category?: string | null }>;
    purchaseTaxCategories: TaxCategoryOption[];
    hasActiveFiscalYear: boolean;
    trigger?: React.ReactNode;
}

export default function CreditCardImportRowEditDialog({
    rowId,
    initialValues,
    accountGroups,
    expenseAccounts = [],
    purchaseTaxCategories,
    hasActiveFiscalYear,
    trigger,
}: CreditCardImportRowEditDialogProps) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [transactionDate, setTransactionDate] = useState(initialValues.transaction_date);
    const [description, setDescription] = useState(initialValues.description);
    const [amount, setAmount] = useState(String(initialValues.amount));
    const [accountId, setAccountId] = useState(
        initialValues.account_id !== null ? String(initialValues.account_id) : '',
    );
    const [consumptionTaxCategory, setConsumptionTaxCategory] = useState(initialValues.consumption_tax_category);
    const [hasQualifiedInvoice, setHasQualifiedInvoice] = useState(initialValues.has_qualified_invoice);

    useEffect(() => {
        if (open) {
            setTransactionDate(initialValues.transaction_date);
            setDescription(initialValues.description);
            setAmount(String(initialValues.amount));
            setAccountId(initialValues.account_id !== null ? String(initialValues.account_id) : '');
            setConsumptionTaxCategory(initialValues.consumption_tax_category);
            setHasQualifiedInvoice(initialValues.has_qualified_invoice);
            setErrors({});
        }
    }, [open, initialValues]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        setProcessing(true);
        setErrors({});

        router.patch(
            route('credit-card-import.rows.update', rowId),
            {
                transaction_date: transactionDate,
                description,
                amount: parseInt(amount, 10),
                account_id: parseInt(accountId, 10),
                consumption_tax_category: consumptionTaxCategory,
                has_qualified_invoice: hasQualifiedInvoice,
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (formErrors) => setErrors(formErrors as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="ghost" size="sm">
                        <Pencil className="size-4" />
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>取引を編集</DialogTitle>
                <DialogDescription>カード利用明細の内容を変更します。記帳済みの場合は仕訳も更新されます。</DialogDescription>

                {!hasActiveFiscalYear && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>会計期間が未設定のため編集できません。</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`edit-date-${rowId}`}>利用日</Label>
                        <Input
                            id={`edit-date-${rowId}`}
                            type="date"
                            value={transactionDate}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setTransactionDate(e.target.value)}
                        />
                        <InputError message={errors.transaction_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-description-${rowId}`}>店名</Label>
                        <Input
                            id={`edit-description-${rowId}`}
                            value={description}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setDescription(e.target.value)}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-amount-${rowId}`}>金額</Label>
                        <Input
                            id={`edit-amount-${rowId}`}
                            type="number"
                            min={1}
                            value={amount}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-account-${rowId}`}>経費科目</Label>
                        <Select
                            value={accountId}
                            onValueChange={(value) => {
                                setAccountId(value);
                                setConsumptionTaxCategory(
                                    defaultCategoryForAccount(Number(value), expenseAccounts, 'taxable_purchase_10'),
                                );
                            }}
                            disabled={!hasActiveFiscalYear || processing}
                        >
                            <SelectTrigger id={`edit-account-${rowId}`}>
                                <SelectValue placeholder="経費科目を選択" />
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
                        <InputError message={errors.account_id} />
                    </div>

                    <ConsumptionTaxFields
                        idPrefix={`edit-tax-${rowId}`}
                        category={consumptionTaxCategory}
                        hasQualifiedInvoice={hasQualifiedInvoice}
                        categoryOptions={purchaseTaxCategories}
                        onCategoryChange={setConsumptionTaxCategory}
                        onQualifiedInvoiceChange={setHasQualifiedInvoice}
                        categoryError={errors.consumption_tax_category}
                        qualifiedInvoiceError={errors.has_qualified_invoice}
                        showQualifiedInvoice
                    />

                    {errors.row && <InputError message={errors.row} />}

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline" disabled={processing}>
                                キャンセル
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={!hasActiveFiscalYear || processing || !accountId}>
                            {processing ? '保存中...' : '保存する'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
