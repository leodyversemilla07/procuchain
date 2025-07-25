import { useState, useEffect } from 'react';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Bell, BellOff, BellRing, AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Push notification settings',
        href: '/settings/push-notification',
    },
];

export default function PushNotification() {
    const { props } = usePage<{
        flash?: {
            message?: string;
            type?: 'success' | 'error' | 'info';
        };
    }>();
    const {
        isSupported,
        permission,
        isSubscribed,
        isLoading,
        error,
        requestPermission,
        subscribe,
        unsubscribe,
        sendTestNotification
    } = usePushNotifications();

    const [showPermissionAlert, setShowPermissionAlert] = useState(false);

    // Handle flash messages from Inertia
    useEffect(() => {
        const flash = props.flash;
        if (flash?.message) {
            if (flash.type === 'success') {
                toast.success(flash.message);
            } else if (flash.type === 'error') {
                toast.error(flash.message);
            } else if (flash.type === 'info') {
                toast.info(flash.message);
            } else {
                toast(flash.message);
            }
        }
    }, [props.flash]);

    useEffect(() => {
        if (isSupported && permission === 'denied') {
            setShowPermissionAlert(true);
        } else {
            setShowPermissionAlert(false);
        }
    }, [isSupported, permission]);

    const handleSubscribe = async () => {
        try {
            const success = await subscribe();
            if (!success) {
                toast.error('Failed to subscribe to push notifications');
            }
        } catch {
            toast.error('Failed to subscribe to push notifications');
        }
    };

    const handleUnsubscribe = async () => {
        try {
            const success = await unsubscribe();
            if (!success) {
                toast.error('Failed to unsubscribe from push notifications');
            }
        } catch {
            toast.error('Failed to unsubscribe from push notifications');
        }
    };

    const handleRequestPermission = async () => {
        try {
            const granted = await requestPermission();
            if (granted) {
                toast.success('Notification permission granted!');
                setShowPermissionAlert(false);
            } else {
                toast.error('Notification permission denied');
            }
        } catch {
            toast.error('Failed to request notification permission');
        }
    };

    const handleTestNotification = async () => {
        try {
            await sendTestNotification();
            // Toast will be shown via flash message from server
        } catch {
            toast.error('Failed to send test notification');
        }
    };

    const getPermissionBadge = () => {
        switch (permission) {
            case 'granted':
                return <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700 hover:bg-green-50">Granted</Badge>;
            case 'denied':
                return <Badge variant="destructive">Denied</Badge>;
            case 'default':
                return <Badge variant="secondary">Not Requested</Badge>;
            default:
                return <Badge variant="outline">Unknown</Badge>;
        }
    };

    const getSubscriptionBadge = () => {
        if (isSubscribed) {
            return (
                <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700 hover:bg-green-50">
                    <CheckCircle2 className="w-3 h-3 mr-1" />
                    Subscribed
                </Badge>
            );
        } else {
            return (
                <Badge variant="secondary">
                    <BellOff className="w-3 h-3 mr-1" />
                    Not Subscribed
                </Badge>
            );
        }
    };

    const renderContent = () => {
        if (!isSupported) {
            return (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <BellOff className="w-5 h-5" />
                            Push Notifications
                        </CardTitle>
                        <CardDescription>
                            Get real-time notifications about procurement updates
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                Push notifications are not supported in your browser. Please use a modern browser like Chrome, Firefox, Safari, or Edge.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>
            );
        }

        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <BellRing className="w-5 h-5" />
                        Push Notifications
                    </CardTitle>
                    <CardDescription>
                        Get real-time notifications about procurement updates and important system events
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">Permission Status:</span>
                            {getPermissionBadge()}
                        </div>
                        
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">Subscription Status:</span>
                            {getSubscriptionBadge()}
                        </div>
                    </div>

                    {showPermissionAlert && (
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>Notification permission is required to receive push notifications.</span>
                                <Button 
                                    size="sm" 
                                    onClick={handleRequestPermission}
                                    disabled={isLoading}
                                >
                                    Enable
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}

                    {error && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    <div className="flex flex-col gap-2">
                        {permission === 'granted' && !isSubscribed && (
                            <Button 
                                onClick={handleSubscribe}
                                disabled={isLoading}
                                className="w-full"
                            >
                                {isLoading ? (
                                    <>
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                        Subscribing...
                                    </>
                                ) : (
                                    <>
                                        <Bell className="w-4 h-4 mr-2" />
                                        Enable Push Notifications
                                    </>
                                )}
                            </Button>
                        )}

                        {isSubscribed && (
                            <div className="flex gap-2">
                                <Button 
                                    variant="outline"
                                    onClick={handleUnsubscribe}
                                    disabled={isLoading}
                                    className="flex-1"
                                >
                                    {isLoading ? (
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                    ) : (
                                        <BellOff className="w-4 h-4 mr-2" />
                                    )}
                                    Disable
                                </Button>
                                
                                <Button 
                                    variant="secondary"
                                    onClick={handleTestNotification}
                                    disabled={isLoading}
                                    className="flex-1"
                                >
                                    <Bell className="w-4 h-4 mr-2" />
                                    Test Server
                                </Button>

                                <Button 
                                    variant="outline"
                                    onClick={() => {
                                        if (Notification.permission === 'granted') {
                                            new Notification('Direct Browser Test', {
                                                body: 'This is a direct browser notification test',
                                                icon: '/favicon.ico',
                                                requireInteraction: true
                                            });
                                            toast.success('Direct notification sent!');
                                        } else {
                                            toast.error('Notification permission not granted');
                                        }
                                    }}
                                    disabled={isLoading}
                                    className="flex-1"
                                >
                                    <Bell className="w-4 h-4 mr-2" />
                                    Test Direct
                                </Button>
                            </div>
                        )}

                        {permission === 'default' && (
                            <Button 
                                onClick={handleRequestPermission}
                                disabled={isLoading}
                                className="w-full"
                            >
                                {isLoading ? (
                                    <>
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                        Requesting...
                                    </>
                                ) : (
                                    <>
                                        <Bell className="w-4 h-4 mr-2" />
                                        Request Permission
                                    </>
                                )}
                            </Button>
                        )}
                    </div>

                    <div className="bg-muted/50 rounded-lg p-3">
                        <h4 className="text-sm font-medium mb-2">What you'll receive notifications for:</h4>
                        <ul className="text-xs text-muted-foreground space-y-1">
                            <li>• Procurement stage updates and transitions</li>
                            <li>• Document uploads and validations</li>
                            <li>• Important system alerts and deadlines</li>
                            <li>• Status changes requiring your attention</li>
                        </ul>
                    </div>

                    {isSubscribed && (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-3 dark:bg-green-950 dark:border-green-800">
                            <div className="flex items-center gap-2 text-green-800 dark:text-green-200">
                                <CheckCircle2 className="w-4 h-4" />
                                <span className="text-sm font-medium">
                                    You're all set! You'll receive push notifications for important updates.
                                </span>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Push Notification Settings" />
            <SettingsLayout>
                {renderContent()}
            </SettingsLayout>
        </AppLayout>
    );
}
