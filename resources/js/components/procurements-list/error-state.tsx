import { Button } from '@/components/ui/button';
import { AlertCircleIcon } from 'lucide-react';

interface ErrorStateProps {
    error: string;
}

export const ErrorState = ({ error }: ErrorStateProps) => (
    <div className="flex flex-col items-center justify-center py-12 text-center">
        <div className="rounded-full bg-red-50 p-3 dark:bg-red-900/20 mb-4">
            <AlertCircleIcon className="h-6 w-6 text-red-600 dark:text-red-400" />
        </div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
            Error Loading Procurements
        </h3>
        <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mb-6">{error}</p>
        <Button
            className="bg-primary hover:bg-primary/90 text-sm font-medium"
            onClick={() => window.location.reload()}
        >
            Retry
        </Button>
    </div>
);