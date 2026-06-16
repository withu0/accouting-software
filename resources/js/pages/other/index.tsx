import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Receipt } from 'lucide-react';
// import { FileSpreadsheet, Landmark } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'その他', href: route('other') },
];

const menuItems = [
    {
        title: '臨時仕訳（振替伝票）',
        description: '例外処理の仕訳を入力します',
        icon: Receipt,
        href: route('transfer-journal.index'),
        disabled: false,
    },
    // Post-MVP — re-enable when implemented
    // {
    //     title: '固定資産登録',
    //     description: '固定資産台帳の管理（今後対応予定）',
    //     icon: Landmark,
    //     href: null,
    //     disabled: true,
    // },
    // {
    //     title: '勘定科目設定',
    //     description: '利用可能な勘定科目の確認（今後対応予定）',
    //     icon: FileSpreadsheet,
    //     href: null,
    //     disabled: true,
    // },
    {
        title: '会計期間設定',
        description: '会計年度の開始日・終了日を設定します',
        icon: Calendar,
        href: route('fiscal-year.edit'),
        disabled: false,
    },
];

export default function OtherIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="その他" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">その他</h1>
                    <p className="text-muted-foreground mt-1 text-sm">補助的な設定・例外処理</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {menuItems.map((item) => {
                        const content = (
                            <Card
                                className={`h-full ${item.disabled ? 'opacity-60' : 'transition-colors hover:border-primary/50 hover:bg-accent/30'}`}
                            >
                                <CardHeader>
                                    <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <item.icon className="size-5" />
                                    </div>
                                    <CardTitle className="text-lg">{item.title}</CardTitle>
                                    <CardDescription>
                                        {item.description}
                                        {item.disabled && <span className="mt-1 block text-xs">準備中</span>}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        );

                        if (item.disabled || !item.href) {
                            return <div key={item.title}>{content}</div>;
                        }

                        return (
                            <Link key={item.title} href={item.href} prefetch className="block">
                                {content}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
