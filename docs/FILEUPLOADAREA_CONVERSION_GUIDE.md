# FileUploadArea Conversion Guide

## Summary
Converting 10 remaining upload pages to use FileUploadArea pattern with inline progress tracking.

## Completed
✅ supplemental-bid-bulletin-upload.tsx - Fully converted with FileUploadArea pattern

## Pattern to Follow

All conversions follow the same structure as `supplemental-bid-bulletin-upload.tsx` (526 lines).

### Required Changes Per File

#### 1. Imports Section (Lines 1-27)
```typescript
// ADD:
import { useState, useCallback } from 'react'; // Add to React import
import FileUploadArea from '@/components/file-upload-area';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { AlertCircle } from 'lucide-react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

// UPDATE import for controller (based on stage):
import { markStageComplete, uploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/[CONTROLLER]';
// PreProcurementController for: bidding-documents
// ProcurementController for: bid-opening, bid-evaluation, post-qualification, bac-resolution
// PostProcurementController for: noa, performance-bond, ntp, monitoring, completion
```

#### 2. Interface (Lines 28-35)
```typescript
interface [ComponentName]Props {
    procurement: {
        pr_number: string;  // CHANGE FROM: id: string
        title: string;
        status?: string;
    };
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}
```

#### 3. Component Function & State (Lines 37-55)
```typescript
export default function [ComponentName]({ procurement, documentGuide, uploadedDocuments = [] }: [ComponentName]Props) {
    // REMOVE:
    const { isUploading, handleDocumentUpload } = useProgressiveUpload({...});

    // ADD:
    const [files, setFiles] = useState<Record<string, File | null>>({});
    const [dragging, setDragging] = useState<Record<string, boolean>>({});
    const [isUploading, setIsUploading] = useState(false);
    const [isMarkingComplete, setIsMarkingComplete] = useState(false);
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        documentValue: string;
        documentName: string;
    }>({
        open: false,
        documentValue: '',
        documentName: '',
    });
```

#### 4. Breadcrumbs (Update pr_number)
```typescript
const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
    getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
    { title: `Upload [Stage Name] - ${procurement?.pr_number || 'Unknown'}${procurement?.title ? ': ' + procurement.title : ''}`, href: '#' },
]);
```

#### 5. handleMarkComplete (Lines 60-90)
```typescript
const handleMarkComplete = () => {
    setIsMarkingComplete(true);
    router.post(
        markStageComplete({ pr_number: procurement.pr_number, stage: '[STAGE_KEY]' }).url,
        {},
        {
            onSuccess: (page) => {
                const flash = (page.props as Record<string, unknown>).flash as Record<string, unknown> | undefined;
                const response = flash?.success;
                if (typeof response === 'object' && response && 'blockchain' in response) {
                    const { message, blockchain } = response as { message: string; blockchain: { status_txid?: string; event_txid?: string } };
                    toast.success(message, {
                        description: (
                            <div className="space-y-1 text-xs">
                                {blockchain.status_txid && (
                                    <p>Status TX: {blockchain.status_txid}</p>
                                )}
                                {blockchain.event_txid && (
                                    <p>Event TX: {blockchain.event_txid}</p>
                                )}
                            </div>
                        ),
                    });
                } else {
                    toast.success('Stage marked as complete!', {
                        description: 'All required documents have been uploaded.',
                    });
                }
            },
            onError: () => {
                toast.error('Failed to mark stage as complete', {
                    description: 'Please try again or contact support.',
                });
            },
            onFinish: () => {
                setIsMarkingComplete(false);
            },
            preserveScroll: true,
        },
    );
};
```

#### 6. Progress Calculations (Lines 92-100)
```typescript
const uploadedRequiredCount = documentGuide
    ? documentGuide.required_documents.filter((doc) => uploadedDocuments.includes(doc.value)).length
    : 0;

const calculatedPercentage =
    documentGuide && documentGuide.counts.required_count > 0
        ? Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100)
        : 100;

const allRequiredUploaded = documentGuide && uploadedRequiredCount === documentGuide.counts.required_count;
const isStageCompleted = [STATUS_CHECK]; // See mapping below
```

