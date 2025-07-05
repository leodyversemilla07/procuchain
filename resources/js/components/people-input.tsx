import React, { useState, useEffect } from 'react';
import { Users, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

interface PeopleInputProps {
    label?: string;
    value: string[];
    onChange: (people: string[]) => void;
    error?: string;
    required?: boolean;
    placeholder?: string;
    labelClassName?: string;
    errorClassName?: string;
}

const PeopleInput: React.FC<PeopleInputProps> = ({
    label = 'People',
    value,
    onChange,
    error,
    required = false,
    placeholder = 'Type name and press Enter or click Add',
    labelClassName,
    errorClassName,
}) => {
    const [input, setInput] = useState('');
    const [people, setPeople] = useState<string[]>(value || []);

    useEffect(() => {
        setPeople(value || []);
    }, [value]);

    const addPerson = () => {
        const trimmed = input.trim();
        if (trimmed && !people.includes(trimmed)) {
            const updated = [...people, trimmed];
            setPeople(updated);
            onChange(updated);
            setInput('');
        }
    };

    const removePerson = (index: number) => {
        const updated = people.filter((_, i) => i !== index);
        setPeople(updated);
        onChange(updated);
    };

    return (
        <div className="flex flex-col gap-1">
            {label && (
                <Label className={labelClassName}>
                    {label}
                    {required ? (
                        <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                            *
                        </span>
                    ) : null}
                </Label>
            )}
            <div className="space-y-3">
                <div className="flex gap-2">
                    <Input
                        value={input}
                        onChange={e => setInput(e.target.value)}
                        placeholder={placeholder}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                addPerson();
                                e.preventDefault();
                            }
                        }}
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        className="px-3 flex items-center gap-1"
                        onClick={addPerson}
                        disabled={!input.trim()}
                    >
                        <Plus className="h-4 w-4" />
                        Add
                    </Button>
                </div>
                <div className="flex flex-wrap gap-2">
                    {people.map((person, index) => (
                        <Badge
                            key={index}
                            variant="secondary"
                            className="flex items-center gap-1 py-1 px-2 text-xs sm:text-sm"
                        >
                            <Users className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            {person}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-4 w-4 sm:h-5 sm:w-5 hover:bg-destructive/10 hover:text-destructive ml-1 -mr-1"
                                onClick={() => removePerson(index)}
                            >
                                <X className="h-3 w-3" />
                            </Button>
                        </Badge>
                    ))}
                </div>
            </div>
            {error && (
                <InputError message={error} className={errorClassName} />
            )}
        </div>
    );
};

export default PeopleInput;
