import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { format } from 'date-fns';
import { ChevronDownIcon } from 'lucide-react';
import React from 'react';

interface DatePickerProps {
    label?: string;
    value: Date | string | undefined;
    onChange: (date: string) => void;
    error?: string;
    inputLabelClassName?: string;
    buttonClassName?: string;
    popoverClassName?: string;
    required?: boolean;
    id?: string;
    disabled?: boolean;
    placeholder?: string;
}

const DatePicker: React.FC<DatePickerProps> = ({
    label = 'Date',
    value,
    onChange,
    error,
    inputLabelClassName = '',
    buttonClassName = '',
    popoverClassName = '',
    required = false,
    id,
    disabled = false,
    placeholder = 'Pick a date',
}) => {
    const generatedId = React.useId();
    const datePickerId = id ?? generatedId;

    // Convert string to Date if needed
    const dateValue = value ? (typeof value === 'string' ? new Date(value) : value) : undefined;

    // Handle date selection
    const handleDateChange = (date: Date | undefined) => {
        if (date) {
            onChange(format(date, 'yyyy-MM-dd'));
        } else {
            onChange('');
        }
    };

    return (
        <Field>
            {label && (
                <FieldLabel htmlFor={datePickerId} className={inputLabelClassName}>
                    {label}
                    {required && (
                        <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                            *
                        </span>
                    )}
                </FieldLabel>
            )}
            <Popover>
                <PopoverTrigger
                    render={
                        <Button
                            id={datePickerId}
                            variant="outline"
                            disabled={disabled}
                            className={`h-9 w-full justify-between px-3 py-2 text-left font-normal ${buttonClassName}`}
                        />
                    }
                >
                    {dateValue ? format(dateValue, 'PPP') : <span>{placeholder}</span>}
                    <ChevronDownIcon className="text-muted-foreground ml-2 h-4 w-4" />
                </PopoverTrigger>
                <PopoverContent className={`w-auto p-0 ${popoverClassName}`} align="start">
                    <Calendar mode="single" selected={dateValue} onSelect={handleDateChange} className="rounded-md border shadow-md" captionLayout="dropdown" />
                </PopoverContent>
            </Popover>
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
};

export default DatePicker;
export { DatePicker as DatePickerInput };
