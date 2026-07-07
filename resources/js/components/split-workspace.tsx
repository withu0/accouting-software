import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface SplitWorkspaceProps {
    left: ReactNode;
    right: ReactNode;
    className?: string;
    stickyLeft?: boolean;
}

export function SplitWorkspace({ left, right, className, stickyLeft = true }: SplitWorkspaceProps) {
    return (
        <div className={cn('grid gap-8 lg:grid-cols-[minmax(360px,420px)_1fr] lg:items-start', className)}>
            <div className={cn(stickyLeft && 'lg:sticky lg:top-20 lg:self-start')}>{left}</div>
            <div className="min-w-0 space-y-4">{right}</div>
        </div>
    );
}