#### 7. File Validation Callback (Lines 102-120)
```typescript
const validateFile = useCallback((file: File): boolean => {
    if (file.size > 10 * 1024 * 1024) {
        toast.error('File too large', {
            description: 'File size must not exceed 10MB.',
        });
        return false;
    }
    if (file.type && file.type !== 'application/pdf') {
        toast.error('Invalid file type', {
            description: 'Only PDF files are allowed.',
        });
        return false;
    }
    if (!file.type && !file.name.toLowerCase().endsWith('.pdf')) {
        toast.error('Invalid file type', {
            description: 'Only PDF files are allowed.',
        });
        return false;
    }
    return true;
}, []);
```

#### 8. Event Handlers (Lines 122-180)
```typescript
const handleFileChange = (documentValue: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file && validateFile(file)) {
        setFiles((prev) => ({ ...prev, [documentValue]: file }));
    } else if (file) {
        e.target.value = '';
    }
};

const handleDragEnter = (documentValue: string) => (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragging((prev) => ({ ...prev, [documentValue]: true }));
};

const handleDragLeave = (documentValue: string) => (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragging((prev) => ({ ...prev, [documentValue]: false }));
};

const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
};

const handleDrop = (documentValue: string) => (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragging((prev) => ({ ...prev, [documentValue]: false }));

    const file = e.dataTransfer.files?.[0];
    if (file && validateFile(file)) {
        setFiles((prev) => ({ ...prev, [documentValue]: file }));
    }
};

const handleRemove = (documentValue: string) => () => {
    setFiles((prev) => ({ ...prev, [documentValue]: null }));
};

const handleUploadClick = (documentValue: string, documentName: string) => {
    const file = files[documentValue];
    if (!file) {
        toast.error('No file selected', {
            description: 'Please select a file to upload.',
        });
        return;
    }

    setConfirmDialog({
        open: true,
        documentValue,
        documentName,
    });
};
```

#### 9. Upload Confirmation Handler (Lines 182-220)
```typescript
const handleConfirmUpload = useCallback(() => {
    const file = files[confirmDialog.documentValue];
    
    if (!file) {
        toast.error('No file selected', {
            description: 'Please select a file to upload.',
        });
        return;
    }

    const uploadToast = toast.loading('Uploading document...');
    setIsUploading(true);

    router.post(
        uploadSingleDocument({ pr_number: procurement.pr_number, stage: '[STAGE_KEY]' }).url,
        {
            document_file: file,
            document_type: confirmDialog.documentValue,
            description: confirmDialog.documentName,
        },
        {
            onSuccess: () => {
                toast.success('Document uploaded successfully!', {
                    id: uploadToast,
                    description: `${confirmDialog.documentName} has been uploaded.`,
                });
                setFiles((prev) => ({ ...prev, [confirmDialog.documentValue]: null }));
                setConfirmDialog({ open: false, documentValue: '', documentName: '' });
                setIsUploading(false);
            },
            onError: (errors) => {
                const errorMessage = errors.message || Object.values(errors)[0] || 'Failed to upload document';
                toast.error('Upload failed', {
                    id: uploadToast,
                    description: errorMessage,
                });
                setIsUploading(false);
            },
            preserveScroll: true,
            only: ['uploadedDocuments'],
            forceFormData: true,
        }
    );
}, [confirmDialog, files, procurement.pr_number]);
```

#### 10. JSX Structure (Lines 222-526)
- Update page title and icon
- Update procurement.id → procurement.pr_number in all places
- Keep Progress card structure
- Keep FileUploadArea structure with required/optional documents
- Keep AlertDialog at bottom

## File-Specific Mappings

