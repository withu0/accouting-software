import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate, toDateInputValue } from '@/lib/dates';
import { formatYen } from '@/lib/yen';
import { type BreadcrumbItem, type Company, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CalendarDays } from 'lucide-react';
import { FormEventHandler } from 'react';

interface FiscalYear {
    id: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
    closed_at?: string | null;
}

interface YearEndPreview {
    asset_total: number;
    net_income: number;
    next_retained_earnings: number;
    next_start_date: string;
    next_end_date: string;
    already_closed: boolean;
    has_next_period_journals: boolean;
    is_balanced: boolean;
    next_fiscal_year_exists: boolean;
}

interface TaxOption {
    value: string;
    label: string;
}

interface Props {
    company: Company & {
        consumption_tax_method?: string;
        simplified_tax_industry?: string | null;
    };
    fiscalYears: FiscalYear[];
    activeFiscalYear: FiscalYear | null;
    yearEndPreview: YearEndPreview | null;
    consumptionTaxMethods: TaxOption[];
    simplifiedTaxIndustries: TaxOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '会計期間設定', href: route('fiscal-year.edit') },
];

export default function FiscalYearSettings({
    company,
    fiscalYears,
    activeFiscalYear,
    yearEndPreview,
    consumptionTaxMethods,
    simplifiedTaxIndustries,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const isTaxExempt = auth.company?.tax_entity_status === 'exempt';
    const isUpdate = activeFiscalYear !== null;

    const companyForm = useForm({
        name: company.name ?? '',
        representative_name: company.representative_name ?? '',
        address: company.address ?? '',
        consumption_tax_method: company.consumption_tax_method ?? 'standard',
        simplified_tax_industry: company.simplified_tax_industry ?? '',
    });

    const fiscalYearForm = useForm({
        start_date: activeFiscalYear ? toDateInputValue(activeFiscalYear.start_date) : '',
        end_date: activeFiscalYear ? toDateInputValue(activeFiscalYear.end_date) : '',
    });

    const submitCompany: FormEventHandler = (e) => {
        e.preventDefault();
        companyForm.patch(route('company.update'));
    };

    const submitFiscalYear: FormEventHandler = (e) => {
        e.preventDefault();

        if (isUpdate) {
            fiscalYearForm.patch(route('fiscal-year.update', activeFiscalYear.id));
        } else {
            fiscalYearForm.post(route('fiscal-year.store'));
        }
    };

    const closeForm = useForm({});

    const submitClose: FormEventHandler = (e) => {
        e.preventDefault();
        if (activeFiscalYear === null) {
            return;
        }

        closeForm.post(route('fiscal-year.close', activeFiscalYear.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="会計期間設定" />
            <PageContainer size="narrow">
                <PageHeader title="会計期間設定" description="会社情報と会計年度を設定します" />

                <FlashAlert />

                <FormSection
                    title="会社情報"
                    description="決算書PDFのヘッダーに表示される会社名・住所・代表者名を設定します。"
                >
                    <form onSubmit={submitCompany} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">会社名</Label>
                            <Input
                                id="name"
                                value={companyForm.data.name}
                                onChange={(e) => companyForm.setData('name', e.target.value)}
                            />
                            <InputError message={companyForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="representative_name">代表者名</Label>
                            <Input
                                id="representative_name"
                                value={companyForm.data.representative_name}
                                onChange={(e) => companyForm.setData('representative_name', e.target.value)}
                            />
                            <InputError message={companyForm.errors.representative_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address">所在地</Label>
                            <Input
                                id="address"
                                value={companyForm.data.address}
                                onChange={(e) => companyForm.setData('address', e.target.value)}
                                placeholder="東京都千代田区..."
                            />
                            <InputError message={companyForm.errors.address} />
                        </div>

                        {!isTaxExempt && (
                            <div className="grid gap-2">
                                <Label htmlFor="consumption_tax_method">課税方式</Label>
                                <Select
                                    value={companyForm.data.consumption_tax_method}
                                    onValueChange={(v) => companyForm.setData('consumption_tax_method', v)}
                                >
                                    <SelectTrigger id="consumption_tax_method">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {consumptionTaxMethods.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    消費税区分集計レポートの計算方式に影響します。変更時は税区分の設定も合わせて確認してください。
                                </p>
                                <InputError message={companyForm.errors.consumption_tax_method} />
                            </div>
                        )}

                        {!isTaxExempt && companyForm.data.consumption_tax_method === 'simplified' && (
                            <div className="grid gap-2">
                                <Label htmlFor="simplified_tax_industry">簡易課税業種</Label>
                                <Select
                                    value={companyForm.data.simplified_tax_industry}
                                    onValueChange={(v) => companyForm.setData('simplified_tax_industry', v)}
                                >
                                    <SelectTrigger id="simplified_tax_industry">
                                        <SelectValue placeholder="業種を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {simplifiedTaxIndustries.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={companyForm.errors.simplified_tax_industry} />
                            </div>
                        )}

                        {isTaxExempt && (
                            <p className="text-muted-foreground text-sm">
                                現在は免税事業者のため、課税方式の設定は表示されません。変更は「その他 → 消費税設定」から行えます。
                            </p>
                        )}

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={companyForm.processing}>
                                会社情報を保存
                            </Button>
                            <Transition
                                show={companyForm.recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-green-600">保存しました</p>
                            </Transition>
                        </div>
                    </form>
                </FormSection>

                {activeFiscalYear && (
                    <StatCard label="現在の会計期間" icon={CalendarDays}>
                        <p className="text-base font-semibold">
                            {formatDate(activeFiscalYear.start_date)} 〜 {formatDate(activeFiscalYear.end_date)}
                        </p>
                    </StatCard>
                )}

                <FormSection
                    title="会計期間"
                    description="会計年度の開始日と終了日を設定します。仕訳はこの期間内の日付のみ登録できます。"
                >
                    <form onSubmit={submitFiscalYear} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="start_date">開始日</Label>
                            <Input
                                id="start_date"
                                type="date"
                                value={fiscalYearForm.data.start_date}
                                onChange={(e) => fiscalYearForm.setData('start_date', e.target.value)}
                                required
                            />
                            <InputError message={fiscalYearForm.errors.start_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="end_date">終了日</Label>
                            <Input
                                id="end_date"
                                type="date"
                                value={fiscalYearForm.data.end_date}
                                onChange={(e) => fiscalYearForm.setData('end_date', e.target.value)}
                                required
                            />
                            <InputError message={fiscalYearForm.errors.end_date} />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={fiscalYearForm.processing}>
                                {isUpdate ? '更新する' : '設定する'}
                            </Button>

                            <Transition
                                show={fiscalYearForm.recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-green-600">保存しました</p>
                            </Transition>
                        </div>
                    </form>

                    {fiscalYears.length > 1 && (
                        <p className="text-muted-foreground text-sm">登録済みの会計期間: {fiscalYears.length} 件</p>
                    )}
                </FormSection>

                {activeFiscalYear && yearEndPreview && (
                    <FormSection
                        title="決算確定・翌期へ繰越"
                        description="今期末の貸借対照表を翌期の開始残高に引き継ぎます。当期純利益は翌期の繰越利益剰余金に含めます。会計期間は自動では切り替わりません。"
                    >
                        <div className="space-y-4">
                            <div className="grid gap-2 text-sm">
                                <p>資産合計：{formatYen(yearEndPreview.asset_total)}円</p>
                                <p>当期純利益：{formatYen(yearEndPreview.net_income)}円</p>
                                <p>翌期の繰越利益剰余金：{formatYen(yearEndPreview.next_retained_earnings)}円</p>
                                <p>
                                    翌期：{formatDate(yearEndPreview.next_start_date)} 〜 {formatDate(yearEndPreview.next_end_date)}
                                </p>
                            </div>

                            {!yearEndPreview.is_balanced && (
                                <Alert variant="destructive">
                                    <AlertCircle className="size-4" />
                                    <AlertDescription>貸借対照表が一致していないため、決算確定できません。</AlertDescription>
                                </Alert>
                            )}

                            {yearEndPreview.has_next_period_journals && (
                                <Alert>
                                    <AlertCircle className="size-4" />
                                    <AlertDescription>
                                        翌期にすでに仕訳があります。開始残高を更新すると翌期の現在残高も変わります。
                                    </AlertDescription>
                                </Alert>
                            )}

                            {yearEndPreview.already_closed && (
                                <p className="text-muted-foreground text-sm">
                                    この会計期間は決算確定済みです。内容を直した場合は、もう一度確定すると翌期開始残高を上書きします。
                                </p>
                            )}

                            <p className="text-muted-foreground text-sm">
                                開始残高の確認は
                                <Link href={route('opening-balances.edit')} className="mx-1 underline">
                                    開始残高
                                </Link>
                                から行えます。
                            </p>

                            <InputError message={closeForm.errors.close} />

                            <form onSubmit={submitClose}>
                                <Button type="submit" disabled={!yearEndPreview.is_balanced || closeForm.processing}>
                                    {closeForm.processing
                                        ? '確定中...'
                                        : yearEndPreview.already_closed
                                          ? '再確定して翌期へ繰り越す'
                                          : '決算を確定して翌期へ繰り越す'}
                                </Button>
                            </form>
                        </div>
                    </FormSection>
                )}
            </PageContainer>
        </AppLayout>
    );
}
