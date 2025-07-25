# Smart Contract Frontend Implementation

## Overview

This document explains the frontend implementation for the ProcuChain smart contract system. The frontend provides a React-based interface that integrates with the **100% PHP backend** smart contract validation system.

## 🔥 Frontend Architecture

### Technology Stack
- **React 18** with TypeScript
- **Inertia.js** for Laravel-React integration
- **Tailwind CSS** with Radix UI components
- **Vite** for building and development
- **React Hooks** for state management

### Key Components

#### 1. **Smart Contract Types** (`types/smart-contracts.ts`)
```typescript
// Comprehensive TypeScript interfaces for all smart contract operations
export interface SmartContractValidationResult {
    compliant: boolean;
    missing_fields: string[];
    invalid_fields: string[];
    stage: string;
    validation_timestamp: string;
}

export interface DocumentIntegrityResult {
    valid: boolean;
    blockchain_hash?: string;
    // ... additional fields
}

// Document type constraints from PHP backend
export const ALLOWED_DOCUMENT_TYPES = [
    'Purchase Request',
    'Minutes',
    'Attendance',
    // ... all types from PHP backend
] as const;
```

#### 2. **Smart Contract Hook** (`hooks/useSmartContractValidation.ts`)
```typescript
// Custom React hook for smart contract operations
export const useSmartContractValidation = (): UseSmartContractValidation => {
    const validateMetadata = async (metadata, stage) => {
        // Calls PHP backend: POST /smart-contracts/check-compliance
    };
    
    const validateIntegrity = async (procurementId, hash) => {
        // Calls PHP backend: POST /smart-contracts/validate-integrity
    };
    
    // ... other methods
};
```

#### 3. **Enhanced File Upload** (`components/smart-contract-file-upload-area.tsx`)
```tsx
// File upload component with real-time smart contract validation
<SmartContractFileUploadArea
    documentType="Evaluation Report"
    stage="Bid Evaluation"
    procurementId="PR-2024-001"
    enableSmartValidation={true}
    onValidationComplete={(result) => {
        // Handle validation results
    }}
/>
```

#### 4. **Validation Dashboard** (`components/smart-contract-dashboard.tsx`)
```tsx
// Comprehensive dashboard for smart contract monitoring
<SmartContractDashboard
    procurementId="PR-2024-001"
    autoRefresh={true}
    refreshInterval={30000}
/>
```

#### 5. **Status Indicators** (`components/validation-status-indicator.tsx`)
```tsx
// Visual indicators for validation status
<ValidationStatusIndicator
    validationResult={result}
    showTooltip={true}
    size="md"
/>
```

## 🎯 Integration with Existing Pages

### Example: Enhanced Document Upload

Your existing upload pages can be enhanced with smart contract validation:

```tsx
// Before: Basic file upload
<FileUploadArea
    label="Upload Document"
    file={file}
    onFileChange={handleFileChange}
/>

// After: Smart contract-enabled upload
<SmartContractFileUploadArea
    label="Upload Document"
    file={file}
    onFileChange={handleFileChange}
    documentType="Purchase Request"
    stage="Procurement Initiation"
    procurementId={procurementId}
    enableSmartValidation={true}
    onValidationComplete={(result) => {
        if (result.compliant) {
            toast.success('Document validation passed');
        } else {
            toast.error('Validation issues found');
        }
    }}
/>
```

## 🚀 API Integration

### Backend Endpoints
All frontend components integrate with your PHP backend endpoints:

```javascript
// Document metadata validation
POST /smart-contracts/check-compliance
Content-Type: application/json

{
    "metadata": {
        "hash": "abc123...",
        "file_size": 1024000,
        "document_type": "Purchase Request",
        // ... other fields
    },
    "stage": "Procurement Initiation"
}

// Document integrity verification
POST /smart-contracts/validate-integrity
{
    "procurement_id": "PR-2024-001",
    "document_hash": "abc123..."
}

// Storage consistency check
POST /smart-contracts/validate-storage
{
    "procurement_id": "PR-2024-001"
}

// Audit trail retrieval
GET /smart-contracts/audit-trail/{procurement_id}
```

