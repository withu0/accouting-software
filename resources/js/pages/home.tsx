import { FeatureCard } from '@/components/feature-card';
import { FiscalYearBadge } from '@/components/fiscal-year-badge';
import { PageContainer } from '@/components/page-container';
import { SectionHeader } from '@/components/section-header';
import { StatCard } from '@/components/stat-card';
import { WorkflowSteps, bankImportSteps } from '@/components/workflow-steps';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, CalendarDays, FileText, Settings, Upload, Wallet } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'ホーム',
        href: route('home'),
    },
];

const menuItems = [
    {
        title: '銀行CSV取込',
        description: '法人口座の入出金を取り込み、自動で仕訳します',
        href: route('bank-import'),
        icon: Upload,
    },
    {
        title: '立替経費入力',
        description: '社長個人が支払った経費を登録します',
        href: route('advance-expenses'),
        icon: Wallet,
    },
    {
        title: '決算書出力',
        description: '損益計算書・貸借対照表などを出力します',
        href: route('reports'),
        icon: FileText,
    },
    {
        title: 'その他',
        description: '振替伝票・会計期間設定など',
        href: route('other'),
        icon: Settings,
    },
];

export default function Home() {
    const { auth } = usePage<SharedData>().props;
    const companyName = auth.company?.name ?? '会社名未設定';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ホーム" />
            <PageContainer size="full">
                <section className="surface-elevated relative overflow-hidden p-6 md:p-8">
                    <div className="from-primary/8 via-primary/3 pointer-events-none absolute inset-0 bg-gradient-to-br to-transparent" />
                    <div className="bg-primary/10 pointer-events-none absolute -top-16 right-0 size-56 rounded-full blur-3xl" />
                    <div className="relative space-y-2">
                        <p className="text-primary text-xs font-semibold tracking-widest uppercase">Dashboard</p>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                            ようこそ、<span className="text-gradient-primary">{auth.user?.name}</span>さん
                        </h1>
                        <p className="text-muted-foreground max-w-xl text-sm leading-relaxed">
                            日常の会計業務をここから始められます。やりたい操作を選んでください。
                        </p>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="会社" icon={Building2}>
                        <p className="truncate text-base font-semibold">{companyName}</p>
                    </StatCard>
                    <StatCard label="会計期間" icon={CalendarDays}>
                        <FiscalYearBadge fiscalYear={auth.activeFiscalYear} />
                    </StatCard>
                </div>

                <WorkflowSteps steps={bankImportSteps} currentStep="upload" />

                <section className="space-y-4">
                    <SectionHeader
                        title="メニュー"
                        description="よく使う機能へのショートカット"
                        actions={
                            <Link
                                href={route('bank-import')}
                                className="text-primary text-sm font-medium hover:underline"
                            >
                                銀行CSV取込を始める →
                            </Link>
                        }
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        {menuItems.map((item) => (
                            <FeatureCard
                                key={item.title}
                                title={item.title}
                                description={item.description}
                                href={item.href}
                                icon={item.icon}
                            />
                        ))}
                    </div>
                </section>
            </PageContainer>
        </AppLayout>
    );
}
