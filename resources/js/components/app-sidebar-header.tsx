import { Breadcrumbs } from '@/components/breadcrumbs';
import { FiscalYearBadge } from '@/components/fiscal-year-badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;
    const companyName = auth.company?.name ?? '会社名未設定';

    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-border/40 bg-background/80 px-4 backdrop-blur-xl md:px-6">
            <SidebarTrigger className="-ml-1 text-muted-foreground hover:text-foreground" />
            <div className="hidden h-5 w-px bg-border/60 sm:block" />
            <div className="flex min-w-0 flex-1 items-center">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="hidden items-center gap-2.5 md:flex">
                <FiscalYearBadge fiscalYear={auth.activeFiscalYear} className="shrink-0" />
                <div className="bg-muted/60 text-muted-foreground flex max-w-52 items-center gap-2 rounded-full border border-border/50 px-3 py-1.5 text-xs">
                    <Building2 className="size-3.5 shrink-0" />
                    <span className="truncate font-medium">{companyName}</span>
                </div>
            </div>
        </header>
    );
}