### Response Handling
```typescript
const { validateMetadata, isLoading, error } = useSmartContractValidation();

try {
    const result = await validateMetadata(documentMetadata, stage);
    
    if (result.compliant) {
        // Document passed validation
        setValidationStatus('valid');
        toast.success('Smart contract validation passed');
    } else {
        // Show validation errors
        setValidationErrors(result.invalid_fields);
        toast.error(`${result.invalid_fields.length} validation issues found`);
    }
} catch (error) {
    // Handle API errors
    toast.error('Validation failed', { description: error.message });
}
```

## 🎨 User Experience Features

### 1. **Real-time Validation**
- Documents are validated as soon as they're uploaded
- Visual feedback with progress indicators
- Instant validation results with detailed error messages

### 2. **Visual Status Indicators**
```tsx
// Different states with appropriate icons and colors
✅ Valid - Green check with "Valid" badge
❌ Invalid - Red X with error count
⏳ Validating - Spinning loader with "Validating..." text
🛡️ Ready - Shield icon with "Ready" badge
```

### 3. **Detailed Validation Feedback**
```tsx
// Expandable validation details
<Collapsible>
    <CollapsibleTrigger>
        Smart Contract Validation Results ✅ Valid
    </CollapsibleTrigger>
    <CollapsibleContent>
        Document Type: Purchase Request
        Stage: Procurement Initiation
        Validation Time: 2024-01-15 10:30:00
        
        ✅ All smart contract validation rules passed
    </CollapsibleContent>
</Collapsible>
```

### 4. **Dashboard Monitoring**
- Real-time audit trail display
- Storage consistency monitoring
- System status overview
- Auto-refresh capabilities

## 📱 Responsive Design

All components are built with mobile-first responsive design:

```tsx
// Grid layouts adapt to screen size
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <StatusCard />
    <StatusCard />
    <StatusCard />
</div>

// Mobile-friendly file upload areas
<div className="p-4 sm:p-6 border-2 border-dashed rounded-lg">
    <FileUploadContent />
</div>
```

## 🔧 Setup Instructions

### 1. Install Dependencies
```bash
# Dependencies are already in your package.json
npm install
```

### 2. Add New Components
The following files have been created in your project:

```
resources/js/
├── types/
│   └── smart-contracts.ts                    # TypeScript interfaces
├── hooks/
│   └── useSmartContractValidation.ts         # React hooks
├── components/
│   ├── smart-contract-file-upload-area.tsx  # Enhanced file upload
│   ├── smart-contract-dashboard.tsx          # Monitoring dashboard
│   └── validation-status-indicator.tsx      # Status indicators
└── pages/
    └── smart-contract-demo/
        └── document-upload.tsx               # Example integration
```

### 3. Integration with Existing Pages

To add smart contract validation to your existing upload pages:

```tsx
// 1. Import the enhanced components
import SmartContractFileUploadArea from '@/components/smart-contract-file-upload-area';
import ValidationStatusIndicator from '@/components/validation-status-indicator';
import { useDocumentUploadValidation } from '@/hooks/useSmartContractValidation';

// 2. Replace existing FileUploadArea components
// 3. Add validation state management
// 4. Handle validation results
```

### 4. Route Setup
Add a route for the demo page in your Laravel routes:

```php
// routes/web.php
Route::get('/smart-contract-demo/{procurement}', function ($procurement) {
    return inertia('smart-contract-demo/document-upload', [
        'procurement' => $procurement
    ]);
})->middleware(['auth', 'role:bac_secretariat']);
```

## 🎯 Usage Examples

### Basic File Upload with Validation
```tsx
const [file, setFile] = useState<File | null>(null);
const [validationResult, setValidationResult] = useState(null);

<SmartContractFileUploadArea
    label="Upload Purchase Request"
    file={file}
    onFileChange={(e) => setFile(e.target.files?.[0] || null)}
    documentType="Purchase Request"
    stage="Procurement Initiation"
    procurementId="PR-2024-001"
    onValidationComplete={setValidationResult}
/>

{validationResult && (
    <ValidationStatusIndicator
        validationResult={validationResult}
        showTooltip={true}
        showText={true}
    />
)}
```

