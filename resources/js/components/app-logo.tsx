import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <div className="from-primary to-primary/80 text-primary-foreground flex aspect-square size-9 items-center justify-center rounded-xl bg-gradient-to-br shadow-md shadow-primary/25 ring-1 ring-white/20">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-2 grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate text-sm font-bold tracking-tight">{name}</span>
                <span className="text-muted-foreground truncate text-[11px] font-medium tracking-wide">会計ソフト</span>
            </div>
        </>
    );
}
