import { toast } from 'sonner';
import { ProcurementListItem } from '@/types/blockchain';

type CSVValue = string | number | null | undefined;

const formatCSVValue = (value: CSVValue): string => {
    if (value === null || value === undefined) return '';
    const stringValue = String(value);
    return stringValue.includes(',') || stringValue.includes('"')
        ? `"${stringValue.replace(/"/g, '""')}"`
        : stringValue;
};

const getProcurementRowData = (procurement: ProcurementListItem): string[] => [
    procurement.id,
    procurement.title,
    procurement.stage,
    procurement.current_status,
    procurement.document_count?.toString() || '0',
    procurement.last_updated || 'N/A',
    procurement.timestamp || new Date().toISOString(),
];

const generateCSVContent = (procurements: ProcurementListItem[]): string => {
    const headers = ['ID', 'Title', 'Phase', 'State', 'Documents', 'Last Updated', 'Timestamp'];
    const rows = procurements.map(proc => 
        getProcurementRowData(proc).map(formatCSVValue).join(',')
    );
    return [headers.join(','), ...rows].join('\n');
};

const downloadCSV = (content: string, fileName: string): void => {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.setAttribute('href', url);
    link.setAttribute('download', fileName);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

export const exportProcurementsToCSV = (procurements: ProcurementListItem[]): void => {
    try {
        const fileName = `procurements-export-${new Date().toISOString().slice(0, 10)}.csv`;
        const csvContent = generateCSVContent(procurements);
        downloadCSV(csvContent, fileName);
        
        toast.success(`Successfully exported ${procurements.length} procurements to CSV`, {
            description: `File: ${fileName}`,
            duration: 5000,
        });
    } catch (e) {
        console.error('Failed to export CSV:', e);
        toast.error('Failed to export data to CSV', {
            description: 'Please try again later',
            duration: 5000,
        });
    }
};