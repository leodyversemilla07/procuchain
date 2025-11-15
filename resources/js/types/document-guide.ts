export interface DocumentItem {
    value: string;
    display_name: string;
    description: string;
}

export interface DocumentGuide {
    stage: string;
    stage_display_name: string;
    phase: string;
    description: string;
    required_documents: DocumentItem[];
    optional_documents: DocumentItem[];
    counts: {
        required_count: number;
        optional_count: number;
        total_count: number;
    };
}
