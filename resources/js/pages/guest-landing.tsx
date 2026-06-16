import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function GuestLanding() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <Head title={name} />
            <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-8 p-6">
                <div className="flex flex-col items-center gap-4 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon className="size-7 fill-current" />
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">{name}</h1>
                    <p className="text-muted-foreground max-w-md text-sm">
                        銀行CSVの取込から決算書の出力まで、シンプルに記帳できる会計ソフトです。
                    </p>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <Button asChild size="lg">
                        <Link href={route('login')}>ログイン</Link>
                    </Button>
                    <Button asChild variant="outline" size="lg">
                        <Link href={route('register')}>新規登録</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
