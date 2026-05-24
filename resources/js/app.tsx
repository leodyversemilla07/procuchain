import '@fontsource/geist/400.css';
import '@fontsource/geist/500.css';
import '@fontsource/geist/600.css';
import '@fontsource/geist/700.css';
import '../css/app.css';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { createInertiaApp, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
 title: (title) => (title ? `${title} - ${appName}` : appName),
 withApp(app) {
 return (
 <TooltipProvider delay={0}>
 {app}
 <Toaster />
 <ThemeInitializer />
 <InertiaErrorHandlers />
 </TooltipProvider>
 );
 },
 progress: {
 color: '#4B5563',
 },
 strictMode: true,
});

/** Global Inertia error handlers for API/HTTP errors */
function InertiaErrorHandlers() {
 useEffect(() => {
 // Handle HTTP exceptions (4xx/5xx responses from the server)
 const offHttpException = router.on('httpException', (event) => {
 const { status } = event.detail.response;

 if (status >= 500) {
 // 5xx: redirect to the server-rendered error page
 router.visit(`/errors/${status}`);
 return false; // prevent Inertia's default handling
 }

 if (status >= 400) {
 // 4xx: show a toast notification
 const messages: Record<number, string> = {
 401: 'You are not authorized to perform this action.',
 403: 'You do not have permission to access this resource.',
 404: 'The requested resource was not found.',
 419: 'Your session has expired. Please refresh and try again.',
 429: 'Too many requests. Please wait a moment and try again.',
 };
 toast.error(messages[status] || `Request failed with status ${status}`);
 return false; // prevent Inertia's default handling
 }
 });

 // Handle network errors (connection lost, DNS failure, etc.)
 const offNetworkError = router.on('networkError', () => {
 toast.error('Network error — check your connection');
 return false; // prevent Inertia's default handling
 });

 // Handle form validation errors — show a generic toast;
 // individual forms already handle specific field errors via onError callbacks.
 const offError = router.on('error', () => {
 toast.error('Please fix the errors and try again.');
 });

 return () => {
 offHttpException();
 offNetworkError();
 offError();
 };
 }, []);

 return null;
}

function ThemeInitializer() {
    useEffect(() => {
        initializeTheme();
    }, []);

    return null;
}
