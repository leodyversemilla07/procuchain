import { useEffect, useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

interface UsePushNotificationsReturn {
    isSupported: boolean;
    permission: NotificationPermission;
    isSubscribed: boolean;
    isLoading: boolean;
    error: string | null;
    requestPermission: () => Promise<boolean>;
    subscribe: () => Promise<boolean>;
    unsubscribe: () => Promise<boolean>;
    sendTestNotification: () => Promise<void>;
}

export function usePushNotifications(): UsePushNotificationsReturn {
    const [isSupported, setIsSupported] = useState(false);
    const [permission, setPermission] = useState<NotificationPermission>('default');
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [registration, setRegistration] = useState<ServiceWorkerRegistration | null>(null);

    // Check if push notifications are supported
    useEffect(() => {
        const checkSupport = () => {
            const supported = 
                'serviceWorker' in navigator &&
                'PushManager' in window &&
                'Notification' in window;
            
            setIsSupported(supported);
            
            if (supported) {
                setPermission(Notification.permission);
            }
        };

        checkSupport();
    }, []);

    // Check current subscription status
    const checkSubscriptionStatus = useCallback(async (reg?: ServiceWorkerRegistration) => {
        if (!reg && !registration) return;
        
        const swRegistration = reg || registration;
        if (!swRegistration) return;

        try {
            const subscription = await swRegistration.pushManager.getSubscription();
            setIsSubscribed(!!subscription);
        } catch (error) {
            console.error('Error checking subscription status:', error);
        }
    }, [registration]);

    // Register service worker
    useEffect(() => {
        if (!isSupported) return;

        const registerServiceWorker = async () => {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });
                
                console.log('Service Worker registered:', registration);
                setRegistration(registration);

                // Check if already subscribed
                checkSubscriptionStatus(registration);
            } catch (error) {
                console.error('Service Worker registration failed:', error);
                setError('Failed to register service worker');
            }
        };

        registerServiceWorker();
    }, [isSupported, checkSubscriptionStatus]);

    // Request permission for notifications
    const requestPermission = useCallback(async (): Promise<boolean> => {
        if (!isSupported) {
            setError('Push notifications are not supported');
            return false;
        }

        try {
            const permission = await Notification.requestPermission();
            setPermission(permission);
            
            if (permission === 'granted') {
                setError(null);
                return true;
            } else {
                setError('Notification permission denied');
                return false;
            }
        } catch (error) {
            console.error('Error requesting permission:', error);
            setError('Failed to request permission');
            return false;
        }
    }, [isSupported]);

    // Subscribe to push notifications
    const subscribe = useCallback(async (): Promise<boolean> => {
        if (!registration || permission !== 'granted') {
            if (permission !== 'granted') {
                const granted = await requestPermission();
                if (!granted) return false;
            }
            
            if (!registration) {
                setError('Service worker not registered');
                return false;
            }
        }

        setIsLoading(true);
        setError(null);

        try {
            // Get VAPID public key from server
            const { data } = await axios.get('/settings/push/vapid-public-key');
            const vapidPublicKey = data.vapid_public_key;

            if (!vapidPublicKey) {
                throw new Error('VAPID public key not available');
            }

            // Convert VAPID key to Uint8Array
            const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);

            // Subscribe to push notifications
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            // Send subscription to server using Inertia
            await new Promise<void>((resolve, reject) => {
                router.post('/settings/push/subscribe', {
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: arrayBufferToBase64(subscription.getKey('p256dh')!),
                        auth: arrayBufferToBase64(subscription.getKey('auth')!)
                    }
                }, {
                    onSuccess: () => resolve(),
                    onError: (errors) => reject(new Error(Object.values(errors).flat().join(', '))),
                    preserveState: true,
                    preserveScroll: true,
                });
            });

            setIsSubscribed(true);
            console.log('Successfully subscribed to push notifications');
            return true;
        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
            setError('Failed to subscribe to notifications');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [registration, permission, requestPermission]);

    // Unsubscribe from push notifications
    const unsubscribe = useCallback(async (): Promise<boolean> => {
        if (!registration) {
            setError('Service worker not registered');
            return false;
        }

        setIsLoading(true);
        setError(null);

        try {
            const subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                // Unsubscribe from browser
                await subscription.unsubscribe();

                // Remove subscription from server using Inertia
                await new Promise<void>((resolve, reject) => {
                    router.delete('/settings/push/unsubscribe', {
                        data: { endpoint: subscription.endpoint },
                        onSuccess: () => resolve(),
                        onError: (errors) => reject(new Error(Object.values(errors).flat().join(', '))),
                        preserveState: true,
                        preserveScroll: true,
                    });
                });
            }

            setIsSubscribed(false);
            console.log('Successfully unsubscribed from push notifications');
            return true;
        } catch (error) {
            console.error('Error unsubscribing from push notifications:', error);
            setError('Failed to unsubscribe from notifications');
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [registration]);

    // Send test notification (for development/testing)
    const sendTestNotification = useCallback(async (): Promise<void> => {
        if (!isSubscribed || permission !== 'granted') {
            setError('Not subscribed to notifications or permission not granted');
            return;
        }

        try {
            // Send test notification from server using Inertia
            await new Promise<void>((resolve, reject) => {
                router.post('/settings/push/test', {}, {
                    onSuccess: () => {
                        resolve();
                        console.log('Test notification sent from server');
                    },
                    onError: (errors) => reject(new Error(Object.values(errors).flat().join(', '))),
                    preserveState: true,
                    preserveScroll: true,
                });
            });
        } catch (error) {
            console.error('Error sending test notification:', error);
            setError('Failed to send test notification');
        }
    }, [isSubscribed, permission]);

    return {
        isSupported,
        permission,
        isSubscribed,
        isLoading,
        error,
        requestPermission,
        subscribe,
        unsubscribe,
        sendTestNotification
    };
}

// Utility functions
function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

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
