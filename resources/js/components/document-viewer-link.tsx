import { Eye, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

interface DocumentViewStatsProps {
  fileKey: string;
  className?: string;
}

export function DocumentViewStats({ className }: DocumentViewStatsProps) {
  // Note: Stats are now only available in the full PDF viewer with analytics
  // This component remains for compatibility but directs users to the full viewer
  
  return (
    <div className={cn("flex items-center gap-3 text-xs text-muted-foreground", className)}>
      <div className="flex items-center gap-1">
        <Eye className="h-3 w-3" />
        <span>View stats in analytics page</span>
      </div>
    </div>
  );
}

interface PdfViewerLinkProps {
  fileKey: string;
  showStats?: boolean;
  className?: string;
}

export function PdfViewerLink({ fileKey, showStats = false, className }: PdfViewerLinkProps) {
  const pdfUrl = `/secure-file/${encodeURIComponent(fileKey)}`;

  return (
    <div className={cn("space-y-2", className)}>
      <div className="flex gap-2">
        <Button
          variant="outline"
          size="sm"
          asChild
          className="flex-shrink-0 transition-all font-medium border-neutral-200 dark:border-neutral-700 text-xs sm:text-sm h-8 sm:h-9 shadow-sm hover:shadow group-hover:border-primary/30 group-hover:bg-white dark:group-hover:bg-neutral-800"
        >
          <Link
            href={`/pdf-viewer/${encodeURIComponent(fileKey)}`}
            className="flex items-center"
          >
            <TrendingUp className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5" aria-hidden="true" />
            View with Analytics
          </Link>
        </Button>

        <Button
          variant="outline"
          size="sm"
          asChild
          className="flex-shrink-0 transition-all font-medium border-neutral-200 dark:border-neutral-700 text-xs sm:text-sm h-8 sm:h-9 shadow-sm hover:shadow group-hover:border-primary/30 group-hover:bg-white dark:group-hover:bg-neutral-800"
        >
          <a
            href={pdfUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center"
          >
            <Eye className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5" aria-hidden="true" />
            Quick View
          </a>
        </Button>
      </div>

      {showStats && (
        <DocumentViewStats fileKey={fileKey} />
      )}
    </div>
  );
}
