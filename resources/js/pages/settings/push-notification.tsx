import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { subscribe, unsubscribe } from '@/routes/settings/push-notification';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, Bell, BellOff, CheckCircle2 } from 'lucide-react';
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
        const checkSupport = () => {
            // Check for all required APIs
            const hasServiceWorker = 'serviceWorker' in navigator;
            const hasPushManager = 'PushManager' in window;
            const hasNotification = 'Notification' in window;

            const supported = hasServiceWorker && hasPushManager && hasNotification;

            setIsSupported(supported);
            if (supported && 'Notification' in window) {
                setPermission(Notification.permission);
            }
        };

        checkSupport();
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
        if (!isSupported) {
            return;
        }

        const registerServiceWorker = async () => {
            try {
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                setRegistration(reg);
                await checkSubscriptionStatus(reg);
            } catch (e) {
                console.error('Service Worker registration failed:', e);
                const errorMessage = e instanceof Error ? e.message : 'Failed to register service worker';
                setError(`Service Worker Error: ${errorMessage}`);
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
            await new Promise<void>((resolve, reject) => {
                router.post(
                    subscribe.url(),
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
                await new Promise<void>((resolve, reject) => {
                    router.visit(unsubscribe.url(), {
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
            <Head title="Push notification settings" />
            <SettingsLayout>
                <div className="flex flex-col gap-6">
                    <HeadingSmall title="Push notifications" description="Manage your push notification preferences" />

                    {!isSupported ? (
                        <div className="flex flex-col items-start justify-start gap-4">
                            <Badge variant="destructive">Not Supported</Badge>
                            <div className="flex flex-col gap-2">
                                <p className="text-muted-foreground">Push notifications are not supported in your current environment.</p>
                                {typeof window !== 'undefined' && !window.isSecureContext && window.location.protocol === 'http:' && (
                                    <Alert>
                                        <AlertCircle />
                                        <AlertDescription>
                                            <strong>HTTPS Required:</strong> Push notifications require a secure connection (HTTPS). Your site is
                                            currently running on HTTP. Please use HTTPS or access via localhost/127.0.0.1 for testing.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {typeof window !== 'undefined' && window.isSecureContext && (
                                    <Alert>
                                        <AlertCircle />
                                        <AlertDescription>
                                            Your browser may not support push notifications. Please use a modern browser like Chrome, Firefox, Safari,
                                            or Edge.
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start gap-4">
                            <div className="flex items-center gap-2">
                                {isSubscribed ? (
                                    <Badge variant="default">Enabled</Badge>
                                ) : permission === 'granted' ? (
                                    <Badge variant="secondary">Ready to Enable</Badge>
                                ) : permission === 'denied' ? (
                                    <Badge variant="destructive">Permission Denied</Badge>
                                ) : (
                                    <Badge variant="outline">Not Configured</Badge>
                                )}
                            </div>

                            <p className="text-muted-foreground">
                                {isSubscribed
                                    ? "You'll receive push notifications for procurement updates, document changes, and important system events."
                                    : permission === 'granted'
                                      ? 'Click the button below to start receiving push notifications for procurement activities, document validations, and important alerts.'
                                      : 'Enable push notifications to receive real-time updates about procurement activities, document validations, and important alerts directly in your browser.'}
                            </p>

                            {showPermissionAlert && (
                                <Alert>
                                    <AlertCircle />
                                    <AlertDescription>
                                        Notification permission is currently denied. Please enable notifications in your browser settings to receive
                                        push notifications.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {error && (
                                <Alert variant="destructive">
                                    <AlertCircle />
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}

                            <div className="flex w-full flex-col gap-3 rounded-lg border p-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Permission Status</span>
                                    {permission === 'granted' ? (
                                        <Badge variant="default">
                                            Granted
                                        </Badge>
                                    ) : permission === 'denied' ? (
                                        <Badge variant="destructive">Denied</Badge>
                                    ) : (
                                        <Badge variant="secondary">Not Requested</Badge>
                                    )}
                                </div>

                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Subscription Status</span>
                                    {isSubscribed ? (
                                        <Badge variant="default">
                                            <CheckCircle2 data-icon="inline-start" />
                                            Active
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            <BellOff data-icon="inline-start" />
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            {permission === 'default' && (
                                <Button type="button" onClick={handleRequestPermission} disabled={isLoading}>
                                    {isLoading ? (
                                        <>
                                            <Spinner data-icon="inline-start" />
                                            Requesting...
                                        </>
                                    ) : (
                                        <>
                                            <Bell />
                                            Request Permission
                                        </>
                                    )}
                                </Button>
                            )}

                            {permission === 'granted' && !isSubscribed && (
                                <Button type="button" onClick={handleSubscribe} disabled={isLoading}>
                                    {isLoading ? (
                                        <>
                                            <Spinner data-icon="inline-start" />
                                            Subscribing...
                                        </>
                                    ) : (
                                        <>
                                            <Bell />
                                            Enable Push Notifications
                                        </>
                                    )}
                                </Button>
                            )}

                            {isSubscribed && (
                                <Button variant="destructive" type="button" onClick={handleUnsubscribe} disabled={isLoading}>
                                    {isLoading ? (
                                        <>
                                            <Spinner data-icon="inline-start" />
                                            Disabling...
                                        </>
                                    ) : (
                                        <>
                                            <BellOff />
                                            Disable Push Notifications
                                        </>
                                    )}
                                </Button>
                            )}

                            {isSubscribed && (
                                <Alert>
                                    <Bell />
                                    <AlertDescription>
                                        <div className="flex flex-col gap-2">
                                            <p className="font-medium">What you'll receive notifications for:</p>
                                            <ul className="marker:text-muted-foreground ml-4 flex list-disc flex-col gap-1.5 text-sm">
                                                <li>Procurement stage updates and transitions</li>
                                                <li>Document uploads and validations</li>
                                                <li>Important system alerts and deadlines</li>
                                                <li>Status changes requiring your attention</li>
                                            </ul>
                                        </div>
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}
                </div>
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
