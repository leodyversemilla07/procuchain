import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { FileX, Search, ArrowLeft } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';

interface NotFoundProps {
    message: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Procurements',
        href: '/procurements',
    },
    {
        title: 'Not Found',
        href: '#',
    }
];

export default function NotFound({ message = 'Procurement not found' }: NotFoundProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement Not Found" />
            
            <div className="flex h-full flex-1 flex-col items-center justify-center p-6">
                <Card className="w-full max-w-md border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                    <CardHeader className="text-center pb-2">
                        <div className="mx-auto mb-4 rounded-full bg-red-100/80 p-3 dark:bg-red-900/30">
                            <FileX className="h-10 w-10 text-red-600 dark:text-red-400" />
                        </div>
                        <CardTitle className="text-2xl font-bold">Not Found</CardTitle>
                        <CardDescription className="mt-2 text-base">
                            {message}
                        </CardDescription>
                    </CardHeader>
                    
                    <CardContent className="text-center pb-2">
                        <p className="text-muted-foreground text-sm">
                            The procurement you're looking for might have been removed, 
                            had its name changed, or is temporarily unavailable.
                        </p>
                    </CardContent>
                    
                    <CardFooter className="flex flex-col gap-3 pt-2 sm:flex-row">
                        <Button 
                            variant="outline" 
                            className="w-full sm:w-auto"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" /> Go Back
                        </Button>
                        
                        <Button asChild className="w-full sm:w-auto">
                            <Link href="/procurements-list">
                                <Search className="mr-2 h-4 w-4" /> View All Procurements
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}