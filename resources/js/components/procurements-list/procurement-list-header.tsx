import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { PlusIcon, Table2Icon, LayersIcon, ExternalLinkIcon, HelpCircleIcon } from 'lucide-react';

type ViewType = 'table' | 'kanban';

interface ProcurementListHeaderProps {
    userRole: string;
    viewType: ViewType;
    setViewType: (value: ViewType) => void;
    procurementsCount: number;
    loading: boolean;
}

export const ProcurementListHeader = ({
    userRole,
    viewType,
    setViewType,
    procurementsCount,
    loading,
}: ProcurementListHeaderProps) => {
    return (
        <CardHeader className="pb-4 border-b dark:border-sidebar-border">
            <div className="flex flex-col space-y-4 md:space-y-0 md:flex-row md:items-start md:justify-between">
                <div className="flex-1 min-w-0">
                    <CardTitle className="text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-50 flex items-center flex-wrap gap-2">
                        <span className="mr-0">Procurement List</span>
                        <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800">
                            {loading ? <span className="animate-pulse">...</span> : procurementsCount}
                        </Badge>
                    </CardTitle>
                    <CardDescription className="mt-1.5 text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
                        Track and manage all procurement activities with blockchain verification. All transactions are immutable and transparent.
                    </CardDescription>
                </div>
                <div className="flex flex-col xs:flex-row gap-3 md:items-start md:ml-4 md:flex-shrink-0">
                    <div className="flex items-center space-x-2">
                        <Button
                            variant={viewType === 'table' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setViewType('table')}
                            className="text-xs px-3 flex-1 xs:flex-none"
                        >
                            <Table2Icon className="h-3.5 w-3.5 mr-1.5" />
                            Table
                        </Button>
                        <Button
                            variant={viewType === 'kanban' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setViewType('kanban')}
                            className="text-xs px-3 flex-1 xs:flex-none"
                        >
                            <LayersIcon className="h-3.5 w-3.5 mr-1.5" />
                            Kanban
                        </Button>
                    </div>
                    {userRole === 'bac_secretariat' && (
                        <Button
                            className="bg-primary hover:bg-primary/90 text-sm font-medium shadow-sm transition-colors dark:bg-primary/90 dark:hover:bg-primary/80 dark:text-white/95 w-full xs:w-auto"
                            asChild
                        >
                            <Link href="/bac-secretariat/procurement/procurement-initiation" className="flex items-center justify-center">
                                <PlusIcon className="h-4 w-4 mr-1.5" />
                                New Procurement
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            <div className="mt-4 flex items-start md:items-center text-xs text-gray-500 dark:text-gray-400">
                <ExternalLinkIcon className="h-3.5 w-3.5 mr-1.5 mt-0.5 md:mt-0 flex-shrink-0" />
                <span className="flex-1">All procurements are verified on the blockchain network and cannot be tampered with</span>
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-5 w-5 ml-1 text-gray-400">
                                <HelpCircleIcon className="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent className="max-w-xs">
                            <p>Blockchain verification ensures all procurement records are tamper-proof and transparent to authorized parties.</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </CardHeader>
    );
};