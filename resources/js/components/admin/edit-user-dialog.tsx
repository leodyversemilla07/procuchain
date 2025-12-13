import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Edit } from 'lucide-react';
import React from 'react';

interface EditUserDialogProps {
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

export default function EditUserDialog({ open, onOpenChange, formData, setFormData, roles, onSubmit, getRoleDisplayName }: EditUserDialogProps) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        onSubmit(e);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px] [&>button]:hidden">
                <DialogHeader>
                    <DialogTitle>Edit User</DialogTitle>
                    <DialogDescription>Update user information and access permissions.</DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <FieldGroup>
                        <div className="grid grid-cols-2 gap-4">
                            <Field>
                                <FieldLabel htmlFor="edit_name">
                                    Full Name
                                    <span className="text-destructive ml-1">*</span>
                                </FieldLabel>
                                <Input
                                    id="edit_name"
                                    placeholder="Enter full name"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    required
                                />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="edit_email">
                                    Email Address
                                    <span className="text-destructive ml-1">*</span>
                                </FieldLabel>
                                <Input
                                    id="edit_email"
                                    type="email"
                                    placeholder="Enter email address"
                                    value={formData.email}
                                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                    required
                                />
                            </Field>
                        </div>

                        <Field>
                            <FieldLabel htmlFor="edit_role">
                                Role
                                <span className="text-destructive ml-1">*</span>
                            </FieldLabel>
                            <RadioGroup value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })} required className="flex flex-wrap gap-4">
                                {roles.map((role) => (
                                    <div key={role} className="flex items-center gap-2">
                                        <RadioGroupItem value={role} id={`edit_${role}`} />
                                        <Label htmlFor={`edit_${role}`} className="font-normal cursor-pointer">
                                            {getRoleDisplayName(role)}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field>
                                <FieldLabel htmlFor="edit_password" className="text-muted-foreground">
                                    New Password
                                </FieldLabel>
                                <Input
                                    id="edit_password"
                                    type="password"
                                    placeholder="Leave blank to keep current"
                                    value={formData.password}
                                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                    autoComplete="new-password"
                                />
                                <FieldDescription>Leave blank to keep current password</FieldDescription>
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="edit_password_confirmation" className="text-muted-foreground">
                                    Confirm New Password
                                </FieldLabel>
                                <Input
                                    id="edit_password_confirmation"
                                    type="password"
                                    placeholder="Confirm new password"
                                    value={formData.password_confirmation}
                                    onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
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
                            <Edit className="mr-2 h-4 w-4" />
                            Update User
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
