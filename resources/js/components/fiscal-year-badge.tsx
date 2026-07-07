import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/dates';
import { type FiscalYear } from '@/types';
import { AlertCircle } from 'lucide-react';

interface FiscalYearBadgeProps {
    fiscalYear?: FiscalYear | null;
    className?: string;
}

export function FiscalYearBadge({ fiscalYear, className }: FiscalYearBadgeProps) {
    if (fiscalYear) {
        return (
            <Badge
                variant="secondary"
                className={`border-primary/10 bg-primary/5 text-primary font-medium ${className ?? ''}`}
            >
                {formatDate(fiscalYear.start_date)} 〜 {formatDate(fiscalYear.end_date)}
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className={`gap-1.5 border-amber-400/40 bg-amber-50/80 font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 ${className ?? ''}`}
        >
            <AlertCircle className="size-3.5" />
            会計期間が未設定です
        </Badge>
    );
}
