import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface FilterToolbarProps {
    children: ReactNode;
    className?: string;
    sticky?: boolean;
}

export function FilterToolbar({ children, className, sticky = true }: FilterToolbarProps) {
    return (
        <div
            className={cn(
                'surface-card flex flex-wrap items-center gap-2 px-4 py-3',
                sticky && 'sticky top-14 z-10',
                className,
            )}
        >
            {children}
        </div>
    );
}
