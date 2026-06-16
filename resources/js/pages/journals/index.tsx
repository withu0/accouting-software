import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';

interface JournalEntry {
    id: number;
    entry_date: string;
    description: string;
    source: string;
    total_amount: number;
}

interface PaginatedEntries {
    data: JournalEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    entries: PaginatedEntries;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '仕訳一覧', href: route('journals.index') },
];

const sourceLabels: Record<string, string> = {
    bank_csv: '銀行CSV',
    advance_expense: '立替経費',
    transfer: '振替伝票',
    manual: '手動',
};

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

export default function JournalsIndex({ entries }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="仕訳一覧" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">仕訳一覧</h1>
                    <p className="text-muted-foreground mt-1 text-sm">登録済みの仕訳を確認できます</p>
                </div>

                {entries.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                        <div className="bg-muted flex size-16 items-center justify-center rounded-full">
                            <FileText className="text-muted-foreground size-8" />
                        </div>
                        <p className="text-muted-foreground text-sm">仕訳がまだありません</p>
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="px-4 py-3 text-left font-medium">日付</th>
                                        <th className="px-4 py-3 text-left font-medium">摘要</th>
                                        <th className="px-4 py-3 text-left font-medium">ソース</th>
                                        <th className="px-4 py-3 text-right font-medium">金額</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entries.data.map((entry) => (
                                        <tr key={entry.id} className="border-b last:border-b-0">
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(entry.entry_date)}</td>
                                            <td className="px-4 py-3">{entry.description}</td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                                {sourceLabels[entry.source] ?? entry.source}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                {formatAmount(entry.total_amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {entries.last_page > 1 && (
                            <div className="flex items-center justify-center gap-2">
                                {entries.links.map((link, index) => {
                                    if (link.url === null) {
                                        return (
                                            <span
                                                key={index}
                                                className="text-muted-foreground px-3 py-1 text-sm"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }

                                    return (
                                        <Button key={index} asChild variant={link.active ? 'default' : 'outline'} size="sm">
                                            <Link
                                                href={link.url}
                                                preserveScroll
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        </Button>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
