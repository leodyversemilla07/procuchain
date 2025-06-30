import React from "react";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import InputError from "@/components/input-error";
import { format } from "date-fns";
import { Label } from "@/components/ui/label";
import { DateRange } from "react-day-picker";
import { ChevronDownIcon } from "lucide-react";

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
    label = "Date Range",
    value,
    onChange,
    error,
    required = false,
    inputLabelClassName = "",
    buttonClassName = "",
    popoverClassName = "",
}) => (
    <div className="space-y-2">
        {label && (
            <Label className={`flex items-center text-base font-medium ${inputLabelClassName}`}>
                {label}
                {required && <span className="text-destructive ml-1">*</span>}
            </Label>
        )}
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className={`w-full justify-between text-left font-normal h-10 ${buttonClassName}`}
                >
                    {value?.from ? (
                        value.to ? (
                            <>
                                {format(value.from, "LLL dd, y")} - {format(value.to, "LLL dd, y")}
                            </>
                        ) : (
                            format(value.from, "LLL dd, y")
                        )
                    ) : (
                        <span>Pick a date range</span>
                    )}
                    <ChevronDownIcon className="ml-2 h-4 w-4 text-muted-foreground" />
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
