import { Button } from '@/components/ui/button';
import { Download, Eye, FileText, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
  pdfUrl: string;
  pdfHeight: number;
  pdfLoading: boolean;
  pdfError: boolean;
  onLoadingChange: (loading: boolean) => void;
  onErrorChange: (error: boolean) => void;
}

/**
 * PDF Viewer using blob URL + iframe approach.
 *
 * Why this works when direct iframe/embed/object fail:
 *
 * 1. AUTH: The /files/{key} endpoint requires session cookies.
 *    <iframe src="/files/..."> makes a navigation request which sends cookies,
 *    but CSP frame-ancestors/object-src can block the browser's PDF plugin.
 *
 * 2. CSP: We fetch the PDF with credentials via JS (not blocked by CSP),
 *    create a blob: URL, and set it as the iframe src.
 *    Blob URLs are same-origin and don't trigger frame-ancestors checks
 *    because the blob was created by this page, not served by a remote endpoint.
 *
 * 3. The parent page CSP needs frame-src 'self' blob: and object-src 'self' blob:
 *    to allow the browser's built-in PDF renderer to work inside the iframe.
 *
 * Reference: https://notes.alexkehayias.com/preview-a-pdf-in-the-browser-with-authentication/
 * Reference: https://github.com/owncloud/web/pull/8498 (ownCloud fixed the same issue)
 */
export default function PdfViewerPane({ pdfUrl, pdfHeight, onLoadingChange, onErrorChange }: Props) {
  const [blobUrl, setBlobUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const prevBlobUrl = useRef<string | null>(null);

  useEffect(() => {
    if (!pdfUrl) return;

    setLoading(true);
    setError(false);
    onLoadingChange(true);
    onErrorChange(false);

    // Fetch the PDF with session cookies, then create a blob URL.
    // The blob URL is same-origin and bypasses CSP frame-ancestors restrictions.
    fetch(pdfUrl, { credentials: 'same-origin' })
      .then((res) => {
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }
        return res.blob();
      })
      .then((blob) => {
        // Clean up previous blob URL to prevent memory leaks
        if (prevBlobUrl.current) {
          URL.revokeObjectURL(prevBlobUrl.current);
        }
        const url = URL.createObjectURL(blob);
        prevBlobUrl.current = url;
        setBlobUrl(url);
        setLoading(false);
        onLoadingChange(false);
      })
      .catch(() => {
        setLoading(false);
        setError(true);
        onLoadingChange(false);
        onErrorChange(true);
      });

    // Cleanup on unmount
    return () => {
      if (prevBlobUrl.current) {
        URL.revokeObjectURL(prevBlobUrl.current);
        prevBlobUrl.current = null;
      }
    };
  }, [pdfUrl]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="border-sidebar-border/70 dark:border-sidebar-border bg-background relative overflow-hidden rounded-lg border shadow-md">
      {loading ? (
        <div
          className="flex items-center justify-center"
          style={{
            height: `${pdfHeight}px`,
            minHeight: '500px',
            maxHeight: 'calc(100vh - 250px)',
          }}
        >
          <div className="text-center">
            <Loader2 className="text-primary mx-auto mb-4 h-10 w-10 animate-spin sm:h-12 sm:w-12" />
            <p className="text-primary text-base font-medium sm:text-lg">Loading PDF...</p>
            <p className="text-muted-foreground mt-2 text-xs sm:text-sm">Please wait while the document loads</p>
          </div>
        </div>
      ) : error ? (
        <div
          className="bg-muted flex items-center justify-center"
          style={{
            height: `${pdfHeight}px`,
            minHeight: '500px',
            maxHeight: 'calc(100vh - 250px)',
          }}
        >
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
        <iframe
          src={blobUrl}
          title="PDF Document"
          className="w-full border-0"
          style={{
            height: `${pdfHeight}px`,
            minHeight: '500px',
            maxHeight: 'calc(100vh - 250px)',
          }}
        />
      ) : null}
    </div>
  );
}
