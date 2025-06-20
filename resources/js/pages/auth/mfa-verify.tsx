import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Shield, Smartphone } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthLayout from '@/layouts/auth-layout';

export default function MfaVerify() {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('mfa.verify'), {
            onFinish: () => reset('code'),
        });
    };

    return (
        <AuthLayout title="Multi-Factor Authentication" description="Enter your verification code to complete login">
            <Head title="Verify MFA Code" />

            <Card className="w-full max-w-md mx-auto">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <Shield className="h-6 w-6 text-primary" />
                    </div>
                    <CardTitle>Two-Factor Authentication</CardTitle>
                    <CardDescription>
                        Enter the verification code from your authenticator app or use a backup code
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="code">Verification Code</Label>
                            <Input
                                id="code"
                                type="text"
                                placeholder="000000"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="text-center text-lg tracking-widest"
                                maxLength={10}
                                autoFocus
                                autoComplete="one-time-code"
                            />
                            <p className="text-xs text-muted-foreground text-center">
                                Enter the 6-digit code from your authenticator app or an 8-character backup code
                            </p>
                            <InputError message={errors.code} />
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Verifying...' : 'Verify Code'}
                        </Button>
                    </form>

                    <div className="mt-6 text-center">
                        <div className="flex items-center justify-center space-x-2 text-sm text-muted-foreground">
                            <Smartphone className="h-4 w-4" />
                            <span>Having trouble? Use a backup code instead</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}
