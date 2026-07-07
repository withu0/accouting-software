import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface FormSectionProps {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
}

export function FormSection({ title, description, children, className }: FormSectionProps) {
    return (
        <Card className={cn('border-border/50 shadow-md', className)}>
            <CardHeader className="border-b border-border/40 bg-muted/20">
                <CardTitle className="text-base font-semibold">{title}</CardTitle>
                {description && <CardDescription className="leading-relaxed">{description}</CardDescription>}
            </CardHeader>
            <CardContent className="pt-6">{children}</CardContent>
        </Card>
    );
}
