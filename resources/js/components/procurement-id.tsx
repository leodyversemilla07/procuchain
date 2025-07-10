
import React from 'react';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from "@/components/ui/select";
import InputError from '@/components/input-error';

interface ProcurementIdFieldProps {
    prNumber: string;
    year: string;
    onYearChange: (year: string) => void;
    serial1: string;
    onSerial1Change: (val: string) => void;
    serial2: string;
    onSerial2Change: (val: string) => void;
    years?: string[];
    error?: string;
    className?: string;
    required?: boolean;
}

const currentYear = new Date().getFullYear();
const defaultYears = Array.from({ length: 6 }, (_, i) => String(currentYear - 2 + i));

export const ProcurementId: React.FC<ProcurementIdFieldProps> = ({
    prNumber = 'PR',
    year,
    onYearChange,
    serial1,
    onSerial1Change,
    serial2,
    onSerial2Change,
    years = defaultYears,
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
                        className="bg-muted text-muted-foreground border-border text-center w-full"
                        required={required}
                    />
                    <Select value={year} onValueChange={onYearChange}>
                        <SelectTrigger className="text-center border-border text-foreground w-full" id="pr-year">
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            {years.map(y => (
                                <SelectItem key={y} value={y}>{y}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
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
