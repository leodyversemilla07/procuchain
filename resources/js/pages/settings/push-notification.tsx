import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, Bell, BellOff, BellRing, CheckCircle2, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

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
        vapidPublicKey?: string | null;
    }>();
    const [isSupported, setIsSupported] = useState(false);
    const [permission, setPermission] = useState<NotificationPermission>('default');
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [registration, setRegistration] = useState<ServiceWorkerRegistration | null>(null);

    const [showPermissionAlert, setShowPermissionAlert] = useState(false);

    // Check if push notifications are supported
    useEffect(() => {
        const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

        setIsSupported(supported);
        if (supported) setPermission(Notification.permission);
    }, []);

    // Check current subscription status
    const checkSubscriptionStatus = useCallback(
        async (reg?: ServiceWorkerRegistration) => {
            const swRegistration = reg || registration;
            if (!swRegistration) return;

            try {
                const subscription = await swRegistration.pushManager.getSubscription();
                setIsSubscribed(!!subscription);
            } catch (e) {
                console.error('Error checking subscription status:', e);
            }
        },
        [registration],
    );

    // Register service worker
    useEffect(() => {
        if (!isSupported) return;

        const registerServiceWorker = async () => {
            try {
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                console.log('Service Worker registered:', reg);
                setRegistration(reg);
                checkSubscriptionStatus(reg);
            } catch (e) {
                console.error('Service Worker registration failed:', e);
                setError('Failed to register service worker');
            }
        };

        registerServiceWorker();
    }, [isSupported, checkSubscriptionStatus]);

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

    const requestPermission = useCallback(async (): Promise<boolean> => {
        if (!isSupported) {
            setError('Push notifications are not supported');
            return false;
        }
        try {
            const p = await Notification.requestPermission();
            setPermission(p);
            if (p === 'granted') {
                setError(null);
                return true;
            }
            setError('Notification permission denied');
            return false;
        } catch (e) {
            console.error('Error requesting permission:', e);
            setError('Failed to request permission');
            return false;
        }
    }, [isSupported]);

    const handleSubscribe = async (e?: React.MouseEvent) => {
        e?.preventDefault();
        e?.stopPropagation();
        console.log('[Push] Subscribe button clicked');
        try {
            // Ensure permission and registration
            if (permission !== 'granted') {
                const granted = await requestPermission();
                if (!granted) return toast.error('Notification permission denied');
            }
            if (!registration) {
                setError('Service worker not registered');
                return toast.error('Service worker not registered');
            }

            setIsLoading(true);
            setError(null);

            const vapidPublicKey = props.vapidPublicKey;
            if (!vapidPublicKey) throw new Error('VAPID public key not available');

            const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);

            // Subscribe to push notifications
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey as unknown as BufferSource,
            });

            // Send subscription to server using Inertia
            console.log('[Push] Subscribing with endpoint', subscription.endpoint);
            await new Promise<void>((resolve, reject) => {
                router.post(
                    '/settings/push/subscribe',
                    {
                        endpoint: subscription.endpoint,
                        keys: {
                            p256dh: arrayBufferToBase64(subscription.getKey('p256dh')!),
                            auth: arrayBufferToBase64(subscription.getKey('auth')!),
                        },
                    },
                    {
                        onSuccess: () => resolve(),
                        onError: (errors) => reject(new Error(Object.values(errors).flat().join(', '))),
                        preserveState: true,
                        preserveScroll: true,
                    },
                );
            });

            setIsSubscribed(true);
            console.log('Successfully subscribed to push notifications');
            const success = true;
            if (!success) {
                toast.error('Failed to subscribe to push notifications');
            }
        } catch (err) {
            console.error('[Push] Subscribe error', err);
            toast.error('Failed to subscribe to push notifications');
        } finally {
            setIsLoading(false);
        }
    };

    const handleUnsubscribe = async (e?: React.MouseEvent) => {
        e?.preventDefault();
        e?.stopPropagation();
        console.log('[Push] Unsubscribe button clicked');
        try {
            if (!registration) {
                setError('Service worker not registered');
                return toast.error('Service worker not registered');
            }

            setIsLoading(true);
            setError(null);

            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
                console.log('Unsubscribing with endpoint', subscription.endpoint);
                await new Promise<void>((resolve, reject) => {
                    router.visit('/settings/push/unsubscribe', {
                        method: 'delete',
                        data: { endpoint: subscription.endpoint },
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => resolve(),
                        onError: (errors: Record<string, string | string[]>) => {
                            reject(new Error(Object.values(errors).flat().join(', ')));
                        },
                    });
                });
            }

            setIsSubscribed(false);
            console.log('Successfully unsubscribed from push notifications');
            const success = true;
            if (!success) {
                toast.error('Failed to unsubscribe from push notifications');
            }
        } catch (err) {
            console.error('[Push] Unsubscribe error', err);
            toast.error('Failed to unsubscribe from push notifications');
        } finally {
            setIsLoading(false);
        }
    };

    const handleRequestPermission = async (e?: React.MouseEvent) => {
        e?.preventDefault();
        e?.stopPropagation();
        console.log('[Push] Request permission clicked');
        try {
            const granted = await requestPermission();
            if (granted) {
                toast.success('Notification permission granted!');
                setShowPermissionAlert(false);
            } else {
                toast.error('Notification permission denied');
            }
        } catch (err) {
            console.error('[Push] Permission request error', err);
            toast.error('Failed to request notification permission');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Push Notification Settings" />
            <SettingsLayout>
                {!isSupported ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BellOff className="h-5 w-5" />
                                Push Notifications
                            </CardTitle>
                            <CardDescription>Get real-time notifications about procurement updates</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Push notifications are not supported in your browser. Please use a modern browser like Chrome, Firefox, Safari, or
                                    Edge.
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BellRing className="h-5 w-5" />
                                Push Notifications
                            </CardTitle>
                            <CardDescription>Get real-time notifications about procurement updates and important system events</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Permission Status:</span>
                                    {permission === 'granted' ? (
                                        <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700 hover:bg-green-50">
                                            Granted
                                        </Badge>
                                    ) : permission === 'denied' ? (
                                        <Badge variant="destructive">Denied</Badge>
                                    ) : permission === 'default' ? (
                                        <Badge variant="secondary">Not Requested</Badge>
                                    ) : (
                                        <Badge variant="outline">Unknown</Badge>
                                    )}
                                </div>

                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Subscription Status:</span>
                                    {isSubscribed ? (
                                        <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700 hover:bg-green-50">
                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                            Subscribed
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            <BellOff className="mr-1 h-3 w-3" />
                                            Not Subscribed
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            {showPermissionAlert && (
                                <Alert>
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription className="flex items-center justify-between">
                                        <span>Notification permission is required to receive push notifications.</span>
                                        <Button size="sm" type="button" onClick={handleRequestPermission} disabled={isLoading}>
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
                                    <Button type="button" onClick={handleSubscribe} disabled={isLoading} className="w-full">
                                        {isLoading ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Subscribing...
                                            </>
                                        ) : (
                                            <>
                                                <Bell className="mr-2 h-4 w-4" />
                                                Enable Push Notifications
                                            </>
                                        )}
                                    </Button>
                                )}

                                {isSubscribed && (
                                    <div className="flex gap-2">
                                        <Button variant="outline" type="button" onClick={handleUnsubscribe} disabled={isLoading} className="flex-1">
                                            {isLoading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <BellOff className="mr-2 h-4 w-4" />}
                                            Disable
                                        </Button>
                                    </div>
                                )}

                                {permission === 'default' && (
                                    <Button type="button" onClick={handleRequestPermission} disabled={isLoading} className="w-full">
                                        {isLoading ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Requesting...
                                            </>
                                        ) : (
                                            <>
                                                <Bell className="mr-2 h-4 w-4" />
                                                Request Permission
                                            </>
                                        )}
                                    </Button>
                                )}
                            </div>

                            <div className="bg-muted/50 rounded-lg p-3">
                                <h4 className="mb-2 text-sm font-medium">What you'll receive notifications for:</h4>
                                <ul className="text-muted-foreground space-y-1 text-xs">
                                    <li>• Procurement stage updates and transitions</li>
                                    <li>• Document uploads and validations</li>
                                    <li>• Important system alerts and deadlines</li>
                                    <li>• Status changes requiring your attention</li>
                                </ul>
                            </div>

                            {isSubscribed && (
                                <div className="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950">
                                    <div className="flex items-center gap-2 text-green-800 dark:text-green-200">
                                        <CheckCircle2 className="h-4 w-4" />
                                        <span className="text-sm font-medium">
                                            You're all set! You'll receive push notifications for important updates.
                                        </span>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </SettingsLayout>
        </AppLayout>
    );
}

// Utility functions (moved from hook)
function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}