### 1. bid-opening-upload.tsx
- Stage: `bid_opening`
- Title: `Bid Opening`
- Icon: `FolderOpen`
- Controller: `ProcurementController`
- Status Check: `procurement.status?.includes('bid_opening') && procurement.status?.includes('completed')`

### 2. bid-evaluation-upload.tsx
- Stage: `bid_evaluation`
- Title: `Bid Evaluation`
- Icon: `FileCheck` (import from lucide-react)
- Controller: `ProcurementController`
- Status Check: `procurement.status?.includes('bid_evaluation') && procurement.status?.includes('completed')`

### 3. bidding-documents-upload.tsx
- Stage: `bidding_documents`
- Title: `Bidding Documents`
- Icon: `FileText`
- Controller: `PreProcurementController`
- Status Check: `procurement.status === 'bidding_documents_completed'`

### 4. post-qualification-upload.tsx
- Stage: `post_qualification`
- Title: `Post Qualification`
- Icon: `CheckSquare` (import from lucide-react)
- Controller: `ProcurementController`
- Status Check: `procurement.status?.includes('post_qualification') && procurement.status?.includes('completed')`

### 5. bac-resolution-upload.tsx
- Stage: `bac_resolution`
- Title: `BAC Resolution`
- Icon: `FileCheck2` (import from lucide-react)
- Controller: `ProcurementController`
- Status Check: `procurement.status?.includes('bac_resolution') && procurement.status?.includes('completed')`

### 6. noa-upload.tsx
- Stage: `noa`
- Title: `Notice of Award`
- Icon: `Award` (import from lucide-react)
- Controller: `PostProcurementController`
- Status Check: `procurement.status?.includes('noa') && procurement.status?.includes('completed')`

### 7. performance-bond-contract-po-upload.tsx
- Stage: `performance_bond`
- Title: `Performance Bond/Contract/PO`
- Icon: `FileSignature` (import from lucide-react)
- Controller: `PostProcurementController`
- Status Check: `procurement.status?.includes('performance') && procurement.status?.includes('completed')`

### 8. ntp-upload.tsx
- Stage: `ntp`
- Title: `Notice to Proceed`
- Icon: `Send` (import from lucide-react)
- Controller: `PostProcurementController`
- Status Check: `procurement.status?.includes('ntp') && procurement.status?.includes('completed')`

### 9. monitoring-upload.tsx
- Stage: `monitoring`
- Title: `Monitoring`
- Icon: `Activity` (import from lucide-react)
- Controller: `PostProcurementController`
- Status Check: `procurement.status === 'monitoring_completed'`

### 10. completion-upload.tsx
- Stage: `completion`
- Title: `Completion`
- Icon: `CheckCircle`
- Controller: `PostProcurementController`
- Status Check: `procurement.status === 'completed'`

## Verification Checklist

After each conversion, verify:
- [ ] All imports updated (added FileUploadArea, AlertDialog, etc.)
- [ ] Interface changed from `id` to `pr_number`
- [ ] All 5 useState hooks added
- [ ] All event handlers added (fileChange, dragEnter, dragLeave, dragOver, drop, remove, uploadClick, confirmUpload)
- [ ] handleMarkComplete includes blockchain feedback
- [ ] Progress calculations present
- [ ] validateFile callback present
- [ ] FileUploadArea used for each document (required and optional)
- [ ] AlertDialog present at bottom
- [ ] All `procurement.id` changed to `procurement.pr_number`
- [ ] Correct stage key used in markStageComplete and uploadSingleDocument
- [ ] Correct controller imported
- [ ] Correct status check for isStageCompleted
- [ ] Correct icon imported and used

## Build After Each File
Run `npm run build` after each conversion to catch TypeScript errors early.

## Final Validation
After all conversions:
1. Run `npm run build` - should succeed with no errors
2. Test each upload page in browser
3. Verify drag-and-drop works
4. Verify upload confirmation dialog appears
5. Verify progress tracking updates
6. Verify blockchain feedback in toast
7. Verify completion badge appears when stage is complete
