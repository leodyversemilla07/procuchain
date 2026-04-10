import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/react';

interface ErrorProps {
    status: number;
}

export default function Error({ status }: ErrorProps) {
    const title =
        {
            503: '503: Service Unavailable',
            500: '500: Server Error',
            404: '404: Page Not Found',
            403: '403: Forbidden',
            401: '401: Unauthorized',
            419: '419: Page Expired',
            429: '429: Too Many Requests',
        }[status] || 'Error';

    const description =
        {
            503: 'Sorry, we are doing some maintenance. Please check back soon.',
            500: 'Whoops, something went wrong on our servers.',
            404: 'Sorry, the page you are looking for could not be found.',
            403: 'Sorry, you are forbidden from accessing this page.',
            401: 'Sorry, you are not authorized to access this page.',
            419: 'Sorry, your session has expired. Please refresh and try again.',
            429: 'Too many requests. Please wait a moment and try again.',
        }[status] || 'An unexpected error has occurred.';

    return (
        <>
            <Head title={title} />
            <div className="bg-background flex min-h-screen flex-col items-center justify-center p-4 text-center">
                <div className="mx-auto max-w-md">
                    <div className="border-primary/20 bg-primary/10 text-primary mb-6 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium">
                        Status {status}
                    </div>

                    <h1 className="mb-4 text-4xl font-bold tracking-tight sm:text-5xl">{title}</h1>

                    <p className="text-muted-foreground mb-8 text-lg">{description}</p>

                    <div className="flex flex-col gap-4 sm:flex-row sm:justify-center">
                        <Button size="lg" render={<Link href={home.url()} />}>
                            Go back home
                        </Button>
                        <Button size="lg" variant="outline" onClick={() => window.location.reload()}>
                            Try again
                        </Button>
                    </div>
                </div>

                {/* Aesthetic background elements */}
                <div className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
                    <div className="bg-primary/5 absolute -top-[10%] -left-[10%] h-[40%] w-[40%] rounded-full blur-3xl"></div>
                    <div className="bg-primary/5 absolute -right-[10%] -bottom-[10%] h-[40%] w-[40%] rounded-full blur-3xl"></div>
                </div>
            </div>
        </>
    );
}
