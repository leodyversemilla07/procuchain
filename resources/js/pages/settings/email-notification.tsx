import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
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
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Email Notifications</h2>
                        <p className="text-muted-foreground">Manage your email notification preferences.</p>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className={`h-5 w-5 ${emailNotificationsEnabled ? '' : 'opacity-50'}`} />
                                Email Notifications
                            </CardTitle>
                            <CardDescription>Receive email notifications for important updates and activities.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label htmlFor="email-notifications" className="text-base">
                                        Enable email notifications
                                    </Label>
                                    <p className="text-muted-foreground text-sm">
                                        You'll receive emails about procurement updates, account changes, and other important notifications.
                                    </p>
                                </div>
                                <Switch
                                    id="email-notifications"
                                    checked={emailNotificationsEnabled}
                                    onCheckedChange={handleToggle}
                                    disabled={isLoading}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
