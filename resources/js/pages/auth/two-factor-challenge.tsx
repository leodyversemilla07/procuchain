import { Head, useForm } from '@inertiajs/react';
import { Spinner } from '@/components/ui/spinner';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/two-factor/login';

type TwoFactorForm = {
    code?: string;
    recovery_code?: string;
};

export default function TwoFactorChallenge() {
    const { data, setData, post, processing, errors, reset } = useForm<TwoFactorForm>({
        code: '',
        recovery_code: '',
    });
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(store.url(), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    return (
        <AuthLayout
            title="Two-Factor Authentication"
            description="Please confirm access to your account by entering the authentication code provided by your authenticator application"
        >
            <Head title="Two-Factor Authentication" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <Tabs
                    value={useRecoveryCode ? 'recovery' : 'code'}
                    onValueChange={(value) => {
                        setUseRecoveryCode(value === 'recovery');
                        reset();
                    }}
                >
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="code">Authentication Code</TabsTrigger>
                        <TabsTrigger value="recovery">Recovery Code</TabsTrigger>
                    </TabsList>

                    <TabsContent value="code" className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Authentication Code</Label>
                            <Input
                                id="code"
                                type="text"
                                required={!useRecoveryCode}
                                autoFocus={!useRecoveryCode}
                                autoComplete="one-time-code"
                                value={data.code || ''}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="123456"
                                maxLength={6}
                            />
                            <p className="text-muted-foreground text-sm">Please enter the 6-digit code from your authenticator app.</p>
                            <InputError message={errors.code} />
                        </div>
                    </TabsContent>

                    <TabsContent value="recovery" className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="recovery_code">Recovery Code</Label>
                            <Input
                                id="recovery_code"
                                type="text"
                                required={useRecoveryCode}
                                autoFocus={useRecoveryCode}
                                autoComplete="off"
                                value={data.recovery_code || ''}
                                onChange={(e) => setData('recovery_code', e.target.value)}
                                placeholder="xxxxx-xxxxx"
                            />
                            <p className="text-muted-foreground text-sm">Please enter one of your emergency recovery codes.</p>
                            <InputError message={errors.recovery_code} />
                        </div>
                    </TabsContent>
                </Tabs>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <Spinner data-icon="inline-start" />}
                    Verify
                </Button>
            </form>
        </AuthLayout>
    );
}
