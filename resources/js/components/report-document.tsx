import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface ReportDocumentProps {
    companyName: string;
    periodLabel: string;
    title: string;
    description?: string;
    toolbar?: ReactNode;
    children: ReactNode;
    className?: string;
}

export function ReportDocument({
    companyName,
    periodLabel,
    title,
    description,
    toolbar,
    children,
    className,
}: ReportDocumentProps) {
    const generatedAt = new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date());

    return (
        <div className={cn('surface-elevated overflow-hidden', className)}>
            <div className="border-b border-border/50 bg-muted/30 px-6 py-5 md:px-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-xs font-medium tracking-widest uppercase">{companyName}</p>
                        <h2 className="text-xl font-bold tracking-tight">{title}</h2>
                        {description && <p className="text-muted-foreground text-sm">{description}</p>}
                        <p className="text-muted-foreground text-xs">対象期間: {periodLabel}</p>
                        <p className="text-muted-foreground text-xs">出力日時: {generatedAt}</p>
                    </div>
                    {toolbar && <div className="flex shrink-0 flex-wrap items-center gap-2">{toolbar}</div>}
                </div>
            </div>
            <div className="p-6 md:p-8">{children}</div>
        </div>
    );
}

interface ReportSectionProps {
    title: string;
    children: ReactNode;
    className?: string;
}

export function ReportSection({ title, children, className }: ReportSectionProps) {
    return (
        <section className={cn('space-y-3', className)}>
            <h3 className="border-b border-border/50 pb-2 text-sm font-bold tracking-wide">{title}</h3>
            {children}
        </section>
    );
}
