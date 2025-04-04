import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { FileTextIcon, PlusIcon } from 'lucide-react';

interface EmptyStateProps {
    userRole: string;
}

export const EmptyState = ({ userRole }: EmptyStateProps) => (
    <div className="flex flex-col items-center justify-center py-12 text-center">
        <div className="rounded-full bg-blue-50 p-3 dark:bg-blue-900/20 mb-4">
            <FileTextIcon className="h-6 w-6 text-blue-600 dark:text-blue-400" />
        </div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No procurement records found</h3>
        {userRole === 'bac_secretariat' ? (
            <>
                <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mb-6">
                    There are no procurement records in the blockchain yet. Start by creating your first procurement to begin tracking it on the blockchain.
                </p>
                <Button
                    className="bg-primary hover:bg-primary/90 text-sm font-medium shadow-sm transition-colors dark:bg-primary/90 dark:hover:bg-primary/80 dark:text-white/95"
                    asChild
                >
                    <Link href="/bac-secretariat/procurement/procurement-initiation" className="flex items-center">
                        <PlusIcon className="h-4 w-4 mr-1.5" />
                        Create First Procurement
                    </Link>
                </Button>
            </>
        ) : (
            <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mb-6">
                There are currently no procurement records available for review. New procurements will appear here once they are initiated by the BAC Secretariat.
            </p>
        )}
    </div>
);