import '@fontsource/geist/400.css';
import '@fontsource/geist/500.css';
import '@fontsource/geist/600.css';
import '@fontsource/geist/700.css';
import '../css/app.css';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { createInertiaApp } from '@inertiajs/react';
import { useEffect } from 'react';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
                <ThemeInitializer />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
    strictMode: true,
});

function ThemeInitializer() {
    useEffect(() => {
        initializeTheme();
    }, []);

    return null;
}
