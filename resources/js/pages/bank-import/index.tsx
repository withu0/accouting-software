import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Upload } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: '銀行CSV取込', href: route('bank-import') },
];

export default function BankImportIndex({ hasActiveFiscalYear }: Props) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('bank-import.store'), {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="銀行CSV取込" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">銀行CSV取込</h1>
                        <p className="text-muted-foreground mt-1 text-sm">法人口座の入出金CSVを取り込み、仕訳候補を作成します</p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={route('bank-import.history')}>取込履歴</Link>
                    </Button>
                </div>

                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

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

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Upload className="size-5" />
                            CSVファイルをアップロード
                        </CardTitle>
                        <CardDescription>
                            日付・摘要・入金額・出金額・残高の形式のCSVファイルを選択してください
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="file">CSVファイル</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".csv,.txt"
                                    disabled={!hasActiveFiscalYear || processing}
                                    onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                                />
                                <InputError message={errors.file} />
                            </div>
                            <Button type="submit" disabled={!hasActiveFiscalYear || processing || !data.file}>
                                {processing ? '取込中...' : 'アップロードして確認'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
