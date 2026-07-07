import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDate, toDateInputValue } from '@/lib/dates';
import { type BreadcrumbItem, type Company } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { FormEventHandler } from 'react';

interface FiscalYear {
    id: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
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
    consumptionTaxMethods,
    simplifiedTaxIndustries,
}: Props) {
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

                        {companyForm.data.consumption_tax_method === 'simplified' && (
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
            </PageContainer>
        </AppLayout>
    );
}
