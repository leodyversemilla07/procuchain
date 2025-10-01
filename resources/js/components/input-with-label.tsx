import InputError from '@/components/input-error';
import * as React from 'react';
import { Input } from './ui/input';
import { Label } from './ui/label';

interface InputWithLabelProps extends React.ComponentProps<typeof Input> {
    label: string;
    labelClassName?: string;
    id?: string;
    required?: boolean;
    error?: string;
    errorClassName?: string;
}

const InputWithLabel = React.forwardRef<HTMLInputElement, InputWithLabelProps>(
    ({ label, labelClassName, id, required, error, errorClassName, ...props }: InputWithLabelProps, ref: React.ForwardedRef<HTMLInputElement>) => {
        const generatedId = React.useId();
        const inputId = id ?? generatedId;
        return (
            <div className="flex flex-col gap-1">
                <Label htmlFor={inputId} className={labelClassName}>
                    {label}
                    {required ? (
                        <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                            *
                        </span>
                    ) : null}
                </Label>
                <Input ref={ref} id={inputId} required={required} {...props} />
                {error && <InputError message={error} className={errorClassName} />}
            </div>
        );
    },
);

InputWithLabel.displayName = 'InputWithLabel';

export { InputWithLabel };
