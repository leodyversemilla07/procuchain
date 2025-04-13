import { useState, useEffect, useRef } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X, Search } from 'lucide-react';
import { type SharedData } from '@/types';
import AppLogoIcon from './app-logo-icon';

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);
    const [hideNav, setHideNav] = useState(false);
    const [lastScrollY, setLastScrollY] = useState(0);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleScroll = () => {
            const currentScrollY = window.scrollY;

            if (currentScrollY > 20) {
                setScrolled(true);
            } else {
                setScrolled(false);
            }

            if (currentScrollY > lastScrollY && currentScrollY > 80) {
                setHideNav(true);
            } else {
                setHideNav(false);
            }

            setLastScrollY(currentScrollY);
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, [lastScrollY]);

    useEffect(() => {
        const handleRouteChange = () => {
            setMobileMenuOpen(false);
            setDropdownOpen(false);
        };
        window.addEventListener('popstate', handleRouteChange);
        return () => window.removeEventListener('popstate', handleRouteChange);
    }, []);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setDropdownOpen(false);
            }
        };

        if (dropdownOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [dropdownOpen]);

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

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('Search query:', searchQuery);
    };

    const toggleDropdown = (e: React.MouseEvent) => {
        e.preventDefault();
        setDropdownOpen(!dropdownOpen);
    };

    return (
        <>
            <header
                role="banner"
                className={`fixed top-0 left-0 right-0 z-50 transform transition-all duration-300 ease-in-out
                    ${hideNav ? '-translate-y-full' : 'translate-y-0'}
                    ${mobileMenuOpen
                        ? "bg-white/95 dark:bg-gray-900/95 backdrop-blur-2xl py-3 shadow-lg"
                        : scrolled
                            ? "bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl py-3 shadow-md"
                            : "bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl py-5"
                    } w-full px-4 sm:px-6 md:px-12 border-b border-gray-100/50 dark:border-gray-800/50`}
            >
                <div className="max-w-7xl mx-auto flex items-center justify-between">
                    <Link href={route('home')} className="flex items-center space-x-3 group" aria-label="Home">
                        <div className="h-11 w-11 rounded-xl overflow-hidden shadow-lg transition-transform duration-300 group-hover:scale-105">
                            <AppLogoIcon className="w-full h-full object-cover" />
                        </div>
                        <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent transition-all duration-300 group-hover:from-teal-500 group-hover:to-teal-300">ProcuChain</span>
                    </Link>

                    <nav className="hidden md:flex items-center gap-7" role="navigation" aria-label="Main navigation">
                        <form onSubmit={handleSearch} className="relative group">
                            <input
                                type="search"
                                placeholder="Search..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-48 pl-10 pr-4 py-2.5 text-sm bg-gray-50 dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 rounded-lg
                                    focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent
                                    focus:w-72 transition-all duration-300 ease-in-out"
                            />
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        </form>

                        <div className="flex items-center gap-3" role="menubar">
                            <Link
                                href={route('home')}
                                role="menuitem"
                                className="relative px-4 py-2.5 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                                aria-current={route().current('home') ? 'page' : undefined}
                            >
                                <span className="relative z-10">Home</span>
                                <div className={`absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 transition-all duration-300 
                                    ${route().current('home') ? 'opacity-100 scale-100' : 'opacity-0 group-hover:opacity-100 group-hover:scale-100'}`}></div>
                            </Link>

                            <div className="relative" role="menuitem" ref={dropdownRef}>
                                <button
                                    onClick={toggleDropdown}
                                    className="relative px-4 py-2.5 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 inline-flex items-center gap-1.5"
                                    aria-expanded={dropdownOpen}
                                >
                                    <span>Procurement</span>
                                    <svg
                                        className={`w-4 h-4 transition-transform duration-200 ${dropdownOpen ? 'rotate-180' : ''}`}
                                        viewBox="0 0 24 24"
                                    >
                                        <path fill="currentColor" d="M7 10l5 5 5-5H7z" />
                                    </svg>
                                </button>
                                <div
                                    className={`absolute top-full left-0 mt-2 w-52 bg-white dark:bg-gray-900/95 rounded-lg shadow-lg border border-gray-100 dark:border-gray-800 transition-all duration-200 
                                        ${dropdownOpen ? 'opacity-100 visible transform-none' : 'opacity-0 invisible translate-y-2'}`}
                                    role="menu"
                                >
                                    <div className="py-1.5">
                                        <Link
                                            href={route('bidding')}
                                            className="block px-5 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400"
                                            role="menuitem"
                                            onClick={() => setDropdownOpen(false)}
                                        >
                                            Bidding
                                        </Link>
                                        <Link
                                            href={route('procurement')}
                                            className="block px-5 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400"
                                            role="menuitem"
                                            onClick={() => setDropdownOpen(false)}
                                        >
                                            Procurement List
                                        </Link>
                                        <Link
                                            href={route('generate-pr.index')}
                                            className="block px-5 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400"
                                            role="menuitem"
                                            onClick={() => setDropdownOpen(false)}
                                        >
                                            PR Generator
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            {auth?.user ? (
                                <>
                                    {auth.user.role === 'hope' ? (
                                        <Link
                                            href={route('hope.dashboard')}
                                            className="relative inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-all duration-200 shadow-sm hover:shadow group"
                                            aria-label="HOPE Dashboard"
                                        >
                                            <span className="relative z-10">Dashboard</span>
                                            <div className="absolute inset-0 -z-10 bg-white/10 rounded-lg opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-300"></div>
                                        </Link>
                                    ) : auth.user.role === 'bac_secretariat' ? (
                                        <Link
                                            href={route('bac-secretariat.dashboard')}
                                            className="relative inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-all duration-200 shadow-sm hover:shadow group"
                                            aria-label="BAC Secretariat Dashboard"
                                        >
                                            <span className="relative z-10">Dashboard</span>
                                            <div className="absolute inset-0 -z-10 bg-white/10 rounded-lg opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-300"></div>
                                        </Link>
                                    ) : auth.user.role === 'bac_chairman' ? (
                                        <Link
                                            href={route('bac-chairman.dashboard')}
                                            className="relative inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-all duration-200 shadow-sm hover:shadow group"
                                            aria-label="BAC Chairman Dashboard"
                                        >
                                            <span className="relative z-10">Dashboard</span>
                                            <div className="absolute inset-0 -z-10 bg-white/10 rounded-lg opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-300"></div>
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
                                        className="relative inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:from-teal-500 hover:to-teal-400 transition-all duration-200 shadow-sm hover:shadow group"
                                    >
                                        <span className="relative z-10">Get Started</span>
                                        <div className="absolute inset-0 -z-10 bg-white/10 rounded-lg opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-300"></div>
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>

                    <button
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        className="md:hidden p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-700 transition-all duration-200"
                        aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                        aria-expanded={mobileMenuOpen}
                        aria-controls="mobile-menu"
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

            <div
                id="mobile-menu"
                className={`fixed inset-0 bg-gray-900/70 backdrop-blur-sm z-40 md:hidden transition-opacity duration-300 ${mobileMenuOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
                onClick={() => setMobileMenuOpen(false)}
                aria-hidden="true"
            ></div>

            <div
                className={`fixed top-0 right-0 bottom-0 w-[300px] bg-white dark:bg-gray-900 z-50 md:hidden shadow-xl transform transition-transform duration-300 ease-in-out ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}
            >
                <div className="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
                    <Link href={route('home')} className="flex items-center space-x-2" aria-label="Home" onClick={() => setMobileMenuOpen(false)}>
                        <div className="h-9 w-9 rounded-lg overflow-hidden shadow-md">
                            <AppLogoIcon className="w-full h-full object-cover" />
                        </div>
                        <span className="font-bold text-lg bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">ProcuChain</span>
                    </Link>
                    <button
                        onClick={() => setMobileMenuOpen(false)}
                        className="p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none"
                        aria-label="Close menu"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="overflow-y-auto py-4">
                    <form onSubmit={handleSearch} className="px-4 mb-5">
                        <div className="relative">
                            <input
                                type="search"
                                placeholder="Search..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-10 pr-4 py-3 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg
                                    focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent
                                    transition-all duration-300 ease-in-out"
                            />
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        </div>
                    </form>

                    <nav className="flex flex-col">
                        <Link
                            href={route('home')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3.5 mx-2 mb-1 rounded-lg flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('home') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">Home</span>
                        </Link>
                        <Link
                            href={route('bidding')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3.5 mx-2 mb-1 rounded-lg flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('bidding') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">Bidding</span>
                        </Link>
                        <Link
                            href={route('procurement')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3.5 mx-2 mb-1 rounded-lg flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('procurement') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">Procurement</span>
                        </Link>
                        <Link
                            href={route('generate-pr.index')}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`px-4 py-3.5 mx-2 mb-1 rounded-lg flex items-center text-gray-700 dark:text-gray-300 hover:bg-teal-50 dark:hover:bg-teal-900/20 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 ${route().current('generate-pr.index') ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 font-medium' : ''}`}
                        >
                            <span className="font-medium">PR Generator</span>
                        </Link>

                        <div className="mt-6 px-4 pt-5 border-t border-gray-100 dark:border-gray-800">
                            {auth?.user ? (
                                auth.user.role === 'hope' ? (
                                    <Link
                                        href={route('hope.dashboard')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-3 px-4 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white hover:from-teal-500 hover:to-teal-400 transition-colors duration-200 shadow-sm"
                                    >
                                        Dashboard
                                    </Link>
                                ) : auth.user.role === 'bac_secretariat' ? (
                                    <Link
                                        href={route('bac-secretariat.dashboard')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-3 px-4 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white hover:from-teal-500 hover:to-teal-400 transition-colors duration-200 shadow-sm"
                                    >
                                        Dashboard
                                    </Link>
                                ) : auth.user.role === 'bac_chairman' ? (
                                    <Link
                                        href={route('bac-chairman.dashboard')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-3 px-4 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white hover:from-teal-500 hover:to-teal-400 transition-colors duration-200 shadow-sm"
                                    >
                                        Dashboard
                                    </Link>
                                ) : null
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-3 px-4 text-center rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block w-full py-3 px-4 text-center rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 text-white mt-3 hover:from-teal-500 hover:to-teal-400 transition-colors duration-200 shadow-sm"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </div>
            </div>
        </>
    );
}
