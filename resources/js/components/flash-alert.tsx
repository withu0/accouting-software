import { Alert, AlertDescription } from '@/components/ui/alert';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export function FlashAlert() {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    if (!flash?.success) {
        return null;
    }

    return (
        <Alert className="border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            <CheckCircle2 className="size-4" />
            <AlertDescription>{flash.success}</AlertDescription>
        </Alert>
    );
}
