import { NavMain } from '@/components/nav-main';
import { NavOther } from '@/components/nav-other';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { FileText, CreditCard, LayoutGrid, Upload, Wallet } from 'lucide-react';
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
        title: 'クレジットカードCSV取込',
        url: route('credit-card-import'),
        icon: CreditCard,
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
    return (
        <Sidebar collapsible="icon" className="border-r border-border/40 bg-sidebar shadow-[4px_0_24px_-16px_rgba(15,40,80,0.12)]">
            <SidebarHeader className="border-b border-border/40 px-3 py-4 group-data-[collapsible=icon]:px-0 group-data-[collapsible=icon]:py-3">
                <SidebarMenu className="group-data-[collapsible=icon]:items-center">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent group-data-[collapsible=icon]:!size-9 group-data-[collapsible=icon]:!justify-center group-data-[collapsible=icon]:!p-0"
                        >
                            <Link href={route('home')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-1 px-2 py-4 group-data-[collapsible=icon]:px-0">
                <NavMain items={mainNavItems} />
                <NavOther />
            </SidebarContent>

            <SidebarFooter className="border-t border-border/40 p-3 group-data-[collapsible=icon]:px-0 group-data-[collapsible=icon]:py-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
