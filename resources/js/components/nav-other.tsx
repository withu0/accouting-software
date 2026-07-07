import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { getPathname, isNavItemActive, navItemActiveClassName, navSubItemActiveClassName } from '@/lib/nav';
import { Link, usePage } from '@inertiajs/react';
import { Calendar, ChevronRight, FileSpreadsheet, List, Receipt, Settings } from 'lucide-react';

// Post-MVP placeholders — re-enable when implemented
// const disabledItems = [
//     { title: '勘定科目設定', icon: FileSpreadsheet },
//     { title: '固定資産登録', icon: Landmark },
// ];

const enabledItems = [
    { title: '臨時仕訳（振替伝票）', url: route('transfer-journal.index'), icon: Receipt },
    { title: '勘定科目設定', url: route('accounts.edit'), icon: FileSpreadsheet },
    { title: '会計期間設定', url: route('fiscal-year.edit'), icon: Calendar },
    { title: '仕訳一覧', url: route('journals.index'), icon: List },
];

export function NavOther() {
    const page = usePage();
    const otherSectionActive =
        getPathname(page.url) === getPathname(route('other')) ||
        enabledItems.some((item) => isNavItemActive(page.url, item.url));

    return (
        <SidebarGroup className="mt-6 px-1 py-0 group-data-[collapsible=icon]:mt-2 group-data-[collapsible=icon]:px-0">
            <SidebarGroupLabel className="text-muted-foreground/80 mb-2 px-3 text-[10px] font-semibold tracking-[0.12em] uppercase">
                その他
            </SidebarGroupLabel>
            <SidebarMenu className="group-data-[collapsible=icon]:items-center">
                <Collapsible asChild defaultOpen className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton
                                tooltip="その他"
                                isActive={otherSectionActive}
                                className={navItemActiveClassName}
                            >
                                <Settings />
                                <span className="group-data-[collapsible=icon]:hidden">その他</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[collapsible=icon]:hidden group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                {enabledItems.map((item) => {
                                    const isActive = isNavItemActive(page.url, item.url);

                                    return (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton
                                                asChild
                                                isActive={isActive}
                                                className={navSubItemActiveClassName}
                                            >
                                                <Link href={item.url} prefetch aria-current={isActive ? 'page' : undefined}>
                                                    <item.icon />
                                                    <span className="group-data-[collapsible=icon]:hidden">{item.title}</span>
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarMenu>
        </SidebarGroup>
    );
}
