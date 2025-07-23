
import React from 'react';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
// ...existing code...
import InputError from '@/components/input-error';

interface ProcurementIdFieldProps {
    prNumber: string;
    serial1: string;
    onSerial1Change: (val: string) => void;
    serial2: string;
    onSerial2Change: (val: string) => void;
    error?: string;
    className?: string;
    required?: boolean;
}

const currentYear = new Date().getFullYear();

export const ProcurementId: React.FC<ProcurementIdFieldProps> = ({
    prNumber = 'PR',
    serial1,
    onSerial1Change,
    serial2,
    onSerial2Change,
    error,
    className = '',
    required = false,
}) => {
    // Match InputWithLabel's label design: label + required star, flex-col gap-1
    return (
        <div className={`flex flex-col gap-1 ${className}`}>
            <Label htmlFor="pr-number" className="">
                Procurement ID
                {required ? (
                    <span className="text-destructive ml-1 align-super text-xs" aria-label="required">*</span>
                ) : null}
            </Label>
            <div className="flex flex-col gap-2 w-full">
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 w-full">
                    <Input
                        id="pr-number"
                        type="text"
                        value={prNumber || 'PR'}
                        readOnly
                        className="text-center border-border text-foreground w-full"
                        required={required}
                    />
                    <Input
                        id="pr-year"
                        type="text"
                        value={String(currentYear)}
                        readOnly
                        className="text-center border-border text-foreground w-full"
                        required={required}
                    />
                    <Input
                        id="pr-serial1"
                        type="text"
                        value={serial1}
                        onChange={e => onSerial1Change(e.target.value.replace(/\D/g, '').slice(0, 4))}
                        className="text-center border-border text-foreground w-full"
                        maxLength={4}
                        placeholder="0001"
                        required={required}
                    />
                    <Input
                        id="pr-serial2"
                        type="text"
                        value={serial2}
                        onChange={e => onSerial2Change(e.target.value.replace(/\D/g, '').slice(0, 4))}
                        className="text-center border-border text-foreground w-full"
                        maxLength={4}
                        placeholder="0001"
                        required={required}
                    />
                </div>
                {error && <InputError message={error} className="ml-2" />}
            </div>
        </div>
    );
};

export default ProcurementId;
