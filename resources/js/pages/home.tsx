import { Badge } from '@/components/ui/badge';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, FileText, Settings, Upload, Wallet } from 'lucide-react';

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
    const activeFiscalYear = auth.activeFiscalYear;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ホーム" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">ようこそ、{auth.user?.name}さん</h1>
                        <p className="text-muted-foreground mt-1 text-sm">やりたい操作を選んでください</p>
                    </div>
                    {activeFiscalYear ? (
                        <Badge variant="secondary" className="w-fit shrink-0">
                            会計期間: {formatDate(activeFiscalYear.start_date)} 〜 {formatDate(activeFiscalYear.end_date)}
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="w-fit shrink-0 gap-1 border-amber-500/50 text-amber-700 dark:text-amber-400">
                            <AlertCircle className="size-3.5" />
                            会計期間が未設定です
                        </Badge>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {menuItems.map((item) => (
                        <Link key={item.title} href={item.href} prefetch className="group block">
                            <Card className="h-full transition-colors hover:border-primary/50 hover:bg-accent/30">
                                <CardHeader>
                                    <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary/15">
                                        <item.icon className="size-6" />
                                    </div>
                                    <CardTitle className="text-lg">{item.title}</CardTitle>
                                    <CardDescription>{item.description}</CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
