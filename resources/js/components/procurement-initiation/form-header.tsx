import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface FormHeaderProps {
  formState?: {
    isDraft?: boolean;
    isComplete?: boolean;
  };
}

export function FormHeader({ formState }: FormHeaderProps) {
  return (
    <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <div className="flex-shrink-0 bg-primary/20 dark:bg-primary/30 p-3 rounded-lg">
            <FileText className="h-8 w-8 text-primary" />
          </div>
          <div>
            <h1 className="text-3xl font-semibold text-gray-900 dark:text-gray-100">
              New Procurement
            </h1>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Start your procurement process by providing necessary details.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Badge className="bg-primary/5 text-primary px-3 py-1 rounded">
            Procurement Initiation
          </Badge>
          {formState?.isDraft && (
            <Badge className="bg-yellow-100 text-yellow-800 px-3 py-1 rounded">
              Draft
            </Badge>
          )}
          {formState?.isComplete && (
            <Badge className="bg-green-100 text-green-800 px-3 py-1 rounded">
              Complete
            </Badge>
          )}
        </div>
      </div>
    </div>
  );
}
