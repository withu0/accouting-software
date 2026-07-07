import { type ReactNode } from 'react';

interface SectionHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
}

export function SectionHeader({ title, description, actions }: SectionHeaderProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1">
                <h2 className="text-base font-semibold tracking-tight">{title}</h2>
                {description && <p className="text-muted-foreground text-sm leading-relaxed">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
