import { Button } from '@/components/ui/button';
import { Download, Eye, FileText } from 'lucide-react';
import { useEffect } from 'react';

interface Props {
    pdfUrl: string;
    pdfHeight: number;
    pdfLoading: boolean;
    pdfError: boolean;
    onLoadingChange: (loading: boolean) => void;
    onErrorChange: (error: boolean) => void;
}

export default function PdfViewerPane({ pdfUrl, pdfHeight, pdfLoading, pdfError, onLoadingChange, onErrorChange }: Props) {
    useEffect(() => {
        const timer = setTimeout(() => {
            if (pdfLoading) {
                onLoadingChange(false);
            }
        }, 15000);

        return () => clearTimeout(timer);
    }, [pdfLoading, onLoadingChange]);

    return (
        <div
            className="bg-background relative rounded-lg border"
            style={{
                height: `${pdfHeight}px`,
                minHeight: '500px',
                maxHeight: 'calc(100vh - 250px)',
            }}
        >
            {pdfError ? (
                <div className="bg-muted flex h-full flex-col items-center justify-center">
                    <div className="max-w-md p-6 text-center sm:p-8">
                        <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12 sm:h-16 sm:w-16" />
                        <h3 className="text-primary mb-2 text-base font-semibold sm:text-lg">PDF Viewer Error</h3>
                        <p className="text-muted-foreground mb-4 text-xs sm:mb-6 sm:text-sm">
                            Unable to display the PDF in the browser. You can view the document using the options below.
                        </p>
                        <div className="space-y-2 sm:space-y-3">
                            <Button asChild className="w-full text-xs sm:text-sm">
                                <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                    <Eye className="mr-2 h-3 w-3 sm:h-4 sm:w-4" />
                                    Open PDF in New Tab
                                </a>
                            </Button>
                            <Button variant="outline" asChild className="w-full text-xs sm:text-sm">
                                <a href={pdfUrl} download>
                                    <Download className="mr-2 h-3 w-3 sm:h-4 sm:w-4" />
                                    Download PDF
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>
            ) : (
                <>
                    <object
                        data={pdfUrl}
                        type="application/pdf"
                        className="bg-background h-full w-full rounded-lg"
                        style={{ minHeight: '500px' }}
                        onLoad={() => {
                            onLoadingChange(false);
                            onErrorChange(false);
                        }}
                        onError={() => {
                            onErrorChange(true);
                            onLoadingChange(false);
                        }}
                    >
                        <div className="bg-muted flex h-full w-full flex-col items-center justify-center rounded-lg" style={{ minHeight: '500px' }}>
                            <div className="max-w-md p-6 text-center sm:p-8">
                                <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12 sm:h-16 sm:w-16" />
                                <h3 className="text-primary mb-2 text-base font-semibold sm:text-lg">PDF Plugin Not Available</h3>
                                <p className="text-muted-foreground mb-4 text-xs sm:mb-6 sm:text-sm">
                                    Your browser doesn't support embedded PDFs. Use the buttons below to view the document.
                                </p>
                                <div className="space-y-2 sm:space-y-3">
                                    <Button asChild className="w-full text-xs sm:text-sm">
                                        <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                            <Eye className="mr-2 h-3 w-3 sm:h-4 sm:w-4" />
                                            Open PDF in New Tab
                                        </a>
                                    </Button>
                                    <Button variant="outline" asChild className="w-full text-xs sm:text-sm">
                                        <a href={pdfUrl} download>
                                            <Download className="mr-2 h-3 w-3 sm:h-4 sm:w-4" />
                                            Download PDF
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </object>

                    {pdfLoading && (
                        <div className="bg-background/95 absolute inset-0 z-10 flex items-center justify-center rounded-lg backdrop-blur-sm">
                            <div className="p-6 text-center sm:p-8">
                                <div className="border-primary mx-auto mb-4 h-10 w-10 animate-spin rounded-full border-b-3 sm:h-12 sm:w-12"></div>
                                <p className="text-primary text-base font-medium sm:text-lg">Loading PDF...</p>
                                <p className="text-muted-foreground mt-2 text-xs sm:text-sm">Please wait while the document loads</p>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
