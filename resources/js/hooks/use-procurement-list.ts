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
    const [preProcurementModalOpen, setPreProcurementModalOpen] = useState(false);
    const [preBidConferenceModalOpen, setPreBidConferenceModalOpen] = useState(false);
    const [supplementalBidBulletinModalOpen, setSupplementalBidBulletinModalOpen] = useState(false);
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
        setPreProcurementModalOpen(true);
    };

    const handleOpenPreBidModal = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setPreBidConferenceModalOpen(true);
    };

    const handleOpenSupplementalBidBulletinModal = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setSupplementalBidBulletinModalOpen(true);
    };

    return {
        procurements,
        selectedRows,
        loading,
        viewType,
        error,
        preProcurementModalOpen,
        preBidConferenceModalOpen,
        supplementalBidBulletinModalOpen,
        selectedProcurement,
        setSelectedRows,
        setLoading,
        setViewType,
        setPreProcurementModalOpen,
        setPreBidConferenceModalOpen,
        setSupplementalBidBulletinModalOpen,
        handleOpenPreProcurementModal,
        handleOpenPreBidModal,
        handleOpenSupplementalBidBulletinModal,
    };
};