import { useAppearance } from '@/hooks/use-appearance';
import { about, contact, home, login, team, workflow } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as bacChairmanDashboard } from '@/routes/bac-chairman';
import { dashboard as bacSecretariatDashboard } from '@/routes/bac-secretariat';
import { dashboard as hopeDashboard } from '@/routes/hope';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, Monitor, Moon, Sun, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from './app-logo-icon';

const getDashboardRouteByRole = (role: string): string => {
    const routes: Record<string, () => string> = {
        hope: hopeDashboard.url,
        bac_secretariat: bacSecretariatDashboard.url,
        bac_chairman: bacChairmanDashboard.url,
        admin: adminDashboard.url,
    };
    return (routes[role] || home.url)();
};

export default function Header() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const { appearance, updateAppearance } = useAppearance();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        if (mobileMenuOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }

        return () => {
            document.body.style.overflow = '';
        };
    }, [mobileMenuOpen]);

    const isRouteActive = (routeUrl: string): boolean => {
        return page.url === routeUrl || page.url.startsWith(routeUrl + '?');
    };

    const cycleAppearance = () => {
        const next = appearance === 'light' ? 'dark' : appearance === 'dark' ? 'system' : 'light';
        updateAppearance(next);
    };

    const ThemeIcon = appearance === 'light' ? Sun : appearance === 'dark' ? Moon : Monitor;

    return (
        <>
            <header className="border-border/50 bg-background/95 sticky top-0 z-40 w-full border-b backdrop-blur">
                <div className="container mx-auto flex h-16 items-center justify-between px-4 sm:px-12 lg:px-16 xl:px-20">
                    <Link href={home.url()} className="flex items-center gap-2 sm:gap-3">
                        <div className="h-8 w-8 overflow-hidden rounded-lg sm:h-10 sm:w-10">
                            <AppLogoIcon className="h-full w-full" />
                        </div>
                        <span className="text-lg font-bold sm:text-xl">ProcuChain</span>
                    </Link>

                    <nav className="hidden items-center gap-4 lg:flex lg:gap-6">
                        <Link
                            href={home.url()}
                            className={`text-sm font-medium transition-colors ${isRouteActive(home.url()) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Home
                        </Link>
                        <Link
                            href={about.url()}
                            className={`text-sm font-medium transition-colors ${isRouteActive(about.url()) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            About
                        </Link>
                        <Link
                            href={workflow.url()}
                            className={`text-sm font-medium transition-colors ${isRouteActive(workflow.url()) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Workflow
                        </Link>
                        <Link
                            href={team.url()}
                            className={`text-sm font-medium transition-colors ${isRouteActive(team.url()) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Team
                        </Link>
                        <Link
                            href={contact.url()}
                            className={`text-sm font-medium transition-colors ${isRouteActive(contact.url()) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Contact
                        </Link>
                    </nav>

                    <div className="flex items-center gap-2 sm:gap-3">
                        <button
                            onClick={cycleAppearance}
                            className="text-muted-foreground hover:bg-muted hover:text-foreground hidden rounded-lg p-2 md:block"
                        >
                            <ThemeIcon className="h-5 w-5" />
                        </button>

                        <div className="hidden lg:block">
                            {auth.user ? (
                                <Link
                                    href={getDashboardRouteByRole(auth.roles?.[0] || auth.user.role)}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-3 py-2 text-sm font-medium transition-colors sm:px-4"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login.url()}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-3 py-2 text-sm font-medium transition-colors sm:px-4"
                                >
                                    Sign In
                                </Link>
                            )}
                        </div>

                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-2 lg:hidden"
                        >
                            <Menu className="h-6 w-6" />
                        </button>
                    </div>
                </div>
            </header>

            {/* Mobile Menu */}
            {mobileMenuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setMobileMenuOpen(false)} />
                    <div className="bg-background fixed inset-y-0 right-0 w-full max-w-xs shadow-xl">
                        <div className="flex h-full flex-col">
                            <div className="flex items-center justify-between border-b p-4">
                                <span className="text-lg font-bold">Menu</span>
                                <button onClick={() => setMobileMenuOpen(false)} className="hover:bg-muted rounded-lg p-2">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            <nav className="flex-1 overflow-y-auto p-4">
                                <div className="space-y-1">
                                    <Link
                                        href={home.url()}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`block rounded-lg px-4 py-3 text-sm font-medium transition-colors ${
                                            isRouteActive(home.url())
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        Home
                                    </Link>
                                    <Link
                                        href={about.url()}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`block rounded-lg px-4 py-3 text-sm font-medium transition-colors ${
                                            isRouteActive(about.url())
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        About
                                    </Link>
                                    <Link
                                        href={workflow.url()}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`block rounded-lg px-4 py-3 text-sm font-medium transition-colors ${
                                            isRouteActive(workflow.url())
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        Workflow
                                    </Link>
                                    <Link
                                        href={team.url()}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`block rounded-lg px-4 py-3 text-sm font-medium transition-colors ${
                                            isRouteActive(team.url())
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        Team
                                    </Link>
                                    <Link
                                        href={contact.url()}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`block rounded-lg px-4 py-3 text-sm font-medium transition-colors ${
                                            isRouteActive(contact.url())
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        Contact
                                    </Link>
                                </div>
                            </nav>

                            <div className="space-y-3 border-t p-4">
                                <button
                                    onClick={cycleAppearance}
                                    className="hover:bg-muted flex w-full items-center justify-between rounded-lg px-4 py-3 text-sm font-medium"
                                >
                                    <span>Theme</span>
                                    <ThemeIcon className="h-5 w-5" />
                                </button>

                                {auth.user ? (
                                    <Link
                                        href={getDashboardRouteByRole(auth.roles?.[0] || auth.user.role)}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 block w-full rounded-lg px-4 py-3 text-center text-sm font-medium"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <Link
                                        href={login.url()}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 block w-full rounded-lg px-4 py-3 text-center text-sm font-medium"
                                    >
                                        Sign In
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
