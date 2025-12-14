import { Head } from '@inertiajs/react';

import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout title="Verify email" description="Please verify your email address by clicking on the link we just emailed to you.">
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address you provided during registration.
                </div>
            )}

            <div className="space-y-6 text-center">
                <p className="text-sm text-neutral-600">Email verification is currently disabled.</p>

                <TextLink href={logout.url()} method="post" className="mx-auto block text-sm">
                    Log out
                </TextLink>
            </div>
        </AuthLayout>
    );
}
