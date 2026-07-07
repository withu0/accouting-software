export function getPathname(url: string): string {
    const withoutQuery = url.split('?')[0].split('#')[0];

    try {
        if (withoutQuery.startsWith('http')) {
            const path = new URL(withoutQuery).pathname;
            return path.replace(/\/$/, '') || '/';
        }
    } catch {
        // Fall through to relative path handling.
    }

    const path = withoutQuery.replace(/\/$/, '') || '/';
    return path.startsWith('/') ? path : `/${path}`;
}

export function isNavItemActive(currentUrl: string, itemUrl: string): boolean {
    const current = getPathname(currentUrl);
    const item = getPathname(itemUrl);

    if (item === '/') {
        return current === '/';
    }

    return current === item || current.startsWith(`${item}/`);
}

export const navItemActiveClassName =
    'h-8 rounded-md text-[13px] font-medium text-muted-foreground transition-all duration-200 hover:bg-primary/5 hover:text-foreground group-data-[collapsible=icon]:!size-8 group-data-[collapsible=icon]:!justify-center group-data-[collapsible=icon]:!gap-0 group-data-[collapsible=icon]:!p-2 data-[active=true]:!bg-gradient-to-r data-[active=true]:from-primary/14 data-[active=true]:to-primary/5 data-[active=true]:!text-primary data-[active=true]:!font-semibold data-[active=true]:ring-1 data-[active=true]:ring-inset data-[active=true]:ring-primary/10 data-[active=true]:hover:from-primary/18 data-[active=true]:hover:to-primary/8 [&_svg]:data-[active=true]:!text-primary';

export const navSubItemActiveClassName =
    'rounded-sm text-[13px] font-medium text-muted-foreground transition-all duration-200 hover:bg-primary/5 hover:text-foreground group-data-[collapsible=icon]:!size-8 group-data-[collapsible=icon]:!justify-center group-data-[collapsible=icon]:!gap-0 group-data-[collapsible=icon]:!p-2 data-[active=true]:!bg-primary/8 data-[active=true]:!text-primary data-[active=true]:!font-semibold data-[active=true]:ring-1 data-[active=true]:ring-inset data-[active=true]:ring-primary/8 data-[active=true]:hover:!bg-primary/12 [&_svg]:data-[active=true]:!text-primary';
