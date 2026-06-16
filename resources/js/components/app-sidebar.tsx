import { NavMain } from '@/components/nav-main';
import { NavOther } from '@/components/nav-other';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { FileText, LayoutGrid, Upload, Wallet } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'ホーム',
        url: route('home'),
        icon: LayoutGrid,
    },
    {
        title: '銀行CSV取込',
        url: route('bank-import'),
        icon: Upload,
    },
    {
        title: '立替経費入力',
        url: route('advance-expenses'),
        icon: Wallet,
    },
    {
        title: '決算書出力',
        url: route('reports'),
        icon: FileText,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const companyName = auth.company?.name ?? '会社名未設定';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('home')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem className="group-data-[collapsible=icon]:hidden">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="text-muted-foreground truncate px-2 text-xs font-medium">{companyName}</div>
                            </TooltipTrigger>
                            <TooltipContent side="right">{companyName}</TooltipContent>
                        </Tooltip>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavOther />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
