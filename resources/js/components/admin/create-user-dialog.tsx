import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Plus } from 'lucide-react';
import React from 'react';

interface CreateUserDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    formData: {
        name: string;
        email: string;
        role: string;
        password: string;
        password_confirmation: string;
    };
    setFormData: React.Dispatch<
        React.SetStateAction<{
            name: string;
            email: string;
            role: string;
            password: string;
            password_confirmation: string;
        }>
    >;
    roles: string[];
    onSubmit: (e: React.FormEvent) => void;
    getRoleDisplayName: (role: string) => string;
}

export default function CreateUserDialog({ open, onOpenChange, formData, setFormData, roles, onSubmit, getRoleDisplayName }: CreateUserDialogProps) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        onSubmit(e);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px] [&>button]:hidden">
                <DialogHeader>
                    <DialogTitle>Create New User</DialogTitle>
                    <DialogDescription>Add a new user to the system with their basic information and access credentials.</DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <FieldGroup>
                        <div className="grid grid-cols-2 gap-4">
                            <Field>
                                <FieldLabel htmlFor="name">
                                    Full Name
                                    <span className="text-destructive ml-1">*</span>
                                </FieldLabel>
                                <Input
                                    id="name"
                                    placeholder="Enter full name"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    required
                                />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="email">
                                    Email Address
                                    <span className="text-destructive ml-1">*</span>
                                </FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="Enter email address"
                                    value={formData.email}
                                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                    required
                                />
                            </Field>
                        </div>

                        <Field>
                            <FieldLabel htmlFor="role">
                                Role
                                <span className="text-destructive ml-1">*</span>
                            </FieldLabel>
                            <RadioGroup
                                value={formData.role}
                                onValueChange={(value) => setFormData({ ...formData, role: value })}
                                required
                                className="flex flex-wrap gap-4"
                            >
                                {roles.map((role) => (
                                    <div key={role} className="flex items-center gap-2">
                                        <RadioGroupItem value={role} id={role} />
                                        <Label htmlFor={role} className="cursor-pointer font-normal">
                                            {getRoleDisplayName(role)}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field>
                                <FieldLabel htmlFor="password">
                                    Password
                                    <span className="text-destructive ml-1">*</span>
                                </FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="Enter secure password"
                                    value={formData.password}
                                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                    required
                                    autoComplete="new-password"
                                />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password_confirmation">
                                    Confirm Password
                                    <span className="text-destructive ml-1">*</span>
                                </FieldLabel>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    placeholder="Confirm password"
                                    value={formData.password_confirmation}
                                    onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                                    required
                                    autoComplete="new-password"
                                />
                            </Field>
                        </div>
                    </FieldGroup>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit">
                            <Plus />
                            Create User
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
