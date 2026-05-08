import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/settings/appearance';
import { edit as editEmailNotification } from '@/routes/settings/email-notification';
import { edit as editPassword } from '@/routes/settings/password';
import { edit } from '@/routes/settings/profile';
import { edit as editPushNotification } from '@/routes/settings/push-notification';
import { show } from '@/routes/settings/two-factor';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit.url(),
        icon: null,
    },
    {
        title: 'Password',
        href: editPassword.url(),
        icon: null,
    },
    {
        title: 'Two-Factor Auth',
        href: show.url(),
        icon: null,
    },
    {
        title: 'Notifications',
        href: '/settings/notification-preferences',
        icon: null,
    },
    {
        title: 'Push Notifications',
        href: editPushNotification.url(),
        icon: null,
    },
    {
        title: 'Email Notifications',
        href: editEmailNotification.url(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance.url(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading title="Settings" description="Manage your profile and account settings" />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.href}
                                size="sm"
                                variant="ghost"
                                render={<Link href={item.href} prefetch />}
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.href,
                                })}
                            >
                                {item.title}
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
