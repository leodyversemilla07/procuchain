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
        <footer className="bg-white dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800">
            <div className="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-6 sm:py-8">
                <div className="flex flex-col space-y-8 md:space-y-0 md:flex-row md:justify-between md:items-center">
                    <div className="flex items-center justify-center md:justify-start space-x-3">
                        <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-xl overflow-hidden transform transition-transform duration-200 hover:scale-105">
                            <AppLogoIcon className="w-full h-full object-cover" />
                        </div>
                        <span className="font-medium text-lg sm:text-xl text-gray-900 dark:text-white">
                            ProcuChain
                        </span>
                    </div>

                    <nav className="flex flex-wrap justify-center gap-x-6 gap-y-4 md:gap-x-8">
                        {navigationLinks.map((link, index) => (
                            <Link
                                key={index}
                                href={link.href}
                                className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors"
                            >
                                {link.name}
                            </Link>
                        ))}
                    </nav>

                    <div className="flex justify-center md:justify-end">
                        <a
                            href="mailto:semilla.leodyver@minsu.edu.ph"
                            className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors flex items-center"
                        >
                            <Mail className="w-4 h-4 sm:w-5 sm:h-5 mr-2" />
                            Contact
                        </a>
                    </div>
                </div>

                <div className="mt-8 pt-6 sm:pt-8 border-t border-gray-100 dark:border-gray-800">
                    <div className="flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
                        <p className="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left">
                            © {currentYear} ProcuChain
                        </p>
                        <div className="flex space-x-6">
                            <a
                                href={route('privacy.pdf')}
                                className="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Privacy
                            </a>
                            <a
                                href={route('terms.pdf')} 
                                className="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors"
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
