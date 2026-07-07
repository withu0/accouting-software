import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { isNavItemActive, navItemActiveClassName } from '@/lib/nav';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();

    return (
        <SidebarGroup className="px-1 py-0 group-data-[collapsible=icon]:px-0">
            <SidebarGroupLabel className="text-muted-foreground/80 mb-2 px-3 text-[10px] font-semibold tracking-[0.12em] uppercase">
                メニュー
            </SidebarGroupLabel>
            <SidebarMenu className="group-data-[collapsible=icon]:items-center">
                {items.map((item) => {
                    const isActive = isNavItemActive(page.url, item.url);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                tooltip={item.title}
                                className={navItemActiveClassName}
                            >
                                <Link href={item.url} prefetch aria-current={isActive ? 'page' : undefined}>
                                    {item.icon && <item.icon />}
                                    <span className="group-data-[collapsible=icon]:hidden">{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
