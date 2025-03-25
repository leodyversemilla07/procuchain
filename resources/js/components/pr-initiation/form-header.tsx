import { FileText, CheckCircle, CheckSquare } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface FormHeaderProps {
  currentStep: number;
  getFormCompletionPercentage: () => number;
}

export function FormHeader({
  currentStep,
  getFormCompletionPercentage,
}: FormHeaderProps) {
  return (
    <>
      <div className="flex items-center justify-between">
        <div className="flex items-start gap-4">
          <div className="flex items-center">
            <FileText className="h-6 w-6 text-primary mr-3" />
            <div>
              <h1 className="text-2xl font-bold">New Purchase Request</h1>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <Badge variant="outline">PR Initiation</Badge>
                <Badge variant="outline">Step {currentStep} of 3</Badge>
              </div>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Badge variant={getFormCompletionPercentage() === 100 ? "default" : "outline"}>
            <span className="flex items-center gap-1.5">
              {getFormCompletionPercentage() === 100 ? (
                <CheckCircle className="h-3.5 w-3.5" />
              ) : (
                <CheckSquare className="h-3.5 w-3.5" />
              )}
              <span>{getFormCompletionPercentage()}% Complete</span>
            </span>
          </Badge>
        </div>
      </div>
    </>
  );
}