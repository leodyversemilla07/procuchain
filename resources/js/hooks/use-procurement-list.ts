import { useState, useEffect } from 'react';
import { ProcurementListItem } from '@/types/blockchain';

export type ViewType = 'table' | 'kanban';

interface UseProcurementListProps {
    initialProcurements: ProcurementListItem[];
    initialError?: string;
}

export const useProcurementList = ({ initialProcurements, initialError }: UseProcurementListProps) => {
    const [procurements, setProcurements] = useState<ProcurementListItem[]>(initialProcurements || []);
    const [selectedRows, setSelectedRows] = useState<ProcurementListItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [viewType, setViewType] = useState<ViewType>('table');
    const [error, setError] = useState<string | undefined>(initialError);
    const [modalOpen, setModalOpen] = useState(false);
    const [markCompleteDialogOpen, setMarkCompleteDialogOpen] = useState(false);
    const [selectedProcurement, setSelectedProcurement] = useState<{
        id: string;
        title: string;
    }>({ id: '', title: '' });

    useEffect(() => {
        setViewType('table');
        if (initialError) {
            console.error('Backend error:', initialError);
            setError(initialError);
        }
    }, [initialError]);

    useEffect(() => {
        setProcurements(initialProcurements || []);
    }, [initialProcurements]);

    const handleOpenPreProcurementModal = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setModalOpen(true);
    };

    const handleOpenMarkCompleteDialog = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setMarkCompleteDialogOpen(true);
    };

    return {
        procurements,
        selectedRows,
        loading,
        viewType,
        error,
        modalOpen,
        markCompleteDialogOpen,
        selectedProcurement,
        setSelectedRows,
        setLoading,
        setViewType,
        setModalOpen,
        setMarkCompleteDialogOpen,
        handleOpenPreProcurementModal,
        handleOpenMarkCompleteDialog,
    };
};