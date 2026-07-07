import { type ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
}

export function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-5 border-b border-border/40 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1.5">
                <h1 className="text-2xl font-bold tracking-tight md:text-[1.75rem]">{title}</h1>
                {description && <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
