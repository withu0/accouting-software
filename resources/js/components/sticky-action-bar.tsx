import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface StickyActionBarProps {
    children: ReactNode;
    className?: string;
    variant?: 'default' | 'warning';
}

export function StickyActionBar({ children, className, variant = 'default' }: StickyActionBarProps) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-20 -mx-4 border-t px-4 py-4 backdrop-blur-xl md:-mx-8 md:px-8',
                variant === 'default' && 'border-border/60 bg-background/90',
                variant === 'warning' && 'border-amber-400/40 bg-amber-50/90 dark:bg-amber-950/80',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-4">{children}</div>
        </div>
    );
}
