import { LucideIcon } from 'lucide-react';

export interface Company {
    id: number;
    name: string | null;
    representative_name: string | null;
    address: string | null;
}

export interface FiscalYear {
    id: number;
    start_date: string;
    end_date: string;
}

export interface Auth {
    user: User | null;
    company?: Company | null;
    activeFiscalYear?: FiscalYear | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
