import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { useForm } from '@inertiajs/react';

interface SendInvitationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: string[];
}

export default function SendInvitationDialog({ open, onOpenChange, roles }: SendInvitationDialogProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: '',
    });

    const formatRoleName = (role: string): string => {
        const roleMap: Record<string, string> = {
            bac_secretariat: 'BAC Secretariat',
            bac_chairman: 'BAC Chairman',
            hope: 'HoPE',
            admin: 'Admin',
        };

        return (
            roleMap[role] ||
            role
                .split('_')
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ')
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/invitations', {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Send User Invitation</DialogTitle>
                        <DialogDescription>
                            Send an email invitation to a new user. They'll receive a link to create their account and set their own password.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <Field data-invalid={!!errors.name}>
                            <FieldLabel htmlFor="name">Full Name</FieldLabel>
                            <Input
                                id="name"
                                placeholder="John Doe"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                disabled={processing}
                                className={errors.name ? 'border-destructive' : ''}
                            />
                            <FieldError>{errors.name}</FieldError>
                        </Field>

                        <Field data-invalid={!!errors.email}>
                            <FieldLabel htmlFor="email">Email Address</FieldLabel>
                            <Input
                                id="email"
                                type="email"
                                placeholder="john.doe@example.com"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                disabled={processing}
                                className={errors.email ? 'border-destructive' : ''}
                            />
                            <FieldError>{errors.email}</FieldError>
                        </Field>

                        <Field data-invalid={!!errors.role}>
                            <FieldLabel>Role</FieldLabel>
                            <RadioGroup
                                value={data.role}
                                onValueChange={(value) => setData('role', value)}
                                disabled={processing}
                                className="flex flex-row gap-4"
                            >
                                {roles.map((role) => (
                                    <div key={role} className="flex items-center space-x-2">
                                        <RadioGroupItem value={role} id={`role-${role}`} aria-invalid={!!errors.role} />
                                        <Label htmlFor={`role-${role}`} className="cursor-pointer font-normal">
                                            {formatRoleName(role)}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                            <FieldError>{errors.role}</FieldError>
                        </Field>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner data-icon="inline-start" />}
                            Send Invitation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