### Dashboard Integration
```tsx
// Add to any page that needs smart contract monitoring
<SmartContractDashboard
    procurementId={procurement.id}
    documentHashes={documentHashes}
    autoRefresh={true}
    refreshInterval={30000}
/>
```

### Form Validation
```tsx
const handleSubmit = (e) => {
    e.preventDefault();
    
    // Check smart contract validation before submission
    if (!validationResult?.compliant) {
        toast.error('Please fix validation issues before submitting');
        return;
    }
    
    // Proceed with form submission
    post('/upload', {
        onSuccess: () => toast.success('Upload successful with smart contract validation')
    });
};
```

## 🔍 Benefits of This Implementation

### 1. **User Experience**
- ✅ **Real-time feedback** - Users see validation results immediately
- ✅ **Clear error messages** - Specific guidance on fixing issues
- ✅ **Visual indicators** - Easy to understand status at a glance
- ✅ **Progressive enhancement** - Works with or without JavaScript

### 2. **Developer Experience**
- ✅ **Type safety** - Full TypeScript support with proper interfaces
- ✅ **Reusable components** - Drop-in replacements for existing uploads
- ✅ **Consistent patterns** - Follows existing React/Laravel conventions
- ✅ **Easy integration** - Minimal changes to existing code

### 3. **Technical Benefits**
- ✅ **Pure PHP backend** - No JavaScript dependencies on server
- ✅ **Laravel integration** - Works seamlessly with Inertia.js
- ✅ **Performance** - Efficient API calls with proper caching
- ✅ **Scalability** - Components can handle multiple file uploads

## 🚀 Next Steps

### 1. **Integration Phase**
1. Review the created components and types
2. Integrate `SmartContractFileUploadArea` into existing upload pages
3. Add `ValidationStatusIndicator` to document lists
4. Include `SmartContractDashboard` in procurement detail pages

### 2. **Testing Phase**
1. Test file upload validation with various document types
2. Verify real-time validation feedback
3. Test dashboard auto-refresh functionality
4. Validate error handling and edge cases

### 3. **Enhancement Phase**
1. Add batch validation for multiple files
2. Implement validation history tracking
3. Add export functionality for audit trails
4. Create admin panel for smart contract management

## 📋 Migration Guide

To migrate your existing upload pages to use smart contract validation:

### Step 1: Replace FileUploadArea
```tsx
// Old component
<FileUploadArea
    label="Upload Document"
    file={file}
    onFileChange={handleFileChange}
    // ... other props
/>

// New component with smart contract validation
<SmartContractFileUploadArea
    label="Upload Document"
    file={file}
    onFileChange={handleFileChange}
    documentType="Purchase Request"  // Add this
    stage={procurement.current_stage}  // Add this
    procurementId={procurement.id}     // Add this
    enableSmartValidation={true}       // Add this
    onValidationComplete={(result) => {  // Add this
        setValidationResult(result);
    }}
    // ... other existing props
/>
```

### Step 2: Add Validation State
```tsx
// Add validation state management
const [validationResult, setValidationResult] = useState(null);

// Update form submission logic
const handleSubmit = (e) => {
    e.preventDefault();
    
    // Add smart contract validation check
    if (validationResult && !validationResult.compliant) {
        toast.error('Please fix validation issues before submitting');
        return;
    }
    
    // Existing submission logic
    post('/upload', data);
};
```

### Step 3: Add Status Indicators
```tsx
// Add visual validation status
<div className="flex items-center gap-2">
    <Label>Document Status:</Label>
    <ValidationStatusIndicator
        validationResult={validationResult}
        showTooltip={true}
    />
</div>
```

This frontend implementation provides a comprehensive, user-friendly interface for your pure PHP smart contract system, enhancing the user experience while maintaining the simplicity and reliability of your backend implementation.
