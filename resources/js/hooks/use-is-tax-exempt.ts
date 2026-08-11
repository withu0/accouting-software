import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export function useIsTaxExempt(): boolean {
    const { auth } = usePage<SharedData>().props;

    return auth.company?.tax_entity_status === 'exempt';
}
