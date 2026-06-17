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
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/dates';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileText, Trash2 } from 'lucide-react';

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
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="仕訳一覧" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">仕訳一覧</h1>
                    <p className="text-muted-foreground mt-1 text-sm">登録済みの仕訳を確認できます</p>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

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
                                        <th className="px-4 py-3 text-right font-medium">操作</th>
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
                                            <td className="px-4 py-3 text-right">
                                                {entry.source === 'bank_csv' ? (
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogTitle>仕訳を削除しますか？</DialogTitle>
                                                            <DialogDescription>
                                                                {formatDate(entry.entry_date)} — {entry.description}（
                                                                {formatAmount(entry.total_amount)}）を削除します。同じCSV行を再度取込できるようになります。
                                                            </DialogDescription>
                                                            <DialogFooter>
                                                                <DialogClose asChild>
                                                                    <Button variant="outline">キャンセル</Button>
                                                                </DialogClose>
                                                                <Button
                                                                    variant="destructive"
                                                                    onClick={() => {
                                                                        router.delete(route('journals.destroy', entry.id), {
                                                                            preserveScroll: true,
                                                                        });
                                                                    }}
                                                                >
                                                                    削除する
                                                                </Button>
                                                            </DialogFooter>
                                                        </DialogContent>
                                                    </Dialog>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
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
