import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';

interface PlaceholderProps {
    title: string;
}

function getBreadcrumbs(title: string): BreadcrumbItem[] {
    const href = window.location.pathname;

    return [
        { title: 'ホーム', href: route('home') },
        { title, href },
    ];
}

export default function Placeholder({ title }: PlaceholderProps) {
    const breadcrumbs = getBreadcrumbs(title);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-4 py-16 text-center md:p-6">
                <div className="bg-muted flex size-16 items-center justify-center rounded-full">
                    <Construction className="text-muted-foreground size-8" />
                </div>
                <h1 className="text-xl font-semibold">{title}</h1>
                <p className="text-muted-foreground text-sm">この機能は準備中です。</p>
            </div>
        </AppLayout>
    );
}
