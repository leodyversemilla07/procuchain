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
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-start gap-4">
          <div className="flex items-center">
            <div className="bg-primary/10 p-2 rounded-lg mr-3">
              <FileText className="h-6 w-6 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-bold">New Procurement</h1>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <Badge variant="outline" className="bg-primary/5 text-primary border-primary/20">
                  Procurement Initiation
                </Badge>
                {formState?.isDraft && (
                  <Badge variant="outline" className="bg-yellow-50 text-yellow-600 border-yellow-200">
                    Draft
                  </Badge>
                )}
                {formState?.isComplete && (
                  <Badge variant="outline" className="bg-green-50 text-green-600 border-green-200">
                    Complete
                  </Badge>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}