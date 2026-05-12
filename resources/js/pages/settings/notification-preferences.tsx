import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Bell, BellOff, Loader2, Mail, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification preferences',
        href: '/settings/notification-preferences',
    },
];

interface NotificationPreferencesPageProps {
    email_notifications_enabled: boolean;
    notification_preferences: Record<string, { email: boolean; push: boolean }>;
    categories: Record<string, Record<string, string>>;
    eventTypes: string[];
    channels: string[];
    flash?: {
        message?: string;
        type?: 'success' | 'error' | 'info';
    };
}

const EVENT_TYPE_LABELS: Record<string, string> = {
    procurement_stage_updates: 'Stage Updates',
    procurement_corrections: 'Corrections',
    document_uploads: 'Document Uploads',
    account_security: 'Account Security',
    user_invitations: 'User Invitations',
    system_announcements: 'System Announcements',
};

export default function NotificationPreferences() {
    const { props } = usePage<{ data: NotificationPreferencesPageProps }>();
    const data = props.data ?? props;

    const [emailEnabled, setEmailEnabled] = useState(data.email_notifications_enabled ?? true);
    const [preferences, setPreferences] = useState<Record<string, { email: boolean; push: boolean }>>(data.notification_preferences ?? {});
    const [isSaving, setIsSaving] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);

    const categories = (data.categories ?? {}) as Record<string, Record<string, string>>;

    useEffect(() => {
        if (data.flash?.message) {
            if (data.flash.type === 'success') {
                toast.success(data.flash.message);
            } else if (data.flash.type === 'error') {
                toast.error(data.flash.message);
            } else {
                toast(data.flash.message);
            }
        }
    }, [data.flash]);

    const handleToggleEmail = (value: boolean) => {
        setEmailEnabled(value);
        setHasChanges(true);
    };

    const handleTogglePreference = (eventType: string, channel: 'email' | 'push', value: boolean) => {
        setPreferences((prev) => ({
            ...prev,
            [eventType]: {
                ...prev[eventType],
                [channel]: value,
            },
        }));
        setHasChanges(true);
    };

    const handleSave = async () => {
        setIsSaving(true);
        try {
            router.patch(
                '/settings/notification-preferences',
                {
                    email_notifications_enabled: emailEnabled,
                    notification_preferences: preferences,
                },
                {
                    onSuccess: () => {
                        setHasChanges(false);
                        toast.success('Notification preferences saved');
                    },
                    onError: (errors) => {
                        const messages = Object.values(errors).flat().join(', ');
                        toast.error(messages || 'Failed to save preferences');
                    },
                    onFinish: () => setIsSaving(false),
                    preserveState: true,
                    preserveScroll: true,
                },
            );
        } catch {
            toast.error('Failed to save preferences');
            setIsSaving(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification preferences" />
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Notification preferences"
                        description="Choose which notifications you'd like to receive and through which channels"
                    />

                    {/* Master toggle */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                {emailEnabled ? <Bell className="h-4 w-4" /> : <BellOff className="h-4 w-4" />}
                                Email Notifications
                            </CardTitle>
                            <CardDescription>
                                Master switch for all email notifications. Push notifications are managed per event type below.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label htmlFor="master-email" className="text-sm font-medium">
                                        Receive email notifications
                                    </Label>
                                    <p className="text-muted-foreground text-xs">
                                        When disabled, you won't receive any emails. Individual toggles below still control push notifications.
                                    </p>
                                </div>
                                <Switch id="master-email" checked={emailEnabled} onCheckedChange={handleToggleEmail} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Per-category toggles */}
                    {Object.entries(categories).map(([category, events]) => (
                        <Card key={category}>
                            <CardHeader>
                                <CardTitle className="text-base">{category}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {Object.entries(events).map(([eventType, description]) => {
                                    const pref = preferences[eventType] ?? { email: false, push: false };
                                    const isEmailDisabled = !emailEnabled;

                                    return (
                                        <div key={eventType}>
                                            <div className="mb-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                <div className="space-y-1 sm:col-span-1">
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="secondary" className="text-xs font-normal">
                                                            {EVENT_TYPE_LABELS[eventType] ?? eventType}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-muted-foreground text-xs leading-relaxed">{description}</p>
                                                </div>

                                                <div className="flex items-center gap-6 sm:col-span-2">
                                                    <div className="flex items-center gap-2">
                                                        <Mail className="text-muted-foreground h-4 w-4" />
                                                        <Switch
                                                            id={`${eventType}-email`}
                                                            checked={pref.email}
                                                            disabled={isEmailDisabled}
                                                            onCheckedChange={(value) => handleTogglePreference(eventType, 'email', value)}
                                                        />
                                                        <Label htmlFor={`${eventType}-email`} className="text-xs">
                                                            Email
                                                        </Label>
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        <Smartphone className="text-muted-foreground h-4 w-4" />
                                                        <Switch
                                                            id={`${eventType}-push`}
                                                            checked={pref.push}
                                                            onCheckedChange={(value) => handleTogglePreference(eventType, 'push', value)}
                                                        />
                                                        <Label htmlFor={`${eventType}-push`} className="text-xs">
                                                            Push
                                                        </Label>
                                                    </div>
                                                </div>
                                            </div>
                                            <Separator />
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    ))}

                    {/* Save button */}
                    <div className="flex items-center justify-end gap-4 pt-2">
                        {hasChanges && <span className="text-muted-foreground text-xs">You have unsaved changes</span>}
                        <Button onClick={handleSave} disabled={isSaving || !hasChanges}>
                            {isSaving ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                'Save preferences'
                            )}
                        </Button>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
