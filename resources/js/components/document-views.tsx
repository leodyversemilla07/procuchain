import { useState, useEffect, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Eye, Users, Clock, TrendingUp } from 'lucide-react';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';

interface DocumentViewsResponse {
  views?: DocumentView[];
}

interface DocumentView {
  id: number;
  user: {
    name: string;
    role: string;
  };
  viewed_at: string;
  viewed_at_human: string;
  ip_address: string;
  view_duration?: number;
}

interface DocumentViewsProps {
  fileKey: string;
  className?: string;
}

const getRoleBadgeColor = (role: string) => {
  switch (role) {
    case 'bac_chairman':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300';
    case 'bac_secretariat':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300';
    case 'hope':
      return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300';
    case 'admin':
      return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300';
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300';
  }
};

const formatRole = (role: string) => {
  switch (role) {
    case 'bac_chairman':
      return 'BAC Chairman';
    case 'bac_secretariat':
      return 'BAC Secretariat';
    case 'hope':
      return 'Head of Office';
    case 'admin':
      return 'Administrator';
    default:
      return role.charAt(0).toUpperCase() + role.slice(1);
  }
};

export function DocumentViews({ fileKey, className }: DocumentViewsProps) {
  const [views, setViews] = useState<DocumentView[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null); const fetchViews = useCallback(() => {
    if (!fileKey) return;

    setLoading(true);
    setError(null);

    router.get(`/document-views/file/${encodeURIComponent(fileKey)}`, {}, {
      onSuccess: (page) => {
        const response = page.props as DocumentViewsResponse;
        if (response.views) {
          setViews(response.views);
        } else {
          setError('Failed to load document views');
        }
      },
      onError: (errors) => {
        setError('Failed to load document views');
        console.error('Error fetching document views:', errors);
      },
      onFinish: () => {
        setLoading(false);
      },
      preserveState: true,
      preserveScroll: true,
      only: ['views']
    });
  }, [fileKey]);

  useEffect(() => {
    if (fileKey) {
      fetchViews();
    }
  }, [fileKey, fetchViews]);

  const totalViews = views.length;
  const uniqueViewers = new Set(views.map(v => v.user.name)).size;
  const recentViews = views.slice(0, 5);

  return (
    <div className={cn("space-y-4", className)}>
      {/* Quick Stats */}
      <div className="flex items-center gap-4 text-sm text-muted-foreground">
        <div className="flex items-center gap-1">
          <Eye className="h-4 w-4" />
          <span>{totalViews} views</span>
        </div>
        <div className="flex items-center gap-1">
          <Users className="h-4 w-4" />
          <span>{uniqueViewers} viewers</span>
        </div>
      </div>

      {/* View Details Dialog */}
      <Dialog>
        <DialogTrigger asChild>
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start text-left"
            onClick={fetchViews}
          >
            <TrendingUp className="h-4 w-4 mr-2" />
            View Document Access History
          </Button>
        </DialogTrigger>
        <DialogContent className="max-w-2xl max-h-[80vh]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Eye className="h-5 w-5" />
              Document Access History
            </DialogTitle>
          </DialogHeader>

          <ScrollArea className="max-h-[60vh]">
            {loading ? (
              <div className="flex items-center justify-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
              </div>
            ) : error ? (
              <div className="text-center py-8 text-muted-foreground">
                <p>{error}</p>
                <Button variant="outline" size="sm" onClick={fetchViews} className="mt-2">
                  Try Again
                </Button>
              </div>
            ) : views.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Eye className="h-12 w-12 mx-auto mb-4 opacity-50" />
                <p>No views recorded yet</p>
              </div>
            ) : (
              <div className="space-y-3">
                {/* Summary Stats */}
                <div className="grid grid-cols-2 gap-4 mb-6">
                  <Card>
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-sm text-muted-foreground">Total Views</p>
                          <p className="text-2xl font-bold">{totalViews}</p>
                        </div>
                        <Eye className="h-8 w-8 text-muted-foreground" />
                      </div>
                    </CardContent>
                  </Card>
                  <Card>
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-sm text-muted-foreground">Unique Viewers</p>
                          <p className="text-2xl font-bold">{uniqueViewers}</p>
                        </div>
                        <Users className="h-8 w-8 text-muted-foreground" />
                      </div>
                    </CardContent>
                  </Card>
                </div>

                {/* Detailed View List */}
                <div className="space-y-2">
                  <h4 className="font-medium text-sm text-muted-foreground mb-3">Recent Access History</h4>
                  {views.map((view) => (
                    <div
                      key={view.id}
                      className="flex items-center justify-between p-3 rounded-lg border bg-card"
                    >
                      <div className="flex items-center gap-3">
                        <div className="flex flex-col">
                          <div className="flex items-center gap-2">
                            <span className="font-medium text-sm">{view.user.name}</span>
                            <Badge
                              variant="secondary"
                              className={cn("text-xs", getRoleBadgeColor(view.user.role))}
                            >
                              {formatRole(view.user.role)}
                            </Badge>
                          </div>
                          <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Clock className="h-3 w-3" />
                            <span>{view.viewed_at_human}</span>
                            <span>•</span>
                            <span>{view.viewed_at}</span>
                          </div>
                          {view.view_duration && (
                            <div className="text-xs text-muted-foreground">
                              Viewed for {Math.round(view.view_duration / 60)} minutes
                            </div>
                          )}
                        </div>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {view.ip_address}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </ScrollArea>
        </DialogContent>
      </Dialog>

      {/* Recent Views Preview */}
      {recentViews.length > 0 && (
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium">Recent Views</CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="space-y-2">
              {recentViews.map((view) => (
                <div key={view.id} className="flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <span className="font-medium">{view.user.name}</span>
                    <Badge
                      variant="secondary"
                      className={cn("text-xs", getRoleBadgeColor(view.user.role))}
                    >
                      {formatRole(view.user.role)}
                    </Badge>
                  </div>
                  <span className="text-muted-foreground">{view.viewed_at_human}</span>
                </div>
              ))}
              {views.length > 5 && (
                <div className="text-center pt-2">
                  <span className="text-xs text-muted-foreground">
                    and {views.length - 5} more...
                  </span>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
