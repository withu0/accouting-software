import { FeatureCard } from '@/components/feature-card';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SectionHeader } from '@/components/section-header';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Calendar, FileSpreadsheet, Receipt } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'その他', href: route('other') },
];

const journalItems = [
    {
        title: '臨時仕訳（振替伝票）',
        description: '例外処理の仕訳を入力します',
        icon: Receipt,
        href: route('transfer-journal.index'),
    },
];

const settingsItems = [
    {
        title: '勘定科目設定',
        description: '仕訳で使用する勘定科目の追加・編集・削除',
        icon: FileSpreadsheet,
        href: route('accounts.edit'),
    },
    {
        title: '会計期間設定',
        description: '会計年度の開始日・終了日を設定します',
        icon: Calendar,
        href: route('fiscal-year.edit'),
    },
];

export default function OtherIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="その他" />
            <PageContainer size="full">
                <PageHeader title="その他" description="補助的な設定・例外処理" />

                <section className="space-y-4">
                    <SectionHeader title="仕訳操作" description="通常の取込フローで対応できない仕訳を入力します" />
                    <div className="grid gap-4 sm:grid-cols-2">
                        {journalItems.map((item) => (
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

                <section className="space-y-4">
                    <SectionHeader title="会社設定" description="勘定科目や会計期間などのマスタ設定" />
                    <div className="grid gap-4 sm:grid-cols-2">
                        {settingsItems.map((item) => (
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
