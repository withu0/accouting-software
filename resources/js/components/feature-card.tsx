import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, type LucideIcon } from 'lucide-react';

interface FeatureCardProps {
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
}

export function FeatureCard({ title, description, href, icon: Icon }: FeatureCardProps) {
    return (
        <Link href={href} prefetch className="group block h-full">
            <Card className="relative h-full overflow-hidden border-border/50 transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5">
                <div className="from-primary/6 pointer-events-none absolute inset-0 bg-gradient-to-br via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                <CardHeader className="relative">
                    <div className="mb-4 flex items-start justify-between">
                        <div className="from-primary/20 to-primary/5 text-primary flex size-12 items-center justify-center rounded-xl bg-gradient-to-br ring-1 ring-primary/10 transition-transform duration-300 group-hover:scale-105">
                            <Icon className="size-5" />
                        </div>
                        <ArrowUpRight className="text-muted-foreground size-4 opacity-0 transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary group-hover:opacity-100" />
                    </div>
                    <CardTitle className="text-base font-semibold">{title}</CardTitle>
                    <CardDescription className="leading-relaxed">{description}</CardDescription>
                </CardHeader>
            </Card>
        </Link>
    );
}
