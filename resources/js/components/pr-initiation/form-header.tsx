import React from 'react';
import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export function FormHeader() {
  return (
    <>
      <div className="flex items-center justify-between">
        <div className="flex items-start gap-4">
          <div className="flex items-center">
            <FileText className="h-6 w-6 text-primary mr-3" />
            <div>
              <h1 className="text-2xl font-bold">New Procurement</h1>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <Badge variant="outline">Procurement Initiation</Badge>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}