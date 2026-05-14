import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Download, Eye, FileText, Loader2, Minus, Plus, RotateCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';

// Import styles for annotations and text layer
import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Configure worker using Vite-bundled worker (avoids CSP issues with CDN)
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
pdfjs.GlobalWorkerOptions.workerSrc = pdfjsWorker;

interface Props {
  pdfUrl: string;
  pdfHeight: number;
  pdfLoading: boolean;
  pdfError: boolean;
  onLoadingChange: (loading: boolean) => void;
  onErrorChange: (error: boolean) => void;
}

export default function PdfViewerPane({ pdfUrl, pdfHeight, pdfError, onLoadingChange, onErrorChange }: Props) {
  const [numPages, setNumPages] = useState<number>(0);
  const [pageNumber, setPageNumber] = useState<number>(1);
  const [scale, setScale] = useState<number>(1.0);
  const [rotation, setRotation] = useState<number>(0);
  const [viewerError, setViewerError] = useState<boolean>(false);
  const [blobUrl, setBlobUrl] = useState<string | null>(null);
  const [fetching, setFetching] = useState<boolean>(true);
  const prevBlobUrl = useRef<string | null>(null);

  // Fetch the PDF with credentials and create a blob URL.
  // This bypasses all auth/CORS/worker credential issues because:
  // 1. fetch() with credentials: 'same-origin' sends session + XSRF cookies
  // 2. The blob URL is same-origin, so pdf.js can load it without any auth
  // 3. The worker loads from a Vite-bundled asset (same-origin), no CSP issue
  useEffect(() => {
    if (!pdfUrl) return;

    setFetching(true);
    onLoadingChange(true);
    setViewerError(false);

    fetch(pdfUrl, { credentials: 'same-origin' })
      .then((res) => {
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }
        return res.blob();
      })
      .then((blob) => {
        // Clean up previous blob URL
        if (prevBlobUrl.current) {
          URL.revokeObjectURL(prevBlobUrl.current);
        }
        const url = URL.createObjectURL(blob);
        prevBlobUrl.current = url;
        setBlobUrl(url);
        setFetching(false);
      })
      .catch(() => {
        setFetching(false);
        onErrorChange(true);
        setViewerError(true);
      });

    // Cleanup on unmount
    return () => {
      if (prevBlobUrl.current) {
        URL.revokeObjectURL(prevBlobUrl.current);
        prevBlobUrl.current = null;
      }
    };
  }, [pdfUrl]); // eslint-disable-line react-hooks/exhaustive-deps

  const onDocumentLoadSuccess = useCallback(
    ({ numPages }: { numPages: number }) => {
      setNumPages(numPages);
      onLoadingChange(false);
      onErrorChange(false);
      setViewerError(false);
    },
    [onLoadingChange, onErrorChange],
  );

  const onDocumentLoadError = useCallback(() => {
    onLoadingChange(false);
    onErrorChange(true);
    setViewerError(true);
  }, [onLoadingChange, onErrorChange]);

  const goToFirstPage = () => setPageNumber(1);
  const goToLastPage = () => setPageNumber(numPages);
  const goToPrevPage = () => setPageNumber((prev) => Math.max(prev - 1, 1));
  const goToNextPage = () => setPageNumber((prev) => Math.min(prev + 1, numPages));

  const zoomIn = () => setScale((prev) => Math.min(prev + 0.25, 3));
  const zoomOut = () => setScale((prev) => Math.max(prev - 0.25, 0.5));
  const rotate = () => setRotation((prev) => (prev + 90) % 360);

  const showError = pdfError || viewerError;
  const isLoading = fetching;

  return (
    <div className="border-sidebar-border/70 dark:border-sidebar-border bg-background relative overflow-hidden rounded-lg border shadow-md">
      {/* Toolbar */}
      {!showError && numPages > 0 && (
        <div className="bg-muted/50 flex flex-wrap items-center justify-between gap-2 border-b px-2 py-2 sm:px-4">
          {/* Page Navigation */}
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={goToFirstPage} disabled={pageNumber <= 1}>
              <ChevronsLeft className="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={goToPrevPage} disabled={pageNumber <= 1}>
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <span className="text-muted-foreground px-2 text-sm">
              <span className="text-foreground font-medium">{pageNumber}</span> / {numPages}
            </span>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={goToNextPage} disabled={pageNumber >= numPages}>
              <ChevronRight className="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={goToLastPage} disabled={pageNumber >= numPages}>
              <ChevronsRight className="h-4 w-4" />
            </Button>
          </div>

          {/* Zoom & Rotate Controls */}
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={zoomOut} disabled={scale <= 0.5}>
              <Minus className="h-4 w-4" />
            </Button>
            <span className="text-muted-foreground w-14 text-center text-sm">{Math.round(scale * 100)}%</span>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={zoomIn} disabled={scale >= 3}>
              <Plus className="h-4 w-4" />
            </Button>
            <div className="bg-border mx-2 hidden h-6 w-px sm:block" />
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={rotate}>
              <RotateCw className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}

      {/* PDF Content */}
      <div
        className="min-h-[400px] overflow-auto sm:min-h-[500px] md:min-h-[650px] lg:min-h-[750px] xl:min-h-[900px]"
        style={{
          height: `${pdfHeight}px`,
          maxHeight: 'calc(100vh - 250px)',
        }}
      >
        {isLoading ? (
          <div className="flex h-full min-h-[400px] items-center justify-center">
            <div className="text-center">
              <Loader2 className="text-primary mx-auto mb-4 h-10 w-10 animate-spin sm:h-12 sm:w-12" />
              <p className="text-primary text-base font-medium sm:text-lg">Loading PDF...</p>
              <p className="text-muted-foreground mt-2 text-xs sm:text-sm">Please wait while the document loads</p>
            </div>
          </div>
        ) : showError ? (
          <div className="bg-muted flex h-full flex-col items-center justify-center">
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
        ) : blobUrl ? (
          <Document
            file={blobUrl}
            onLoadSuccess={onDocumentLoadSuccess}
            onLoadError={onDocumentLoadError}
            loading={
              <div className="flex h-full min-h-[400px] items-center justify-center">
                <div className="text-center">
                  <Loader2 className="text-primary mx-auto mb-4 h-10 w-10 animate-spin sm:h-12 sm:w-12" />
                  <p className="text-primary text-base font-medium sm:text-lg">Rendering PDF...</p>
                  <p className="text-muted-foreground mt-2 text-xs sm:text-sm">Please wait while the document renders</p>
                </div>
              </div>
            }
            error={
              <div className="bg-muted flex h-full min-h-[400px] flex-col items-center justify-center">
                <div className="max-w-md p-6 text-center sm:p-8">
                  <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12 sm:h-16 sm:w-16" />
                  <h3 className="text-primary mb-2 text-base font-semibold sm:text-lg">Failed to Load PDF</h3>
                  <p className="text-muted-foreground mb-4 text-xs sm:mb-6 sm:text-sm">
                    There was an error loading the PDF. Please try again or use the options below.
                  </p>
                  <div className="space-y-2 sm:space-y-3">
                    <Button
                      className="w-full text-xs sm:text-sm"
                      render={<a href={pdfUrl} target="_blank" rel="noopener noreferrer" />}
                    >
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
            }
            className="flex justify-center py-4"
          >
            <Page
              pageNumber={pageNumber}
              scale={scale}
              rotate={rotation}
              loading={
                <div className="flex items-center justify-center p-8">
                  <Loader2 className="text-primary h-8 w-8 animate-spin" />
                </div>
              }
              className="shadow-lg"
            />
          </Document>
        ) : null}
      </div>
    </div>
  );
}
