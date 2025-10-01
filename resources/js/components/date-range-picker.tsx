import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { format } from 'date-fns';
import { ChevronDownIcon } from 'lucide-react';
import React from 'react';
import { DateRange } from 'react-day-picker';

interface DateRangePickerProps {
    label?: string;
    value: DateRange | undefined;
    onChange: (range: DateRange | undefined) => void;
    error?: string;
    required?: boolean;
    inputLabelClassName?: string;
    buttonClassName?: string;
    popoverClassName?: string;
}

const DateRangePicker: React.FC<DateRangePickerProps> = ({
    label = 'Date Range',
    value,
    onChange,
    error,
    required = false,
    inputLabelClassName = '',
    buttonClassName = '',
    popoverClassName = '',
}) => (
    <div className="flex flex-col gap-1">
        {label && (
            <Label className={inputLabelClassName}>
                {label}
                {required ? (
                    <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                        *
                    </span>
                ) : null}
            </Label>
        )}
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline" className={`h-9 w-full justify-between px-3 py-2 text-left font-normal ${buttonClassName}`}>
                    {value?.from ? (
                        value.to ? (
                            <>
                                {format(value.from, 'LLL dd, y')} - {format(value.to, 'LLL dd, y')}
                            </>
                        ) : (
                            format(value.from, 'LLL dd, y')
                        )
                    ) : (
                        <span>Pick a date range</span>
                    )}
                    <ChevronDownIcon className="text-muted-foreground ml-2 h-4 w-4" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className={`w-auto p-0 ${popoverClassName}`} align="start">
                <Calendar
                    initialFocus
                    mode="range"
                    defaultMonth={value?.from}
                    selected={value}
                    onSelect={onChange}
                    numberOfMonths={2}
                    className="rounded-md border shadow-md"
                    captionLayout="dropdown"
                />
            </PopoverContent>
        </Popover>
        {error && <InputError message={error} />}
    </div>
);

export default DateRangePicker;
