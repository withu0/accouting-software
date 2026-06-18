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

export interface BankImportRowEditValues {
    transaction_date: string;
    description: string;
    amount: number;
    account_id: number | null;
}

interface BankImportRowEditDialogProps {
    rowId?: number;
    journalId?: number;
    isDeposit: boolean;
    initialValues: BankImportRowEditValues;
    accountGroups: Record<string, { id: number; name: string }[]>;
    hasActiveFiscalYear: boolean;
    trigger?: React.ReactNode;
}

export default function BankImportRowEditDialog({
    rowId,
    journalId,
    isDeposit,
    initialValues,
    accountGroups,
    hasActiveFiscalYear,
    trigger,
}: BankImportRowEditDialogProps) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [transactionDate, setTransactionDate] = useState(initialValues.transaction_date);
    const [description, setDescription] = useState(initialValues.description);
    const [amount, setAmount] = useState(String(initialValues.amount));
    const [accountId, setAccountId] = useState(
        initialValues.account_id !== null ? String(initialValues.account_id) : '',
    );

    useEffect(() => {
        if (open) {
            setTransactionDate(initialValues.transaction_date);
            setDescription(initialValues.description);
            setAmount(String(initialValues.amount));
            setAccountId(initialValues.account_id !== null ? String(initialValues.account_id) : '');
            setErrors({});
        }
    }, [open, initialValues]);

    const submitUrl =
        journalId !== undefined
            ? route('journals.update', journalId)
            : rowId !== undefined
              ? route('bank-import.rows.update', rowId)
              : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (submitUrl === null) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.patch(
            submitUrl,
            {
                transaction_date: transactionDate,
                description,
                amount: parseInt(amount, 10),
                account_id: parseInt(accountId, 10),
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
                <DialogDescription>
                    {isDeposit ? '入金取引' : '出金取引'}の内容を変更します。記帳済みの場合は仕訳も更新されます。
                </DialogDescription>

                {!hasActiveFiscalYear && (
                    <Alert variant="destructive">
                        <AlertCircle className="size-4" />
                        <AlertDescription>会計期間が未設定のため編集できません。</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`edit-date-${rowId ?? journalId}`}>取引日</Label>
                        <Input
                            id={`edit-date-${rowId ?? journalId}`}
                            type="date"
                            value={transactionDate}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setTransactionDate(e.target.value)}
                        />
                        <InputError message={errors.transaction_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-description-${rowId ?? journalId}`}>摘要</Label>
                        <Input
                            id={`edit-description-${rowId ?? journalId}`}
                            value={description}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setDescription(e.target.value)}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-amount-${rowId ?? journalId}`}>金額</Label>
                        <Input
                            id={`edit-amount-${rowId ?? journalId}`}
                            type="number"
                            min={1}
                            value={amount}
                            disabled={!hasActiveFiscalYear || processing}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`edit-account-${rowId ?? journalId}`}>勘定科目</Label>
                        <Select value={accountId} onValueChange={setAccountId} disabled={!hasActiveFiscalYear || processing}>
                            <SelectTrigger id={`edit-account-${rowId ?? journalId}`}>
                                <SelectValue placeholder="勘定科目を選択" />
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

                    {errors.row && <InputError message={errors.row} />}
                    {errors.journal && <InputError message={errors.journal} />}

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
