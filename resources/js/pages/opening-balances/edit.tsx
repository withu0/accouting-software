import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { SummaryStrip } from '@/components/summary-strip';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { formatYen, parseYenInput } from '@/lib/yen';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface OpeningAccount {
    id: number;
    code: string;
    name: string;
    type: string;
    is_contra: boolean;
    is_required: boolean;
    amount: number;
}

interface FormState {
    groups: Record<string, OpeningAccount[]>;
    is_confirmed: boolean;
    confirmed_at: string | null;
    has_period_journals: boolean;
    fiscal_year: {
        id: number;
        start_date: string;
        end_date: string;
    };
}

interface Props {
    hasActiveFiscalYear: boolean;
    form: FormState | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'その他', href: route('other') },
    { title: '開始残高', href: route('opening-balances.edit') },
];

const groupOrder = ['資産', '負債', '純資産'] as const;

function initialAmounts(form: FormState | null): Record<number, string> {
    const amounts: Record<number, string> = {};
    if (form === null) {
        return amounts;
    }

    for (const accounts of Object.values(form.groups)) {
        for (const account of accounts) {
            amounts[account.id] = account.amount === 0 ? '' : String(account.amount);
        }
    }

    return amounts;
}

export default function OpeningBalanceEdit({ hasActiveFiscalYear, form }: Props) {
    const [amounts, setAmounts] = useState<Record<number, string>>(() => initialAmounts(form));
    const saveForm = useForm({});
    const confirmForm = useForm({});

    const lines = useMemo(() => {
        if (form === null) {
            return [];
        }

        return Object.values(form.groups)
            .flat()
            .map((account) => ({
                account_id: account.id,
                amount: parseYenInput(amounts[account.id] ?? ''),
            }));
    }, [amounts, form]);

    const totals = useMemo(() => {
        if (form === null) {
            return { asset_total: 0, liability_total: 0, equity_total: 0, difference: 0 };
        }

        let assetTotal = 0;
        let liabilityTotal = 0;
        let equityTotal = 0;

        for (const account of Object.values(form.groups).flat()) {
            const raw = parseYenInput(amounts[account.id] ?? '');
            const signed = account.is_contra ? -raw : raw;

            if (account.type === 'asset') {
                assetTotal += signed;
            } else if (account.type === 'liability') {
                liabilityTotal += signed;
            } else if (account.type === 'equity') {
                equityTotal += signed;
            }
        }

        return {
            asset_total: assetTotal,
            liability_total: liabilityTotal,
            equity_total: equityTotal,
            difference: assetTotal - (liabilityTotal + equityTotal),
        };
    }, [amounts, form]);

    const isBalanced = totals.difference === 0;
    const periodLabel = form
        ? `${formatDate(form.fiscal_year.start_date)} 〜 ${formatDate(form.fiscal_year.end_date)}`
        : '未設定';

    const payload = { lines };

    const submitDraft: FormEventHandler = (e) => {
        e.preventDefault();
        saveForm.transform(() => payload);
        saveForm.put(route('opening-balances.save'), { preserveScroll: true });
    };

    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        if (!isBalanced) {
            return;
        }

        confirmForm.transform(() => payload);
        confirmForm.post(route('opening-balances.confirm'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="開始残高" />
            <PageContainer size="full">
                <PageHeader
                    title="開始残高"
                    description="前期の貸借対照表を見ながら、今期開始時点の残高を入力します。確定すると総勘定元帳の繰越残高と貸借対照表に反映されます。"
                />

                <FlashAlert />

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

                {form && (
                    <>
                        <SummaryStrip
                            items={[
                                { label: '対象期間', value: periodLabel },
                                {
                                    label: '状態',
                                    value: form.is_confirmed ? '確定済み' : '未確定',
                                    variant: form.is_confirmed ? 'success' : 'warning',
                                },
                                {
                                    label: '資産合計',
                                    value: formatYen(totals.asset_total),
                                },
                                {
                                    label: '負債＋純資産',
                                    value: formatYen(totals.liability_total + totals.equity_total),
                                },
                                {
                                    label: '差額',
                                    value: isBalanced ? '一致' : `${formatYen(totals.difference)}円`,
                                    variant: isBalanced ? 'success' : 'warning',
                                    highlight: true,
                                },
                            ]}
                        />

                        {form.has_period_journals && (
                            <Alert>
                                <AlertCircle className="size-4" />
                                <AlertDescription>
                                    開始残高を変更すると現在の残高も変更されます。今期の仕訳は残ったまま、繰越残高だけが書き換わります。
                                </AlertDescription>
                            </Alert>
                        )}

                        {!isBalanced && (
                            <Alert variant="destructive">
                                <AlertCircle className="size-4" />
                                <AlertDescription>
                                    貸借が一致していません。差額：{formatYen(Math.abs(totals.difference))}円
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={submitConfirm} className="space-y-6">
                            <div className="grid gap-6 xl:grid-cols-3">
                                {groupOrder.map((groupName) => (
                                    <FormSection
                                        key={groupName}
                                        title={`${groupName}の部`}
                                        description={
                                            groupName === '純資産'
                                                ? '資本金・繰越利益剰余金は必ず入力してください（0円可）。'
                                                : groupName === '資産'
                                                  ? '前期BSの金額を入力します。減価償却累計額は控除額として資産合計から差し引きます。'
                                                  : '前期BSの負債残高を入力します。'
                                        }
                                    >
                                        <div className="space-y-3">
                                            {(form.groups[groupName] ?? []).map((account) => (
                                                <div key={account.id} className="grid grid-cols-[1fr_9rem] items-center gap-3">
                                                    <Label htmlFor={`account-${account.id}`} className="text-sm leading-snug">
                                                        <span className="text-muted-foreground mr-2 font-mono text-[11px]">
                                                            {account.code}
                                                        </span>
                                                        {account.name}
                                                        {account.is_required && (
                                                            <span className="text-primary ml-1 text-[11px]">必須</span>
                                                        )}
                                                        {account.is_contra && (
                                                            <span className="text-muted-foreground ml-1 text-[11px]">控除</span>
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id={`account-${account.id}`}
                                                        inputMode="numeric"
                                                        className="text-right tabular-nums"
                                                        placeholder="0"
                                                        value={amounts[account.id] ?? ''}
                                                        onChange={(e) =>
                                                            setAmounts((prev) => ({
                                                                ...prev,
                                                                [account.id]: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </FormSection>
                                ))}
                            </div>

                            <InputError message={saveForm.errors.lines ?? confirmForm.errors.lines} />

                            <StickyActionBar>
                                <div className="text-muted-foreground flex flex-col gap-1 text-sm">
                                    <span>
                                        資産 {formatYen(totals.asset_total)} ／ 負債 {formatYen(totals.liability_total)} ／ 純資産{' '}
                                        {formatYen(totals.equity_total)}
                                    </span>
                                    <span className={isBalanced ? 'text-green-700' : 'text-destructive'}>
                                        {isBalanced
                                            ? '貸借は一致しています'
                                            : `貸借が一致していません。差額：${formatYen(Math.abs(totals.difference))}円`}
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={saveForm.processing || confirmForm.processing}
                                        onClick={submitDraft}
                                    >
                                        {saveForm.processing ? '保存中...' : '下書き保存'}
                                    </Button>
                                    <Button type="submit" disabled={!isBalanced || saveForm.processing || confirmForm.processing}>
                                        {confirmForm.processing ? '確定中...' : '開始残高を確定'}
                                    </Button>
                                </div>
                            </StickyActionBar>
                        </form>
                    </>
                )}

                {!form && hasActiveFiscalYear && (
                    <p className="text-muted-foreground text-sm">開始残高を表示できません。</p>
                )}
            </PageContainer>
        </AppLayout>
    );
}
