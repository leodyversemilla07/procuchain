import { ProcurementListItem } from '@/types';
import { useEffect, useState } from 'react';

interface UseProcurementListProps {
    initialProcurements: ProcurementListItem[];
    initialError?: string;
}

export const useProcurementList = ({ initialProcurements, initialError }: UseProcurementListProps) => {
    const [procurements, setProcurements] = useState<ProcurementListItem[]>(initialProcurements || []);
    const [selectedRows, setSelectedRows] = useState<ProcurementListItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | undefined>(initialError);
    const [preProcurementDialogOpen, setPreProcurementDialogOpen] = useState(false);
    const [preBidConferenceDialogOpen, setPreBidConferenceDialogOpen] = useState(false);
    const [supplementalBidBulletinDialogOpen, setSupplementalBidBulletinDialogOpen] = useState(false);
    const [selectedProcurement, setSelectedProcurement] = useState<{
        id: string;
        title: string;
    }>({ id: '', title: '' });

    useEffect(() => {
        if (initialError) {
            console.error('Backend error:', initialError);
            setError(initialError);
        }
    }, [initialError]);

    useEffect(() => {
        setProcurements(initialProcurements || []);
    }, [initialProcurements]);

    const handleOpenPreProcurementDialog = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setPreProcurementDialogOpen(true);
    };

    const handleOpenPreBidDialog = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setPreBidConferenceDialogOpen(true);
    };

    const handleOpenSupplementalBidBulletinDialog = (procurement: ProcurementListItem) => {
        setSelectedProcurement({
            id: procurement.id,
            title: procurement.title,
        });
        setSupplementalBidBulletinDialogOpen(true);
    };

    return {
        procurements,
        selectedRows,
        loading,
        error,
        preProcurementDialogOpen,
        preBidConferenceDialogOpen,
        supplementalBidBulletinDialogOpen,
        selectedProcurement,
        setSelectedRows,
        setLoading,
        setPreProcurementDialogOpen,
        setPreBidConferenceDialogOpen,
        setSupplementalBidBulletinDialogOpen,
        handleOpenPreProcurementDialog,
        handleOpenPreBidDialog,
        handleOpenSupplementalBidBulletinDialog,
    };
};
