import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface StatCardProps {
    label: string;
    icon?: LucideIcon;
    children: ReactNode;
    className?: string;
}

export function StatCard({ label, icon: Icon, children, className }: StatCardProps) {
    return (
        <Card className={cn('border-border/50 bg-card/90 shadow-sm backdrop-blur-sm transition-shadow hover:shadow-md', className)}>
            <div className="flex items-start gap-4 p-5">
                {Icon && (
                    <div className="from-primary/15 to-primary/5 text-primary flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ring-1 ring-primary/10">
                        <Icon className="size-5" />
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground mb-1.5 text-xs font-medium tracking-wide uppercase">{label}</p>
                    {children}
                </div>
            </div>
        </Card>
    );
}
