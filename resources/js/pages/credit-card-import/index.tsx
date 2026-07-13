import { FileDropZone } from '@/components/file-drop-zone';
import InputError from '@/components/input-error';
import { FormSection } from '@/components/form-section';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SummaryStrip } from '@/components/summary-strip';
import { WorkflowSteps, creditCardImportSteps } from '@/components/workflow-steps';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    hasActiveFiscalYear: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ホーム', href: route('home') },
    { title: 'クレジットカードCSV取込', href: route('credit-card-import') },
];

export default function CreditCardImportIndex({ hasActiveFiscalYear }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('credit-card-import.store'), {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="クレジットカードCSV取込" />
            <PageContainer size="narrow">
                <PageHeader
                    title="クレジットカードCSV取込"
                    description="クレジットカード明細CSVを取り込み、経費仕訳（未払金計上）を作成します"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('credit-card-import.history')}>取込履歴</Link>
                        </Button>
                    }
                />

                <WorkflowSteps steps={creditCardImportSteps} currentStep="upload" />

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

                <FormSection
                    title="CSVファイルをアップロード"
                    description="各種クレジットカード明細CSV（Shift_JIS / UTF-8）に対応しています"
                >
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="file">CSVファイル</Label>
                            <FileDropZone
                                file={data.file}
                                disabled={!hasActiveFiscalYear || processing}
                                onFileChange={(file) => setData('file', file)}
                            />
                            <InputError message={errors.file} />
                        </div>
                        <Button type="submit" disabled={!hasActiveFiscalYear || processing || !data.file}>
                            {processing ? '取込中...' : 'アップロードして確認'}
                        </Button>
                    </form>
                </FormSection>

                <SummaryStrip
                    items={[
                        { label: '形式', value: '自動判別' },
                        { label: '記帳', value: '経費 / 未払金' },
                    ]}
                />
            </PageContainer>
        </AppLayout>
    );
}
