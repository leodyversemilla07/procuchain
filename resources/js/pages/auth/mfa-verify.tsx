import { Head, useForm } from '@inertiajs/react';
import { Shield, Smartphone } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
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

            <Card className="mx-auto w-full max-w-md">
                <CardHeader className="text-center">
                    <div className="bg-primary/10 mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg">
                        <Shield className="text-primary h-6 w-6" />
                    </div>
                    <CardTitle>Two-Factor Authentication</CardTitle>
                    <CardDescription>Enter the verification code from your authenticator app or use a backup code</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        <div className="flex flex-col items-center space-y-2">
                            <Label htmlFor="code">Verification Code</Label>
                            <InputOTP
                                id="code"
                                maxLength={6}
                                value={data.code}
                                onChange={(value: string) => setData('code', value)}
                                disabled={processing}
                                className="mx-auto"
                                autoFocus
                            >
                                <InputOTPGroup>
                                    <InputOTPSlot index={0} />
                                    <InputOTPSlot index={1} />
                                    <InputOTPSlot index={2} />
                                    <InputOTPSlot index={3} />
                                    <InputOTPSlot index={4} />
                                    <InputOTPSlot index={5} />
                                </InputOTPGroup>
                            </InputOTP>
                            <p className="text-muted-foreground text-center text-xs">
                                Enter the 6-digit code from your authenticator app or an 8-character backup code
                            </p>
                            <InputError message={errors.code} />
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Verifying...' : 'Verify Code'}
                        </Button>
                    </form>

                    <div className="mt-6 text-center">
                        <div className="text-muted-foreground flex items-center justify-center space-x-2 text-sm">
                            <Smartphone className="h-4 w-4" />
                            <span>Having trouble? Use a backup code instead</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}
