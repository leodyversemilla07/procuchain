import React, { useState } from 'react';
import { PreProcurementModal } from '@/components/pre-procurement/pre-procurement-modal';

export default function ProcurementManagementPage() {
    // State to control modal visibility
    const [modalOpen, setModalOpen] = useState(false);

    // Example procurement data
    const procurementId = "proc-123";
    const procurementTitle = "Office Equipment Procurement";

    // Callback function when modal completes
    const handleModalComplete = (skipToPhase?: string, conferenceHeld?: boolean) => {
        console.log("Decision submitted");

        if (conferenceHeld) {
            console.log("Conference was held - redirect to document upload");
            // Navigate to document upload or next step
        } else {
            console.log("Skipping to next phase:", skipToPhase);
            // Navigate to bid invitation phase
        }
    };

    return (
        <div>
            <h1>Procurement Management</h1>

            {/* Button to open the modal */}
            <button
                onClick={() => setModalOpen(true)}
                className="px-4 py-2 bg-blue-600 text-white rounded"
            >
                Start Pre-Procurement
            </button>

            {/* The Pre-Procurement Modal */}
            <PreProcurementModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                procurementId={procurementId}
                procurementTitle={procurementTitle}
                onComplete={handleModalComplete}
            />
        </div>
    );
}