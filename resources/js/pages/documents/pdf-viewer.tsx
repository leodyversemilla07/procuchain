import DocumentInfoCard from '@/components/pdf-viewer/document-info-card';
import PdfViewerHeader from '@/components/pdf-viewer/pdf-viewer-header';
import PdfViewerPane from '@/components/pdf-viewer/pdf-viewer-pane';
import RecentViewersCard from '@/components/pdf-viewer/recent-viewers-card';
import StatisticsCards from '@/components/pdf-viewer/statistics-cards';
import ViewsByRoleCard from '@/components/pdf-viewer/views-by-role-card';
import AppLayout from '@/layouts/app-layout';
import { DocumentView, PdfDocument, SharedData, ViewStats } from '@/types';
import { buildBreadcrumbs, getProcurementDetailBreadcrumb, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    document: PdfDocument;
    fileKey: string;
    pdfUrl: string;
    viewStats: ViewStats;
    recentViews: DocumentView[];
}

export default function PdfViewer({ document, fileKey, pdfUrl, viewStats, recentViews }: Props) {
    const [pdfHeight, setPdfHeight] = useState(600);

    const statisticsPanelRef = useRef<HTMLDivElement>(null);

    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.roles?.[0] || auth?.user?.role || 'guest';

    // Build breadcrumbs using centralized utility
    const procurementsListBreadcrumb = getProcurementsListBreadcrumb(userRole);
    const procurementDetailBreadcrumb = getProcurementDetailBreadcrumb(userRole, document.pr_number);
    const breadcrumbs = buildBreadcrumbs(userRole, [procurementsListBreadcrumb, procurementDetailBreadcrumb, { title: 'PDF Viewer', href: '#' }]);

    useEffect(() => {
        const updateHeight = () => {
            if (window.innerWidth < 1024) {
                // Mobile/tablet: Use viewport-based height
                const viewportHeight = window.innerHeight;
                const headerHeight = 200; // Approximate header + padding
                const mobileHeight = Math.max(500, Math.min(700, viewportHeight - headerHeight));
                setPdfHeight(mobileHeight);
            } else if (statisticsPanelRef.current) {
                // Desktop: Match statistics panel height
                const statsHeight = statisticsPanelRef.current.offsetHeight;
                const desktopHeight = Math.max(600, Math.min(1200, statsHeight));
                setPdfHeight(desktopHeight);
            } else {
                // Fallback
                setPdfHeight(800);
            }
        };

        // Initial update with delay for layout to settle
        const delayedUpdate = setTimeout(() => {
            updateHeight();
        }, 100);

        // Additional update after content loads
        const lateUpdate = setTimeout(() => {
            updateHeight();
        }, 2000);

        // Debounced resize handler
        let resizeTimeout: NodeJS.Timeout;
        const debouncedResize = () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateHeight, 300);
        };

        window.addEventListener('resize', debouncedResize);

        return () => {
            window.removeEventListener('resize', debouncedResize);
            clearTimeout(delayedUpdate);
            clearTimeout(lateUpdate);
            clearTimeout(resizeTimeout);
        };
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`PDF Viewer - ${document.document_type_display}`} />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Document Header Card */}
                <PdfViewerHeader document={document} pdfUrl={pdfUrl} viewStats={viewStats} pdfError={false} />

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                    {/* PDF Viewer Pane */}
                    <div className="order-2 lg:order-1 lg:col-span-2">
                        <PdfViewerPane
                            pdfUrl={pdfUrl}
                            pdfHeight={pdfHeight}
                            pdfLoading={false}
                            pdfError={false}
                            onLoadingChange={() => {}}
                            onErrorChange={() => {}}
                        />
                    </div>

                    {/* Statistics Panel */}
                    <div ref={statisticsPanelRef} className="order-1 space-y-4 sm:space-y-6 lg:order-2">
                        <StatisticsCards viewStats={viewStats} />
                        <DocumentInfoCard document={document} fileKey={fileKey} viewStats={viewStats} />
                        <ViewsByRoleCard viewStats={viewStats} />
                        <RecentViewersCard recentViews={recentViews} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
