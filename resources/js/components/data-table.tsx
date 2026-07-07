import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface DataTableProps {
    children: ReactNode;
    className?: string;
}

export function DataTable({ children, className }: DataTableProps) {
    return (
        <div className={cn('overflow-hidden rounded-xl border border-border/50 bg-card shadow-sm', className)}>
            <div className="overflow-x-auto">
                <table className="w-full caption-bottom text-sm">{children}</table>
            </div>
        </div>
    );
}

export function DataTableHeader({ children, className }: DataTableProps) {
    return (
        <thead className={cn('border-b border-border/50 bg-muted/40 [&_tr]:border-0', className)}>{children}</thead>
    );
}
