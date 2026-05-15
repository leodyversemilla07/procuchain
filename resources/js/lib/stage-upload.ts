import type { DocumentGuide } from '@/types/document-guide';

export function getUploadedRequiredCount(documentGuide: DocumentGuide | undefined, uploadedDocuments: string[]): number {
    if (!documentGuide) {
        return 0;
    }

    return documentGuide.required_documents.filter((document) => uploadedDocuments.includes(document.value)).length;
}

export function getUploadedOptionalCount(documentGuide: DocumentGuide | undefined, uploadedDocuments: string[]): number {
    if (!documentGuide) {
        return 0;
    }

    return documentGuide.optional_documents.filter((document) => uploadedDocuments.includes(document.value)).length;
}

export function getStageCompletionPercentage(documentGuide: DocumentGuide | undefined, uploadedRequiredCount: number): number {
    if (!documentGuide || documentGuide.counts.required_count === 0) {
        return 100;
    }

    return Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100);
}

export function hasUploadedAllRequiredDocuments(documentGuide: DocumentGuide | undefined, uploadedRequiredCount: number): boolean {
    if (!documentGuide) {
        return false;
    }

    return uploadedRequiredCount === documentGuide.counts.required_count;
}
