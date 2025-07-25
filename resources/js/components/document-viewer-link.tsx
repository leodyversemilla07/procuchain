import { useState, useEffect } from 'react';
import { Eye, Users, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

interface DocumentViewStatsProps {
  fileKey: string;
  className?: string;
}

interface ViewStats {
  total_views: number;
  unique_viewers: number;
  today_views: number;
}

interface DocumentView {
  user: {
    name: string;
    role: string;
  };
  viewed_at: string;
}

interface ApiResponse {
  success: boolean;
  data: DocumentView[];
}

export function DocumentViewStats({ fileKey, className }: DocumentViewStatsProps) {
  const [stats, setStats] = useState<ViewStats | null>(null);
  const [loading, setLoading] = useState(false); useEffect(() => {
    const fetchStats = async () => {
      if (!fileKey) return;

      setLoading(true);
      try {
        const response = await fetch(`/api/document-views/file/${encodeURIComponent(fileKey)}`);
        const data: ApiResponse = await response.json();

        if (data.success) {
          const views: DocumentView[] = data.data;
          const uniqueViewers = new Set(views.map((v) => v.user.name)).size;
          const todayViews = views.filter((v) => {
            const viewDate = new Date(v.viewed_at).toDateString();
            const today = new Date().toDateString();
            return viewDate === today;
          }).length;

          setStats({
            total_views: views.length,
            unique_viewers: uniqueViewers,
            today_views: todayViews,
          });
        }
      } catch (error) {
        console.error('Failed to fetch view stats:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchStats();
  }, [fileKey]);

  if (loading || !stats) {
    return (
      <div className={cn("flex items-center gap-2 text-xs text-muted-foreground", className)}>
        <Eye className="h-3 w-3" />
        <span>Loading...</span>
      </div>
    );
  }

  return (
    <div className={cn("flex items-center gap-3 text-xs text-muted-foreground", className)}>
      <div className="flex items-center gap-1">
        <Eye className="h-3 w-3" />
        <span>{stats.total_views}</span>
      </div>
      <div className="flex items-center gap-1">
        <Users className="h-3 w-3" />
        <span>{stats.unique_viewers}</span>
      </div>
      {stats.today_views > 0 && (
        <Badge variant="secondary" className="text-xs px-1.5 py-0.5">
          {stats.today_views} today
        </Badge>
      )}
    </div>
  );
}

interface PdfViewerLinkProps {
  fileKey: string;
  showStats?: boolean;
  className?: string;
}

export function PdfViewerLink({ fileKey, showStats = true, className }: PdfViewerLinkProps) {
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
