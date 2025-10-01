import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Users, X } from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface PersonData {
    name: string;
    affiliation: string;
}

type AffiliationType = 'position' | 'organization';

interface PeopleInputProps {
    label?: string;
    value: PersonData[];
    onChange: (people: PersonData[]) => void;
    error?: string;
    required?: boolean;
    namePlaceholder?: string;
    affiliationType: AffiliationType;
    labelClassName?: string;
    errorClassName?: string;
}

const PeopleInput: React.FC<PeopleInputProps> = ({
    label = 'People',
    value,
    onChange,
    error,
    required = false,
    namePlaceholder = 'Enter name',
    affiliationType = 'position',
    labelClassName,
    errorClassName,
}) => {
    const [nameInput, setNameInput] = useState('');
    const [affiliationInput, setAffiliationInput] = useState('');
    const [people, setPeople] = useState<PersonData[]>(value || []);

    const getAffiliationPlaceholder = () => {
        return affiliationType === 'position' ? 'Enter position' : 'Enter organization';
    };

    useEffect(() => {
        setPeople(value || []);
    }, [value]);

    const addPerson = () => {
        const trimmedName = nameInput.trim();
        const trimmedAffiliation = affiliationInput.trim();
        if (trimmedName && trimmedAffiliation && !people.some((p) => p.name === trimmedName && p.affiliation === trimmedAffiliation)) {
            const updated = [...people, { name: trimmedName, affiliation: trimmedAffiliation }];
            setPeople(updated);
            onChange(updated);
            setNameInput('');
            setAffiliationInput('');
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
                    <div className="flex flex-1 gap-2">
                        <Input
                            value={nameInput}
                            onChange={(e) => setNameInput(e.target.value)}
                            placeholder={namePlaceholder}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && affiliationInput) {
                                    addPerson();
                                    e.preventDefault();
                                }
                            }}
                        />
                        <Input
                            value={affiliationInput}
                            onChange={(e) => setAffiliationInput(e.target.value)}
                            placeholder={getAffiliationPlaceholder()}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && nameInput) {
                                    addPerson();
                                    e.preventDefault();
                                }
                            }}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        className="flex items-center gap-1 px-3 whitespace-nowrap"
                        onClick={addPerson}
                        disabled={!nameInput.trim() || !affiliationInput.trim()}
                    >
                        <Plus className="h-4 w-4" />
                        Add
                    </Button>
                </div>
                <div className="flex flex-wrap gap-2">
                    {people.map((person, index) => (
                        <Badge key={index} variant="secondary" className="flex items-center gap-1 px-2 py-1 text-xs sm:text-sm">
                            <Users className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            {person.name} - {person.affiliation}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="hover:bg-destructive/10 hover:text-destructive -mr-1 ml-1 h-4 w-4 sm:h-5 sm:w-5"
                                onClick={() => removePerson(index)}
                            >
                                <X className="h-3 w-3" />
                            </Button>
                        </Badge>
                    ))}
                </div>
            </div>
            {error && <InputError message={error} className={errorClassName} />}
        </div>
    );
};

export default PeopleInput;
