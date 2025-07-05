import React from 'react';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { MUNICIPAL_OFFICES } from '@/types/blockchain';
import { cn } from '@/lib/utils';

interface MunicipalOfficeSelectProps {
    id?: string;
    label?: string;
    value: string;
    onValueChange: (value: string) => void;
    error?: string;
    required?: boolean;
    placeholder?: string;
    className?: string;
    labelClassName?: string;
    helperText?: string;
    disabled?: boolean;
}

const MunicipalOfficeSelect: React.FC<MunicipalOfficeSelectProps> = ({
    id,
    label = "Municipal Office",
    value,
    onValueChange,
    error,
    required = false,
    placeholder = "Select municipal office",
    className,
    labelClassName,
    helperText = "Select the municipal office involved in this document.",
    disabled = false,
}) => {
    const generatedId = React.useId();
    const selectId = id ?? generatedId;

    return (
        <div className="flex flex-col gap-1">
            {label && (
                <Label
                    htmlFor={selectId}
                    className={labelClassName}
                >
                    {label}
                    {required ? (
                        <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                            *
                        </span>
                    ) : null}
                </Label>
            )}

            <Select
                value={value}
                onValueChange={onValueChange}
                disabled={disabled}
            >
                <SelectTrigger
                    id={selectId}
                    className={cn(
                        "transition-all duration-200 text-left",
                        error
                            ? 'border-destructive ring-1 ring-destructive/30'
                            : 'border-input',
                        className
                    )}
                >
                    <div className="flex-1 truncate text-left">
                        <SelectValue 
                            placeholder={placeholder}
                        />
                    </div>
                </SelectTrigger>
                <SelectContent>
                    {MUNICIPAL_OFFICES.map((office) => (
                        <SelectItem key={office.value} value={office.value}>
                            {office.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {error && (
                <InputError message={error} className="mt-1" />
            )}

            {helperText && !error && (
                <p className="mt-1 text-xs sm:text-sm text-muted-foreground">
                    {helperText}
                </p>
            )}
        </div>
    );
};

export default MunicipalOfficeSelect;
