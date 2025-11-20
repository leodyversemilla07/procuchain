import { about, contact, home, team } from '@/routes';
import { policy } from '@/routes/privacy';
import { service } from '@/routes/terms';
import { Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import AppLogoIcon from './app-logo-icon';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="border-t bg-background">
            <div className="container mx-auto px-4 py-8 sm:px-12 lg:px-16 xl:px-20">
                <div className="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <Link href={home.url()} className="flex items-center gap-2">
                        <div className="h-8 w-8 overflow-hidden rounded-lg">
                            <AppLogoIcon className="h-full w-full" />
                        </div>
                        <span className="text-lg font-bold">ProcuChain</span>
                    </Link>

                    <nav className="flex flex-wrap justify-center gap-6">
                        <Link href={home.url()} className="text-sm text-muted-foreground hover:text-foreground">
                            Home
                        </Link>
                        <Link href={about.url()} className="text-sm text-muted-foreground hover:text-foreground">
                            About
                        </Link>
                        <Link href={team.url()} className="text-sm text-muted-foreground hover:text-foreground">
                            Team
                        </Link>
                        <Link href={contact.url()} className="text-sm text-muted-foreground hover:text-foreground">
                            Contact
                        </Link>
                    </nav>

                    <a
                        href="mailto:semilla.leodyver@minsu.edu.ph"
                        className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <Mail className="h-4 w-4" />
                        Email Us
                    </a>
                </div>

                <div className="mt-8 flex flex-col items-center justify-between gap-4 border-t pt-8 sm:flex-row">
                    <p className="text-sm text-muted-foreground">© {currentYear} ProcuChain. All rights reserved.</p>
                    <div className="flex gap-6">
                        <Link href={policy.url()} className="text-sm text-muted-foreground hover:text-foreground">
                            Privacy
                        </Link>
                        <Link href={service.url()} className="text-sm text-muted-foreground hover:text-foreground">
                            Terms
                        </Link>
                    </div>
                </div>
            </div>
        </footer>
    );
}
