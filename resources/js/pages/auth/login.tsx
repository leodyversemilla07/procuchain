import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { getDeviceInfo } from '@/lib/device-detection';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
            <Head title="Log in">
                <meta name="description" content="Log in to ProcuChain to access your blockchain-powered procurement document management system." />
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <Form
                action={store()}
                className="flex flex-col gap-6"
                transform={(data) => ({
                    ...data,
                    device_info: JSON.stringify(getDeviceInfo()),
                })}
            >
                {({ errors, processing }) => (
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="email">Email address</FieldLabel>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="email@example.com"
                            />
                            <FieldError>{errors.email}</FieldError>
                        </Field>

                        <Field>
                            <div className="flex items-center">
                                <FieldLabel htmlFor="password">Password</FieldLabel>
                                {canResetPassword && (
                                    <TextLink href={request.url()} className="ml-auto text-sm" tabIndex={5}>
                                        Forgot password?
                                    </TextLink>
                                )}
                            </div>
                            <div className="relative">
                                <Input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="text-muted-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                    tabIndex={3}
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                            </div>
                            <FieldError>{errors.password}</FieldError>
                        </Field>

                        <Field orientation="horizontal">
                            <Checkbox id="remember" name="remember" tabIndex={4} />
                            <FieldLabel htmlFor="remember">Remember me</FieldLabel>
                        </Field>

                        <Button type="submit" className="mt-4 w-full" tabIndex={5} disabled={processing}>
                            {processing && <Spinner />}
                            Log in
                        </Button>
                    </FieldGroup>
                )}
            </Form>

            {status && (
                <div className={`mb-4 text-center text-sm font-medium ${status.includes('account_locked') ? 'text-destructive' : 'text-primary'}`}>
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
