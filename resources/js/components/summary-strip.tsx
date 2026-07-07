import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

export interface SummaryItem {
    label: string;
    value: ReactNode;
    highlight?: boolean;
    variant?: 'default' | 'warning' | 'success';
}

interface SummaryStripProps {
    items: SummaryItem[];
    className?: string;
}

const variantClasses = {
    default: 'text-foreground',
    warning: 'text-amber-700 dark:text-amber-400',
    success: 'text-green-700 dark:text-green-400',
};

export function SummaryStrip({ items, className }: SummaryStripProps) {
    return (
        <div className={cn('surface-card flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4 text-sm', className)}>
            {items.map((item) => (
                <div key={item.label} className="flex items-center gap-2">
                    <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{item.label}</span>
                    <span
                        className={cn(
                            'font-semibold tabular-nums',
                            item.highlight && 'text-primary',
                            item.variant && variantClasses[item.variant],
                        )}
                    >
                        {item.value}
                    </span>
                </div>
            ))}
        </div>
    );
}
