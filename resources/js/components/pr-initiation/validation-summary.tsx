import React from 'react';
import { AlertCircle, Check } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

interface ValidationSummaryProps {
  errors: Record<string, string>;
  hasErrors: boolean;
  isSubmitting: boolean;
}

export function ValidationSummary({ errors, hasErrors, isSubmitting }: ValidationSummaryProps) {
  // Group errors by section
  const groupedErrors: {[key: string]: string[]} = {};
  
  Object.entries(errors).forEach(([field, message]) => {
    let section = 'General';
    
    if (field.startsWith('procurement_')) {
      section = 'Procurement Details';
    } else if (field.startsWith('metadata.')) {
      const matches = field.match(/metadata\.(\d+)\./);
      if (matches && matches[1]) {
        section = `Document #${parseInt(matches[1]) + 1}`;
      } else {
        section = 'Document Metadata';
      }
    } else if (field.startsWith('files.')) {
      const matches = field.match(/files\.(\d+)/);
      if (matches && matches[1]) {
        section = `Document #${parseInt(matches[1]) + 1}`;
      } else {
        section = 'Documents';
      }
    }
    
    if (!groupedErrors[section]) {
      groupedErrors[section] = [];
    }
    groupedErrors[section].push(message);
  });
  
  if (isSubmitting) {
    return (
      <Alert className="bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300">
        <div className="h-4 w-4 animate-spin border-2 border-blue-600 border-t-transparent rounded-full mr-2" />
        <AlertTitle>Processing form submission...</AlertTitle>
        <AlertDescription>
          Please wait while we validate and submit your procurement details.
        </AlertDescription>
      </Alert>
    );
  }
  
  if (hasErrors) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertTitle>Form Validation Failed</AlertTitle>
        <AlertDescription>
          <div className="mt-2">
            {Object.entries(groupedErrors).map(([section, messages]) => (
              <div key={section} className="mb-3">
                <h4 className="font-medium text-sm">{section} Issues:</h4>
                <ul className="list-disc ml-5 mt-1 space-y-1">
                  {messages.map((message, i) => (
                    <li key={i} className="text-sm">{message}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </AlertDescription>
      </Alert>
    );
  }
  
  return (
    <Alert className="bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300">
      <Check className="h-4 w-4" />
      <AlertTitle>Ready to submit</AlertTitle>
      <AlertDescription>
        Your form is complete. Review your information before submitting.
      </AlertDescription>
    </Alert>
  );
}
