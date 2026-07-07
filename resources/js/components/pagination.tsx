import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationProps {
    links: PaginationLink[];
    onPageChange?: () => void;
}

export function Pagination({ links, onPageChange }: PaginationProps) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex items-center justify-center gap-1.5">
            {links.map((link, index) => {
                if (link.url === null) {
                    return (
                        <span
                            key={index}
                            className="text-muted-foreground px-3 py-1.5 text-sm"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                }

                return (
                    <Button key={index} asChild variant={link.active ? 'default' : 'outline'} size="sm">
                        <Link
                            href={link.url}
                            preserveScroll
                            onClick={onPageChange}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    </Button>
                );
            })}
        </div>
    );
}
