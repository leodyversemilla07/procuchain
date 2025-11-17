export interface DocumentTypeOption {
    value: string;
    label: string;
    description: string;
    is_mandatory: boolean;
    requirement_summary: string;
}

export interface CategoryOption {
    value: string;
    label: string;
    description: string;
}

export interface ProcurementModeOption {
    value: string;
    label: string;
    description: string;
    threshold: number | null;
    requires_philgeps: boolean;
    requires_bac_resolution: boolean;
}

export interface OptionalDocument {
    id: string;
    document_type: string;
    file: File | null;
}

export type UseFormData = {
    // Basic Information - REQUIRED per RA 9184
    pr_number: string;
    ppmp_reference: string;
    title: string;
    description: string;

    // Financial Information (ABC = Approved Budget for Contract)
    abc_amount: string;
    funding_source: string;

    // Classification
    category: string;
    procurement_mode: string;

    // Municipal Office Information
    office: string;
    end_user: string;

    // Purpose
    purpose: string;

    // Delivery Details
    delivery_location: string;
    delivery_date: Date | undefined;
    delivery_term_days: string;

    // Prepared By
    prepared_by: string;
};

export interface StepProps {
    data: UseFormData;
    setData: <K extends keyof UseFormData>(key: K, value: UseFormData[K]) => void;
    errors: Partial<Record<keyof UseFormData, string>>;
    clearErrors: (...fields: (keyof UseFormData)[]) => void;
    hasError: (field: string) => boolean;
}
