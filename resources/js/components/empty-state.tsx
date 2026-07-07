import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface EmptyStateAction {
    label: string;
    href?: string;
    onClick?: () => void;
    variant?: 'default' | 'outline';
}

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    actions?: EmptyStateAction[];
    children?: ReactNode;
    className?: string;
}

export function EmptyState({ icon: Icon, title, description, actions, children, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-border/60 bg-muted/20 px-6 py-14 text-center',
                className,
            )}
        >
            <div className="from-primary/15 to-primary/5 text-primary flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br ring-1 ring-primary/10">
                <Icon className="size-7" />
            </div>
            <div className="space-y-1.5">
                <p className="font-semibold">{title}</p>
                {description && <p className="text-muted-foreground max-w-sm text-sm leading-relaxed">{description}</p>}
            </div>
            {children}
            {actions && actions.length > 0 && (
                <div className="flex flex-wrap items-center justify-center gap-2">
                    {actions.map((action) =>
                        action.href ? (
                            <Button key={action.label} asChild variant={action.variant ?? 'default'}>
                                <Link href={action.href}>{action.label}</Link>
                            </Button>
                        ) : (
                            <Button key={action.label} variant={action.variant ?? 'default'} onClick={action.onClick}>
                                {action.label}
                            </Button>
                        ),
                    )}
                </div>
            )}
        </div>
    );
}
