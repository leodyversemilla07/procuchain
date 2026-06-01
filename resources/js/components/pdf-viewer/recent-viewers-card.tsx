import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { DocumentView } from '@/types';
import { formatRole, formatUserAddress, getRoleBadgeColor } from '@/utils/pdf-viewer/helpers';
import { Clock, Globe, Shield, Users } from 'lucide-react';
import { useState } from 'react';

interface Props {
    recentViews: DocumentView[];
}

export default function RecentViewersCard({ recentViews }: Props) {
    const [showAllViewersDialog, setShowAllViewersDialog] = useState(false);

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base sm:text-lg">
                            <Clock className="h-4 w-4 sm:h-5 sm:w-5" />
                            Recent Viewers
                        </CardTitle>
                        <CardDescription className="text-xs sm:text-sm">
                            Last {Math.min(recentViews.length, 10)} of {recentViews.length} viewers
                        </CardDescription>
                    </div>
                    {recentViews.length > 10 && (
                        <Sheet open={showAllViewersDialog} onOpenChange={setShowAllViewersDialog}>
                            <SheetTrigger render={<Button variant="outline" size="sm" className="text-xs" />}>
                                <span className="hidden sm:inline">View All ({recentViews.length})</span>
                                <span className="sm:hidden">All ({recentViews.length})</span>
                            </SheetTrigger>
                            <SheetContent side="right" className="w-full sm:max-w-2xl">
                                <SheetHeader>
                                    <SheetTitle className="flex items-center gap-2">
                                        <Users className="h-5 w-5" />
                                        All Document Viewers
                                    </SheetTitle>
                                    <SheetDescription>Complete list of {recentViews.length} users who have viewed this document</SheetDescription>
                                </SheetHeader>
                                <ScrollArea className="mt-6 h-[calc(100vh-120px)]">
                                    <div className="space-y-3 pr-4">
                                        {recentViews.map((view, index) => (
                                            <div
                                                key={view.id}
                                                className="bg-card hover:bg-accent/50 flex min-w-0 items-center justify-between gap-3 rounded-lg border p-3 transition-colors"
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <div className="flex shrink-0 items-center gap-2">
                                                        <span className="text-muted-foreground bg-muted rounded px-2 py-1 font-mono text-xs">
                                                            #{index + 1}
                                                        </span>
                                                        <Avatar className="h-8 w-8">
                                                            <AvatarFallback>{view.user.name.charAt(0).toUpperCase()}</AvatarFallback>
                                                        </Avatar>
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="truncate text-sm font-medium">{view.user.name}</span>
                                                            <Badge
                                                                variant="secondary"
                                                                className={cn('shrink-0 text-xs', getRoleBadgeColor(view.user.role))}
                                                            >
                                                                {formatRole(view.user.role)}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                                            <span className="shrink-0">{view.viewed_at_human}</span>
                                                            {view.user_address && (
                                                                <>
                                                                    <span className="hidden sm:inline">•</span>
                                                                    <span className="bg-muted text-muted-foreground hidden shrink-0 truncate rounded px-1 py-0.5 font-mono text-xs sm:inline">
                                                                        <Shield className="mr-0.5 inline h-3 w-3" />
                                                                        {formatUserAddress(view.user_address)}
                                                                    </span>
                                                                </>
                                                            )}
                                                            <span className="hidden sm:inline">•</span>
                                                            <span className="hidden shrink-0 items-center gap-1 sm:flex">
                                                                <Globe className="h-3 w-3" />
                                                                {view.ip_address}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </ScrollArea>
                            </SheetContent>
                        </Sheet>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <ScrollArea className="h-48 sm:h-64">
                    <div className="space-y-2 sm:space-y-3">
                        {recentViews.slice(0, 10).map((view) => (
                            <div key={view.id} className="flex items-center justify-between gap-2 rounded-lg border p-2">
                                <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                    <Avatar className="h-7 w-7 shrink-0 sm:h-8 sm:w-8">
                                        <AvatarFallback>{view.user.name.charAt(0).toUpperCase()}</AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-1.5 sm:gap-2">
                                            <span className="truncate text-xs font-medium sm:text-sm">{view.user.name}</span>
                                            <Badge variant="secondary" className={cn('shrink-0 text-xs', getRoleBadgeColor(view.user.role))}>
                                                {formatRole(view.user.role)}
                                            </Badge>
                                        </div>
                                        <div className="text-muted-foreground flex flex-wrap items-center gap-1 text-xs sm:gap-2">
                                            <span className="truncate">{view.viewed_at_human}</span>
                                            {view.user_address && (
                                                <>
                                                    <span className="hidden sm:inline">•</span>
                                                    <span className="bg-muted text-muted-foreground hidden truncate rounded px-1 py-0.5 font-mono text-xs sm:inline">
                                                        {formatUserAddress(view.user_address)}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-muted-foreground hidden shrink-0 text-xs sm:block">{view.ip_address}</div>
                            </div>
                        ))}
                    </div>
                </ScrollArea>
                {recentViews.length > 10 && (
                    <div className="mt-4 border-t pt-4">
                        <Button variant="ghost" className="w-full text-xs sm:text-sm" onClick={() => setShowAllViewersDialog(true)}>
                            View All {recentViews.length} Viewers
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
