import React from "react";
import { ChevronDownIcon } from "lucide-react";
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
                <Button
                    variant="outline"
                    className={`w-full justify-between text-left font-normal h-9 px-3 py-2 ${buttonClassName}`}
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
