import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import React from 'react';

interface ProcurementIdFieldProps {
    prNumber: string;
    serial1: string;
    onSerial1Change: (val: string) => void;
    serial2: string;
    onSerial2Change: (val: string) => void;
    error?: string;
    className?: string;
    required?: boolean;
    description?: string;
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
    description,
}) => {
    return (
        <Field className={className}>
            <FieldLabel htmlFor="pr-number">
                Procurement ID
                {required && (
                    <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                        *
                    </span>
                )}
            </FieldLabel>
            <div className="grid w-full grid-cols-1 gap-2 sm:grid-cols-4">
                <Input
                    id="pr-number"
                    type="text"
                    value={prNumber || 'PR'}
                    readOnly
                    className="border-border text-foreground w-full text-center"
                    required={required}
                />
                <Input
                    id="pr-year"
                    type="text"
                    value={String(currentYear)}
                    readOnly
                    className="border-border text-foreground w-full text-center"
                    required={required}
                />
                <Input
                    id="pr-serial1"
                    type="text"
                    value={serial1}
                    onChange={(e) => onSerial1Change(e.target.value.replace(/\D/g, '').slice(0, 4))}
                    className="border-border text-foreground w-full text-center"
                    maxLength={4}
                    placeholder="0001"
                    required={required}
                />
                <Input
                    id="pr-serial2"
                    type="text"
                    value={serial2}
                    onChange={(e) => onSerial2Change(e.target.value.replace(/\D/g, '').slice(0, 4))}
                    className="border-border text-foreground w-full text-center"
                    maxLength={4}
                    placeholder="0001"
                    required={required}
                />
            </div>
            {description && <FieldDescription>{description}</FieldDescription>}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
};

export default ProcurementId;
