import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import * as React from 'react';

interface TextareaWithLabelProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    label: string;
    labelClassName?: string;
    id?: string;
    required?: boolean;
    error?: string;
    errorClassName?: string;
}

const TextareaWithLabel = React.forwardRef<HTMLTextAreaElement, TextareaWithLabelProps>(
    (
        { label, labelClassName, id, required, error, errorClassName, ...props }: TextareaWithLabelProps,
        ref: React.ForwardedRef<HTMLTextAreaElement>,
    ) => {
        const generatedId = React.useId();
        const textareaId = id ?? generatedId;
        return (
            <div className="flex flex-col gap-1">
                <Label htmlFor={textareaId} className={labelClassName}>
                    {label}
                    {required ? (
                        <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                            *
                        </span>
                    ) : null}
                </Label>
                <textarea ref={ref} id={textareaId} required={required} {...props} className="rounded-md border p-2" />
                {error && <InputError message={error} className={errorClassName} />}
            </div>
        );
    },
);

TextareaWithLabel.displayName = 'TextareaWithLabel';

export { TextareaWithLabel };
