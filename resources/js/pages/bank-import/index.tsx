import { FileDropZone } from '@/components/file-drop-zone';
import { FlashAlert } from '@/components/flash-alert';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { SummaryStrip } from '@/components/summary-strip';
import { WorkflowSteps, bankImportSteps } from '@/components/workflow-steps';
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
    { title: '銀行CSV取込', href: route('bank-import') },
];

export default function BankImportIndex({ hasActiveFiscalYear }: Props) {
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
            <PageContainer size="narrow">
                <PageHeader
                    title="銀行CSV取込"
                    description="法人口座の入出金CSVを取り込み、仕訳候補を作成します"
                    actions={
                        <Button asChild variant="outline">
                            <Link href={route('bank-import.history')}>取込履歴</Link>
                        </Button>
                    }
                />

                <WorkflowSteps steps={bankImportSteps} currentStep="upload" />

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
                    description="GMOあおぞら・楽天銀行・住信SBIネット銀行など主要銀行のCSVを自動判別して取り込みます"
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
                        { label: '対応銀行', value: 'GMOあおぞら / 楽天 / 住信SBI 他' },
                        { label: '形式', value: '自動判別' },
                    ]}
                />
            </PageContainer>
        </AppLayout>
    );
}
