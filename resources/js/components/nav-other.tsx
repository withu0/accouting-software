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

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>その他</SidebarGroupLabel>
            <SidebarMenu>
                <Collapsible asChild defaultOpen className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="その他">
                                <Settings />
                                <span>その他</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                {/* {disabledItems.map((item) => (
                                    <SidebarMenuSubItem key={item.title}>
                                        <SidebarMenuSubButton
                                            aria-disabled
                                            className="pointer-events-none opacity-50"
                                        >
                                            <item.icon />
                                            <span>{item.title}</span>
                                            <span className="ml-auto text-xs text-muted-foreground">準備中</span>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))} */}
                                {enabledItems.map((item) => (
                                    <SidebarMenuSubItem key={item.title}>
                                        <SidebarMenuSubButton asChild isActive={item.url === page.url}>
                                            <Link href={item.url} prefetch>
                                                <item.icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarMenu>
        </SidebarGroup>
    );
}
