import { FileText, Calendar, Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface FormHeaderProps {
  formState?: {
    isDraft?: boolean;
    isComplete?: boolean;
    createdAt?: string;
    lastUpdated?: string;
    reference?: string;
  };
}

export function FormHeader({ formState }: FormHeaderProps) {
  const today = new Date().toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });

  return (
    <div className="bg-white dark:bg-gray-800 shadow-md rounded-xl p-4 sm:p-6 mb-6 sm:mb-8 border border-gray-100 dark:border-gray-700 transition-all duration-200">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start sm:items-center gap-3 sm:gap-5">
          <div className="flex-shrink-0 bg-primary/20 dark:bg-primary/30 p-3 sm:p-4 rounded-xl shadow-sm">
            <FileText className="h-7 sm:h-9 w-7 sm:w-9 text-primary" />
          </div>
          <div>
            <div className="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-0.5 sm:mb-1">
              <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50">
                New Procurement
              </h1>
              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Info className="h-4 w-4 text-muted-foreground cursor-help mt-1" />
                  </TooltipTrigger>
                  <TooltipContent>
                    <p className="text-xs">Create a new procurement request</p>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            </div>
            <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
              <p className="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                Start your procurement process by providing necessary details.
              </p>
              <div className="flex items-center text-xs text-muted-foreground gap-1 mt-1 sm:mt-0">
                <Calendar className="h-3 w-3" />
                <span className="text-[10px] sm:text-xs">{formState?.lastUpdated || today}</span>
              </div>
            </div>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2 mt-2 sm:mt-0">
          <Badge
            className="bg-primary/10 hover:bg-primary/20 text-primary text-xs sm:text-sm px-2 py-1 sm:px-3 sm:py-1.5 rounded-md font-medium transition-colors duration-200"
          >
            Procurement Initiation
          </Badge>
          {formState?.reference && (
            <Badge className="text-xs sm:text-sm bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 px-2 py-1 sm:px-3 sm:py-1.5 rounded-md">
              {formState.reference}
            </Badge>
          )}
          {formState?.isDraft && (
            <Badge className="text-xs sm:text-sm bg-yellow-100 hover:bg-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 px-2 py-1 sm:px-3 sm:py-1.5 rounded-md transition-colors duration-200">
              Draft
            </Badge>
          )}
          {formState?.isComplete && (
            <Badge className="text-xs sm:text-sm bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/30 dark:text-green-300 px-2 py-1 sm:px-3 sm:py-1.5 rounded-md transition-colors duration-200">
              Complete
            </Badge>
          )}
        </div>
      </div>
    </div>
  );
}
