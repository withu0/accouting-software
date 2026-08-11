import { FlashAlert } from '@/components/flash-alert';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { Building2, Check, ShieldOff } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface TaxEntityOption {
    value: string;
    label: string;
    description: string;
}

interface Props {
    taxEntityStatus: string;
    taxEntityStatusOptions: TaxEntityOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'その他', href: route('other') },
    { title: '消費税設定', href: route('consumption-tax.edit') },
];

const optionIcons: Record<string, typeof Building2> = {
    taxable: Building2,
    exempt: ShieldOff,
};

export default function ConsumptionTaxSettings({ taxEntityStatus, taxEntityStatusOptions }: Props) {
    const form = useForm({
        tax_entity_status: taxEntityStatus,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('consumption-tax.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="消費税設定" />
            <PageContainer size="narrow">
                <PageHeader
                    title="消費税設定"
                    description="免税事業者か課税事業者かを選択します。この設定が仕訳・取込全体の消費税処理の基準になります。"
                />

                <FlashAlert />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {taxEntityStatusOptions.map((option) => {
                            const selected = form.data.tax_entity_status === option.value;
                            const Icon = optionIcons[option.value] ?? Building2;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => form.setData('tax_entity_status', option.value)}
                                    className={cn(
                                        'relative flex h-full flex-col items-start gap-3 rounded-xl border p-5 text-left transition-all',
                                        selected
                                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                            : 'border-border/60 hover:border-primary/40 hover:bg-muted/40',
                                    )}
                                >
                                    {selected && (
                                        <span className="bg-primary text-primary-foreground absolute top-3 right-3 flex size-6 items-center justify-center rounded-full">
                                            <Check className="size-3.5" strokeWidth={3} />
                                        </span>
                                    )}
                                    <div
                                        className={cn(
                                            'flex size-11 items-center justify-center rounded-xl ring-1',
                                            selected
                                                ? 'bg-primary/15 text-primary ring-primary/20'
                                                : 'bg-muted text-muted-foreground ring-border/50',
                                        )}
                                    >
                                        <Icon className="size-5" />
                                    </div>
                                    <div className="space-y-1.5 pr-6">
                                        <p className="text-base font-semibold">{option.label}</p>
                                        <p className="text-muted-foreground text-sm leading-relaxed">
                                            {option.description}
                                        </p>
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    <InputError message={form.errors.tax_entity_status} />

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={form.processing}>
                            保存する
                        </Button>
                        <Transition
                            show={form.recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-green-600">保存しました</p>
                        </Transition>
                    </div>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
