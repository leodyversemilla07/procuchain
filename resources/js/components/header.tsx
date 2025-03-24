import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { type SharedData } from '@/types';

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            if (window.scrollY > 20) {
                setScrolled(true);
            } else {
                setScrolled(false);
            }
        };

        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Close mobile menu when navigating
    useEffect(() => {
        const handleRouteChange = () => setMobileMenuOpen(false);
        window.addEventListener('popstate', handleRouteChange);
        return () => window.removeEventListener('popstate', handleRouteChange);
    }, []);

    // Lock body scroll when mobile menu is open
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

    return (
        <>
            <header className={`fixed top-0 left-0 right-0 z-50 ${mobileMenuOpen
                ? "bg-white/90 dark:bg-gray-900/90 backdrop-blur-2xl py-3 shadow-lg"
                : scrolled
                    ? "bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl py-3 shadow-lg"
                    : "bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl py-4"
                } w-full px-4 sm:px-6 md:px-12 border-b border-gray-100/50 dark:border-gray-800/50 transition-all duration-300 ease-in-out`}>
                <div className="max-w-7xl mx-auto flex items-center justify-between">
                    {/* Logo section with enhanced animation */}
                    <Link href={route('home')} className="flex items-center space-x-3 group">
                        <div className="h-11 w-11 rounded-xl bg-gradient-to-tr from-teal-600 via-teal-500 to-teal-400 flex items-center justify-center overflow-hidden relative transform transition-all duration-300 group-hover:scale-105 group-hover:rotate-3 shadow-lg">
                            <span className="text-white font-bold text-xl relative z-10 group-hover:scale-110 transition-transform duration-300">P</span>
                            <div className="absolute inset-0 bg-gradient-to-br from-teal-400 to-teal-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rotate-180"></div>
                        </div>
                        <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent group-hover:to-teal-500 transition-all duration-300">ProcuChain</span>
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="hidden md:flex items-center gap-6">
                        <Link
                            href={route('home')}
                            className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                        >
                            <span className="relative z-10">Home</span>
                            <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                        </Link>
                        <Link
                            href={route('bidding')}
                            className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                        >
                            <span className="relative z-10">Bidding</span>
                            <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                        </Link>
                        <Link
                            href={route('procurement')}
                            className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                        >
                            <span className="relative z-10">Procurement</span>
                            <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                        </Link>
                        <Link
                            href={route('generate-pr.index')}
                            className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                        >
                            <span className="relative z-10">PR Generator</span>
                            <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                        </Link>

                        {auth?.user ? (
                            <>
                                {auth.user.role === 'hope' ? (
                                    <Link
                                        href={route('hope.dashboard')}
                                        className="relative inline-block px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : auth.user.role === 'bac_secretariat' ? (
                                    <Link
                                        href={route('bac-secretariat.dashboard')}
                                        className="relative inline-block px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : auth.user.role === 'bac_chairman' ? (
                                    <Link
                                        href={route('bac-chairman.dashboard')}
                                        className="relative inline-block px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : null}
                            </>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="relative px-5 py-2.5 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                                >
                                    <span className="relative z-10">Log in</span>
                                    <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="relative inline-block px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                >
                                    Get Started
                                </Link>
                            </>
                        )}
                    </nav>

                    {/* Mobile menu button with Lucide React icons */}
                    <button
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        className="md:hidden p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-all duration-200"
                        aria-label="Toggle menu"
                    >
                        <div className="relative w-6 h-6 flex items-center justify-center">
                            {mobileMenuOpen ? (
                                <X className="absolute transition-all duration-300 ease-in-out" size={24} />
                            ) : (
                                <Menu className="absolute transition-all duration-300 ease-in-out" size={24} />
                            )}
                        </div>
                    </button>
                </div>
            </header>

            {/* Mobile Menu Backdrop */}
            <div
                className={`fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-40 md:hidden transition-opacity duration-300 ${mobileMenuOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
                onClick={() => setMobileMenuOpen(false)}
                aria-hidden="true"
            ></div>

            {/* Mobile Menu Side Drawer */}
            <div
                className={`fixed top-0 right-0 bottom-0 w-[280px] bg-white dark:bg-gray-900 z-50 md:hidden shadow-xl transform transition-transform duration-300 ease-in-out ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}
            >
                {/* Menu Header with Close Button */}
                <div className="flex items-center justify-end p-4 border-b border-gray-100 dark:border-gray-800">
                    <button
                        onClick={() => setMobileMenuOpen(false)}
                        className="p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none"
                        aria-label="Close menu"
                    >
                        <X size={20} />
                    </button>
                </div>

                {/* Menu Items */}
                <div className="overflow-y-auto py-4">
                    <nav className="flex flex-col">
                        <Link
                            href={route('home')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3 flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('home') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">Home</span>
                        </Link>
                        <Link
                            href={route('bidding')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3 flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('bidding') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">Bidding</span>
                        </Link>
                        <Link
                            href={route('procurement')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3 flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('procurement') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">Procurement</span>
                        </Link>
                        <Link
                            href={route('generate-pr.index')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3 flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('generate-pr.index') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">PR Generator</span>
                        </Link>

                        {/* Bottom section with auth buttons or dashboard access */}
                        <div className="mt-4 px-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                            {auth?.user ? (
                                auth.user.role === 'hope' ? (
                                    <Link
                                        href={route('hope.dashboard')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-2.5 px-3 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : auth.user.role === 'bac_secretariat' ? (
                                    <Link
                                        href={route('bac-secretariat.dashboard')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-2.5 px-3 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : auth.user.role === 'bac_chairman' ? (
                                    <Link
                                        href={route('bac-chairman.dashboard')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-2.5 px-3 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : null
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-2.5 px-3 text-center rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-2.5 px-3 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white mt-3 hover:from-teal-500 hover:to-teal-400 transition-colors duration-200"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </div>

                {/* Footer */}
                <div className="absolute bottom-0 left-0 right-0 border-t border-gray-100 dark:border-gray-800 py-4 px-4">
                    <p className="text-sm text-gray-500 dark:text-gray-400 text-center">
                        &copy; {new Date().getFullYear()} ProcuChain
                    </p>
                </div>
            </div>
        </>
    );
}
