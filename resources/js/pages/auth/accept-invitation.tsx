import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Clock, Loader2, Mail, Shield, User } from 'lucide-react';

interface Invitation {
    email: string;
    name: string;
    role: string;
    role_display: string;
    invited_by: string;
    expires_at: string;
    expires_at_human: string;
}

interface PageProps {
    invitation: Invitation;
    token: string;
}

export default function AcceptInvitation({ invitation, token }: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: invitation.name || '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/invitation/${token}/accept`);
    };

    return (
        <AuthLayout title="Accept Invitation" description="Join the Procuchain Procurement System">
            <Head title="Accept Invitation" />

            <div className="w-full max-w-2xl space-y-6">
                {/* Header */}
                <div className="text-center">
                    <div className="bg-primary/10 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                        <Mail className="text-primary h-8 w-8" />
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight">You're Invited!</h1>
                    <p className="text-muted-foreground mt-2">Join the Procuchain Procurement System</p>
                </div>

                {/* Invitation Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Invitation Details</CardTitle>
                        <CardDescription>
                            You've been invited by {invitation.invited_by} to join as a {invitation.role_display}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4">
                            <div className="flex items-center gap-3 rounded-lg border p-3">
                                <Mail className="text-muted-foreground h-5 w-5" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Email</p>
                                    <p className="text-muted-foreground text-sm">{invitation.email}</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 rounded-lg border p-3">
                                <Shield className="text-muted-foreground h-5 w-5" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Role</p>
                                    <Badge variant="outline" className="mt-1">
                                        {invitation.role_display}
                                    </Badge>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 rounded-lg border p-3">
                                <User className="text-muted-foreground h-5 w-5" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Invited By</p>
                                    <p className="text-muted-foreground text-sm">{invitation.invited_by}</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/20">
                                <Clock className="h-5 w-5 text-amber-600 dark:text-amber-500" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-amber-900 dark:text-amber-100">Expires {invitation.expires_at_human}</p>
                                    <p className="text-xs text-amber-700 dark:text-amber-300">{invitation.expires_at}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Account Creation Form */}
                <Card>
                    <CardHeader>
                        <CardTitle>Create Your Account</CardTitle>
                        <CardDescription>Set your name and create a secure password to complete your registration</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit}>
                            <div className="space-y-4">
                                <Field data-invalid={!!errors.name}>
                                    <FieldLabel htmlFor="name">Full Name</FieldLabel>
                                    <Input
                                        id="name"
                                        type="text"
                                        placeholder={invitation.name}
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        disabled={processing}
                                        className={errors.name ? 'border-destructive' : ''}
                                        required
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>

                                <Field data-invalid={!!errors.password}>
                                    <FieldLabel htmlFor="password">Password</FieldLabel>
                                    <Input
                                        id="password"
                                        type="password"
                                        placeholder="Create a strong password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        disabled={processing}
                                        className={errors.password ? 'border-destructive' : ''}
                                        required
                                    />
                                    <FieldDescription>Must be at least 8 characters with a mix of letters, numbers, and symbols</FieldDescription>
                                    <FieldError>{errors.password}</FieldError>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="password_confirmation">Confirm Password</FieldLabel>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        placeholder="Re-enter your password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        disabled={processing}
                                        required
                                    />
                                </Field>

                                <Alert className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/20">
                                    <CheckCircle2 className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    <AlertTitle className="text-blue-900 dark:text-blue-100">What happens next?</AlertTitle>
                                    <AlertDescription className="space-y-1 text-blue-700 dark:text-blue-300">
                                        <ul className="space-y-1">
                                            <li>• Your email will be automatically verified</li>
                                            <li>• A blockchain address will be generated for you</li>
                                            <li>• You'll be logged in and redirected to your dashboard</li>
                                        </ul>
                                    </AlertDescription>
                                </Alert>

                                <Button type="submit" className="w-full" disabled={processing}>
                                    {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Accept Invitation & Create Account
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <p className="text-muted-foreground text-center text-sm">Having trouble? Contact the system administrator for help.</p>
            </div>
        </AuthLayout>
    );
}
