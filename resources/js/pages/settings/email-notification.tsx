import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Email notification settings',
        href: '/settings/email-notification',
    },
];

export default function EmailNotification() {
    const { props } = usePage<{
        flash?: {
            message?: string;
            type?: 'success' | 'error' | 'info';
        };
        emailNotificationsEnabled: boolean;
    }>();

    const [emailNotificationsEnabled, setEmailNotificationsEnabled] = useState(props.emailNotificationsEnabled);
    const [isLoading, setIsLoading] = useState(false);

    const handleToggle = async (enabled: boolean) => {
        setIsLoading(true);
        try {
            await router.patch(
                '/settings/email-notification',
                {
                    email_notifications_enabled: enabled,
                },
                {
                    onSuccess: () => {
                        setEmailNotificationsEnabled(enabled);
                        toast.success(enabled ? 'Email notifications enabled' : 'Email notifications disabled');
                    },
                    onError: () => {
                        toast.error('Failed to update email notification settings');
                    },
                    onFinish: () => setIsLoading(false),
                },
            );
        } catch {
            toast.error('Failed to update email notification settings');
            setIsLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Email notification settings" />
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Email notifications" description="Manage your email notification preferences" />

                    <div className="flex flex-col items-start justify-start space-y-4">
                        <Badge variant={emailNotificationsEnabled ? 'default' : 'destructive'}>
                            {emailNotificationsEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>

                        <p className="text-muted-foreground">
                            {emailNotificationsEnabled
                                ? "You'll receive emails about procurement updates, account changes, and other important notifications."
                                : 'Email notifications are currently disabled. Enable them to receive updates about procurement activities and important account changes.'}
                        </p>

                        <div className="flex w-full items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <Label htmlFor="email-notifications" className="text-base">
                                    Enable email notifications
                                </Label>
                                <p className="text-muted-foreground text-sm">
                                    Receive notifications for procurement updates, document changes, and system alerts
                                </p>
                            </div>
                            <Switch
                                id="email-notifications"
                                checked={emailNotificationsEnabled}
                                onCheckedChange={handleToggle}
                                disabled={isLoading}
                            />
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
