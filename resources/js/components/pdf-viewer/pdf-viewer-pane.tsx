import { Button } from '@/components/ui/button';
import { Download, Eye, FileText } from 'lucide-react';

interface Props {
  pdfUrl: string;
  pdfHeight: number;
  pdfLoading: boolean;
  pdfError: boolean;
  onLoadingChange: (loading: boolean) => void;
  onErrorChange: (error: boolean) => void;
}

export default function PdfViewerPane({ pdfUrl, pdfHeight }: Props) {
  return (
    <div className="border-sidebar-border/70 dark:border-sidebar-border bg-background relative overflow-hidden rounded-lg border shadow-md">
      <iframe
        src={pdfUrl}
        title="PDF Document"
        className="w-full border-0"
        style={{
          height: `${pdfHeight}px`,
          minHeight: '500px',
          maxHeight: 'calc(100vh - 250px)',
        }}
        onError={(e) => {
          // If iframe fails, the fallback links below will still work
          console.error('PDF iframe error:', e);
        }}
      />
      {/* Fallback: if browser can't render PDF in iframe, show download links */}
      <noscript>
        <div className="bg-muted flex flex-col items-center justify-center p-6 text-center sm:p-8">
          <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12 sm:h-16 sm:w-16" />
          <h3 className="text-primary mb-2 text-base font-semibold sm:text-lg">PDF Document</h3>
          <p className="text-muted-foreground mb-4 text-xs sm:mb-6 sm:text-sm">
            Your browser cannot display this PDF inline. Use the options below.
          </p>
          <div className="space-y-2 sm:space-y-3">
            <Button className="w-full text-xs sm:text-sm" render={<a href={pdfUrl} target="_blank" rel="noopener noreferrer" />}>
              <Eye className="mr-2 h-3 w-3 sm:h-4 sm:w-4" />
              Open PDF in New Tab
            </Button>
            <Button variant="outline" className="w-full text-xs sm:text-sm" render={<a href={pdfUrl} download />}>
              <Download className="mr-2 h-3 w-3 sm:h-4 sm:w-4" />
              Download PDF
            </Button>
          </div>
        </div>
      </noscript>
    </div>
  );
}
