import React from "react";
import { ChevronDownIcon, CalendarIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import InputError from "@/components/input-error";
import { format } from "date-fns";
import { Label } from "@/components/ui/label";

interface DatePickerProps {
    label?: string;
    value: Date | undefined;
    onChange: (date: Date | undefined) => void;
    error?: string;
    inputLabelClassName?: string;
    buttonClassName?: string;
    popoverClassName?: string;
    required?: boolean;
}

const DatePicker: React.FC<DatePickerProps> = ({
    label = "Date",
    value,
    onChange,
    error,
    inputLabelClassName = "",
    buttonClassName = "",
    popoverClassName = "",
    required = false,
}) => (
    <div className="space-y-2">
        {label && (
            <Label className={`flex items-center text-base font-medium ${inputLabelClassName}`}>
                <CalendarIcon className="h-4 w-4 mr-2" />
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
                    {value ? format(value, "PPP") : <span>Pick a date</span>}
                    <ChevronDownIcon className="ml-2 h-4 w-4 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className={`w-auto p-0 ${popoverClassName}`} align="start">
                <Calendar
                    mode="single"
                    selected={value}
                    onSelect={onChange}
                    className="rounded-md border shadow-md"
                    captionLayout="dropdown"
                />
            </PopoverContent>
        </Popover>
        {error && <InputError message={error} />}
    </div>
);

export default DatePicker;
