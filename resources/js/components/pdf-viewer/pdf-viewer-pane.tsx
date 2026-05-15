import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ChevronLeft, ChevronRight, Download, Eye, FileText, ZoomIn, ZoomOut } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';
import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Configure pdf.js worker — serve from public/ as a static asset.
// Vite's `new URL(..., import.meta.url)` pattern doesn't emit the worker file
// when it's inside a bundled dependency (Vite #7025, #20631).
// Per react-pdf v10 docs "Option 2: Copy worker to public directory":
// Copy pdfjs-dist/build/pdf.worker.min.mjs → public/pdf.worker.min.mjs
// Then set workerSrc to the static path.
//
// CSP: worker-src 'self' blob: — 'self' covers /pdf.worker.min.mjs.
// The predeploy hook must also copy this file (see .platform/hooks/predeploy/).
pdfjs.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs';

interface Props {
    pdfUrl: string;
    pdfHeight: number;
    pdfLoading: boolean;
    pdfError: boolean;
    onLoadingChange: (loading: boolean) => void;
    onErrorChange: (error: boolean) => void;
}

/**
 * PDF Viewer using react-pdf (pdf.js) — renders via Canvas, no iframe/plugin.
 *
 * Why react-pdf instead of <iframe src="blob:...">:
 *
 * Chrome's built-in PDF viewer is a MimeHandlerView plugin that creates its own
 * internal iframe. That plugin-iframe is blocked by CSP sandbox directives — and
 * there's NO sandbox allow-* directive that permits PDF plugins (confirmed by
 * WHATWG, Chromium issue #343754409, PrivateBin #1552, bulwarkmail/webmail #253).
 *
 * react-pdf renders PDFs to <canvas> elements via Mozilla's pdf.js library:
 * - No plugin, no iframe, no CSP sandbox issues
 * - Works identically across Chrome, Firefox, Safari, Edge
 * - Full control over UI (page nav, zoom, download)
 * - Security: pdf.js doesn't execute embedded PDF JavaScript
 * - Our strict CSP (with sandbox) remains fully intact
 */
export default function PdfViewerPane({ pdfUrl, pdfHeight, onLoadingChange, onErrorChange }: Props) {
    const [numPages, setNumPages] = useState<number>(0);
    const [pageNumber, setPageNumber] = useState<number>(1);
    const [scale, setScale] = useState<number>(1.2);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);

    // Pass file as an object with withCredentials so pdfjs includes session cookies
    // when fetching the PDF from the authenticated /files/ endpoint.
    // Must be memoized per react-pdf docs to avoid unnecessary reloads.
    const fileConfig = useMemo(
        () => ({
            url: pdfUrl,
            withCredentials: true as const,
        }),
        [pdfUrl],
    );

    const onDocumentLoadSuccess = useCallback(
        ({ numPages }: { numPages: number }) => {
            setNumPages(numPages);
            setPageNumber(1);
            setLoading(false);
            setError(false);
            onLoadingChange(false);
            onErrorChange(false);
        },
        [onLoadingChange, onErrorChange],
    );

    const onDocumentLoadError = useCallback(() => {
        setLoading(false);
        setError(true);
        onLoadingChange(false);
        onErrorChange(true);
    }, [onLoadingChange, onErrorChange]);

    const goToPrevPage = () => setPageNumber((prev) => Math.max(prev - 1, 1));
    const goToNextPage = () => setPageNumber((prev) => Math.min(prev + 1, numPages));
    const zoomIn = () => setScale((prev) => Math.min(prev + 0.2, 3.0));
    const zoomOut = () => setScale((prev) => Math.max(prev - 0.2, 0.4));

    const containerHeight = Math.max(500, pdfHeight);

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border bg-background relative flex flex-col overflow-hidden rounded-lg border shadow-md">
            {/* Toolbar */}
            {!loading && !error && numPages > 0 && (
                <div className="bg-muted/50 flex items-center justify-between gap-2 border-b px-3 py-1.5 text-xs sm:px-4 sm:py-2 sm:text-sm">
                    <div className="flex items-center gap-1 sm:gap-2">
                        <Button variant="ghost" size="icon" className="h-7 w-7 sm:h-8 sm:w-8" onClick={goToPrevPage} disabled={pageNumber <= 1}>
                            <ChevronLeft className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </Button>
                        <span className="text-muted-foreground min-w-[60px] text-center">
                            {pageNumber} / {numPages}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7 sm:h-8 sm:w-8"
                            onClick={goToNextPage}
                            disabled={pageNumber >= numPages}
                        >
                            <ChevronRight className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </Button>
                    </div>

                    <div className="flex items-center gap-1 sm:gap-2">
                        <Button variant="ghost" size="icon" className="h-7 w-7 sm:h-8 sm:w-8" onClick={zoomOut} disabled={scale <= 0.4}>
                            <ZoomOut className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </Button>
                        <span className="text-muted-foreground min-w-[40px] text-center">{Math.round(scale * 100)}%</span>
                        <Button variant="ghost" size="icon" className="h-7 w-7 sm:h-8 sm:w-8" onClick={zoomIn} disabled={scale >= 3.0}>
                            <ZoomIn className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </Button>
                    </div>

                    <div className="flex items-center gap-1 sm:gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs sm:h-8"
                            render={<a href={pdfUrl} target="_blank" rel="noopener noreferrer" />}
                        >
                            <Eye className="mr-1 h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            <span className="hidden sm:inline">Open</span>
                        </Button>
                        <Button variant="ghost" size="sm" className="h-7 text-xs sm:h-8" render={<a href={pdfUrl} download />}>
                            <Download className="mr-1 h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            <span className="hidden sm:inline">Download</span>
                        </Button>
                    </div>
                </div>
            )}

            {/* PDF Content */}
            <div className="flex-1 overflow-auto" style={{ height: `${containerHeight}px`, maxHeight: 'calc(100vh - 250px)' }}>
                {error ? (
                    <div className="bg-muted flex h-full items-center justify-center" style={{ minHeight: '500px' }}>
                        <div className="max-w-md p-6 text-center sm:p-8">
                            <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12 sm:h-16 sm:w-16" />
                            <h3 className="text-primary mb-2 text-base font-semibold sm:text-lg">PDF Viewer Error</h3>
                            <p className="text-muted-foreground mb-4 text-xs sm:mb-6 sm:text-sm">
                                Unable to display the PDF in the browser. You can view the document using the options below.
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
                    </div>
                ) : (
                    <div className="flex justify-center py-4">
                        {loading && (
                            <div className="flex h-full items-center justify-center" style={{ minHeight: '500px' }}>
                                <div className="text-center">
                                    <Spinner className="text-primary mx-auto mb-4 size-10 sm:size-12" />
                                    <p className="text-primary text-base font-medium sm:text-lg">Loading PDF...</p>
                                    <p className="text-muted-foreground mt-2 text-xs sm:text-sm">Please wait while the document loads</p>
                                </div>
                            </div>
                        )}
                        <Document file={fileConfig} onLoadSuccess={onDocumentLoadSuccess} onLoadError={onDocumentLoadError} loading="">
                            {!loading && (
                                <Page
                                    pageNumber={pageNumber}
                                    scale={scale}
                                    renderTextLayer={true}
                                    renderAnnotationLayer={true}
                                    className="react-pdf__page"
                                />
                            )}
                        </Document>
                    </div>
                )}
            </div>
        </div>
    );
}
