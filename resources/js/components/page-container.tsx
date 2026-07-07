import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface PageContainerProps {
    children: ReactNode;
    className?: string;
    size?: 'default' | 'narrow' | 'full';
}

const sizeClasses = {
    default: 'max-w-6xl',
    narrow: 'max-w-3xl',
    full: 'max-w-none',
};

export function PageContainer({ children, className, size = 'default' }: PageContainerProps) {
    return (
        <div className={cn('mx-auto flex w-full flex-1 flex-col gap-8 px-4 py-6 md:px-8 md:py-8', sizeClasses[size], className)}>
            {children}
        </div>
    );
}
