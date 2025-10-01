import { Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import AppLogoIcon from './app-logo-icon';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    const navigationLinks = [
        { name: 'Home', href: route('home') },
        { name: 'About', href: route('about') },
        { name: 'Team', href: route('team') },
        { name: 'Contact', href: route('contact') },
    ];
    return (
        <footer className="bg-background border-border border-t">
            <div className="mx-auto max-w-7xl px-3 py-6 sm:px-4 sm:py-8 md:px-6 lg:px-8">
                <div className="flex flex-col space-y-8 md:flex-row md:items-center md:justify-between md:space-y-0">
                    <div className="flex items-center justify-center space-x-3 md:justify-start">
                        <div className="h-8 w-8 transform overflow-hidden rounded-xl transition-transform duration-200 hover:scale-105 sm:h-10 sm:w-10">
                            <AppLogoIcon className="h-full w-full object-cover" />
                        </div>
                        <span className="text-foreground text-lg font-medium sm:text-xl">ProcuChain</span>
                    </div>{' '}
                    <nav className="flex flex-wrap justify-center gap-x-6 gap-y-4 md:gap-x-8">
                        {navigationLinks.map((link, index) => (
                            <Link key={index} href={link.href} className="text-muted-foreground hover:text-foreground text-sm transition-colors">
                                {link.name}
                            </Link>
                        ))}
                    </nav>
                    <div className="flex justify-center md:justify-end">
                        <a
                            href="mailto:semilla.leodyver@minsu.edu.ph"
                            className="text-muted-foreground hover:text-foreground flex items-center text-sm transition-colors"
                        >
                            <Mail className="mr-2 h-4 w-4 sm:h-5 sm:w-5" />
                            Contact
                        </a>
                    </div>
                </div>

                <div className="border-border mt-8 border-t pt-6 sm:pt-8">
                    <div className="flex flex-col items-center justify-between space-y-4 sm:flex-row sm:space-y-0">
                        <p className="text-muted-foreground text-center text-sm sm:text-left">© {currentYear} ProcuChain</p>
                        <div className="flex space-x-6">
                            <a
                                href={route('privacy.policy')}
                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Privacy
                            </a>
                            <a
                                href={route('terms.service')}
                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Terms
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
}
