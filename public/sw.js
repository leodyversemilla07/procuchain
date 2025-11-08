// Service Worker for Push Notifications
const CACHE_NAME = 'procuchain-push-v1';

// Install event
self.addEventListener('install', event => {
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

// Push event - handles incoming push notifications
self.addEventListener('push', event => {
    // Send message to all clients about push received
    self.clients.matchAll().then(clients => {
        clients.forEach(client => {
            client.postMessage({
                type: 'push-received',
                timestamp: new Date().toISOString()
            });
        });
    });
    
    if (!event.data) {
        // Send message to clients about no data
        self.clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'push-no-data',
                    timestamp: new Date().toISOString()
                });
            });
        });
        return;
    }

    try {
        const data = event.data.json();
        
        // Send message to clients about received data
        self.clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'push-data-received',
                    data: data,
                    timestamp: new Date().toISOString()
                });
            });
        });

        const title = data.title || 'ProcuChain Notification';
        const options = {
            body: data.body || 'You have a new notification',
            icon: data.icon || '/favicon.ico',
            badge: data.badge || '/favicon.ico',
            data: data.data || {},
            requireInteraction: data.requireInteraction || true,
            actions: data.actions || [],
            tag: data.tag || 'procuchain-notification',
            renotify: true,
            silent: false,
            vibrate: [200, 100, 200],
            timestamp: Date.now()
        };

        // Add default action if not provided
        if (!options.actions.length && data.data && data.data.url) {
            options.actions = [{
                action: 'view',
                title: 'View Details',
                icon: '/favicon.ico'
            }];
        }
        
        // Send message to clients about showing notification
        self.clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'notification-showing',
                    title: title,
                    options: options,
                    timestamp: new Date().toISOString()
                });
            });
        });

        event.waitUntil(
            self.registration.showNotification(title, options).then(() => {
                // Send success message to clients
                self.clients.matchAll().then(clients => {
                    clients.forEach(client => {
                        client.postMessage({
                            type: 'notification-shown-success',
                            timestamp: new Date().toISOString()
                        });
                    });
                });
            }).catch(error => {
                console.error('❌ Error showing notification:', error);
                
                // Send error message to clients
                self.clients.matchAll().then(clients => {
                    clients.forEach(client => {
                        client.postMessage({
                            type: 'notification-show-error',
                            error: error.message,
                            timestamp: new Date().toISOString()
                        });
                    });
                });
            })
        );
    } catch (error) {
        console.error('❌ Error processing push event:', error);
        
        // Send error message to clients
        self.clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'push-process-error',
                    error: error.message,
                    timestamp: new Date().toISOString()
                });
            });
        });
        
        // Fallback notification
        event.waitUntil(
            self.registration.showNotification('ProcuChain Notification', {
                body: 'You have a new notification',
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                requireInteraction: true
            })
        );
    }
});

// Notification click event
self.addEventListener('notificationclick', event => {
    event.notification.close();

    const data = event.notification.data || {};
    const action = event.action;
    
    let url = '/';
    
    // Determine the URL to open
    if (action === 'view' && data.url) {
        url = data.url;
    } else if (data.url) {
        url = data.url;
    } else if (data.procurement_id) {
        // Fallback to notifications page if we have procurement ID
        url = '/notifications';
    }

    // Focus existing window or open new one
    event.waitUntil(
        self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(clientList => {
            // Check if there's already a window open
            for (const client of clientList) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            
            // If no window is open, open a new one
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        }).catch(error => {
            console.error('Error handling notification click:', error);
        })
    );
});

// Notification close event
self.addEventListener('notificationclose', event => {
    // You can track notification dismissals here if needed
    const data = event.notification.data || {};
    
    // Optional: Send analytics data about notification dismissal
    // Analytics tracking can be added here if needed
});

// Background sync for offline notifications
self.addEventListener('sync', event => {
    if (event.tag === 'procurement-sync') {
        event.waitUntil(
            // You can implement background sync logic here
            Promise.resolve()
        );
    }
});

// Message event - for communication with main thread
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    // Send response back to main thread
    event.ports[0].postMessage({
        type: 'SW_RESPONSE',
        message: 'Service Worker received your message'
    });
});

// Error handling
self.addEventListener('error', event => {
    console.error('Service Worker error:', event);
});

self.addEventListener('unhandledrejection', event => {
    console.error('Service Worker unhandled rejection:', event);
});
