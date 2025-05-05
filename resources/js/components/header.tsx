import { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { Menu, X, Search, Home, Info, Layers, Code, Users, FileArchive, Mail, FileText, Package, Loader2 } from 'lucide-react';
import { type SharedData } from '@/types';
import AppLogoIcon from './app-logo-icon';
import axios from 'axios';
import { useDebounce } from '@uidotdev/usehooks';

interface Suggestion {
    id: string | number;
    title: string;
    link: string;
    type: 'Page' | 'Procurement';
}

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [isSuggestionsLoading, setIsSuggestionsLoading] = useState(false);
    const [isSuggestionsVisible, setIsSuggestionsVisible] = useState(false);
    const [isMobileSearchExpanded, setIsMobileSearchExpanded] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);

    const debouncedSearchQuery = useDebounce(searchQuery, 300);

    const dropdownRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);
    const mobileSearchInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsSuggestionsVisible(false);
            }
        };
        if (isSuggestionsVisible) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isSuggestionsVisible]);

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

    useEffect(() => {
        if (debouncedSearchQuery.trim().length > 1) {
            setIsSuggestionsLoading(true);
            axios.get(route('search.suggestions'), { params: { query: debouncedSearchQuery } })
                .then(response => {
                    setSuggestions(response.data.suggestions || []);
                    setIsSuggestionsVisible(true);
                    setActiveIndex(-1);
                })
                .catch(error => {
                    console.error("Error fetching search suggestions:", error);
                    setSuggestions([]);
                    setIsSuggestionsVisible(false);
                })
                .finally(() => {
                    setIsSuggestionsLoading(false);
                });
        } else {
            setSuggestions([]);
            setIsSuggestionsVisible(false);
        }
    }, [debouncedSearchQuery]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!searchQuery.trim()) return;

        if (activeIndex >= 0 && activeIndex < suggestions.length) {
            const selectedSuggestion = suggestions[activeIndex];
            router.visit(selectedSuggestion.link);
            resetSearchState();
            return;
        }

        router.get(route('search'), { query: searchQuery }, {
            preserveState: true,
            onSuccess: () => resetSearchState(),
        });
    };

    const handleSuggestionClick = (suggestion: Suggestion) => {
        router.visit(suggestion.link);
        resetSearchState();
    };

    const resetSearchState = () => {
        setSearchQuery('');
        setSuggestions([]);
        setIsSuggestionsVisible(false);
        setActiveIndex(-1);
        setIsMobileSearchExpanded(false);
        setMobileMenuOpen(false);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!isSuggestionsVisible || suggestions.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setActiveIndex(prev => (prev + 1) % suggestions.length);
                break;
            case 'ArrowUp':
                e.preventDefault();
                setActiveIndex(prev => (prev - 1 + suggestions.length) % suggestions.length);
                break;
            case 'Enter':
                break;
            case 'Escape':
                setIsSuggestionsVisible(false);
                setActiveIndex(-1);
                break;
        }
    };

    const handleSearchInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearchQuery(e.target.value);
        if (!isSuggestionsVisible && e.target.value.trim().length > 1) {
            setIsSuggestionsVisible(true);
        }
    };

    return (
        <>
            <header
                className={`fixed top-0 left-0 right-0 z-40 w-full transition-all duration-300 ease-out
                    ${scrolled
                        ? "bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm py-3 shadow-md"
                        : "bg-white dark:bg-gray-900 py-5"}`}
            >
                <div className="max-w-7xl mx-auto flex items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('home')}
                        className="flex items-center space-x-3 group flex-shrink-0"
                        aria-label="ProcuChain Home"
                    >
                        <div className="h-10 w-10 rounded-xl overflow-hidden shadow-md">
                            <AppLogoIcon className="w-full h-full object-cover" />
                        </div>
                        <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                            ProcuChain
                        </span>
                    </Link>

                    <nav className="hidden md:flex items-center space-x-1 lg:space-x-2">
                        <NavLink href={route('home')} active={route().current('home')}>Home</NavLink>
                        <NavLink href={route('about')} active={route().current('about')}>About</NavLink>
                        <NavLink href={route('features')} active={route().current('features')}>Features</NavLink>
                        <NavLink href={route('development')} active={route().current('development')}>Development</NavLink>
                        <NavLink href={route('team')} active={route().current('team')}>Team</NavLink>
                        <NavLink href={route('documentation')} active={route().current('documentation')}>Documentation</NavLink>
                        <NavLink href={route('contact')} active={route().current('contact')}>Contact</NavLink>
                    </nav>

                    <div className="flex items-center space-x-3">
                        {/* Desktop Search */}
                        <div
                            className="relative hidden lg:block"
                            ref={dropdownRef} // Attach ref to the container div
                        >
                            <form
                                onSubmit={handleSearchSubmit}
                                className="relative"
                            >
                                <div className="relative">
                                    <input
                                        ref={searchInputRef}
                                        type="search"
                                        placeholder="Search site..."
                                        value={searchQuery}
                                        onChange={handleSearchInputChange}
                                        onFocus={() => { if (searchQuery.trim().length > 1) setIsSuggestionsVisible(true); }}
                                        onKeyDown={handleKeyDown}
                                        className="w-48 pl-9 pr-3 py-2 text-sm bg-gray-100 dark:bg-gray-800/70 rounded-lg
                                            focus:outline-none focus:ring-2 focus:ring-teal-500/50 focus:bg-white dark:focus:bg-gray-800
                                            transition-all duration-200"
                                        aria-label="Search"
                                        autoComplete="off"
                                    />
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    {isSuggestionsLoading && (
                                        <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 animate-spin" />
                                    )}
                                </div>
                            </form> {/* End of form */}

                            {/* Suggestions Dropdown (remains inside the container div) */}
                            {isSuggestionsVisible && suggestions.length > 0 && (
                                <div className="absolute mt-2 w-72 max-h-80 overflow-y-auto bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700">
                                    <ul>
                                        {suggestions.map((suggestion, index) => (
                                            <li key={suggestion.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => handleSuggestionClick(suggestion)}
                                                    className={`w-full text-left px-4 py-3 flex items-center space-x-3 text-sm transition-colors duration-150
                                                        ${index === activeIndex
                                                            ? 'bg-teal-50 dark:bg-teal-900/50'
                                                            : 'hover:bg-gray-100 dark:hover:bg-gray-700/50'}
                                                    `}
                                                >
                                                    {suggestion.type === 'Procurement' ? (
                                                        <Package className="w-4 h-4 text-blue-500 flex-shrink-0" />
                                                    ) : (
                                                        <FileText className="w-4 h-4 text-teal-500 flex-shrink-0" />
                                                    )}
                                                    <span className="flex-grow truncate text-gray-800 dark:text-gray-200">{suggestion.title}</span>
                                                    <span className={`flex-shrink-0 px-1.5 py-0.5 rounded text-xs font-medium ${suggestion.type === 'Procurement' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200'}`}>
                                                        {suggestion.type}
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {isSuggestionsVisible && !isSuggestionsLoading && suggestions.length === 0 && searchQuery.trim().length > 1 && (
                                <div className="absolute mt-2 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    No suggestions found.
                                </div>
                            )}
                        </div> {/* End of container div */}

                        {auth?.user ? (
                            <Link
                                href={getDashboardRouteByRole(auth.user.role)}
                                className="hidden md:inline-flex items-center px-4 py-2 text-sm font-medium text-white
                                    bg-teal-600 hover:bg-teal-700 rounded-lg
                                    focus:outline-none focus:ring-2 focus:ring-teal-500/50
                                    transition-colors duration-200"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <div className="hidden md:flex items-center space-x-2">
                                <Link
                                    href={route('login')}
                                    className="inline-flex items-center px-4 py-2 text-sm font-medium
                                        border border-teal-600 text-teal-600 hover:bg-teal-50 rounded-lg
                                        dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20
                                        focus:outline-none focus:ring-2 focus:ring-teal-500/50
                                        transition-colors duration-200"
                                >
                                    Sign In
                                </Link>
                            </div>
                        )}

                        <button
                            onClick={() => {
                                setIsMobileSearchExpanded(true);
                                setTimeout(() => mobileSearchInputRef.current?.focus(), 100);
                            }}
                            className="lg:hidden p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800
                                focus:outline-none focus:ring-2 focus:ring-teal-500/50"
                            aria-label="Open search"
                        >
                            <Search className="w-5 h-5" />
                        </button>

                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800
                                focus:outline-none focus:ring-2 focus:ring-teal-500/50"
                            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={mobileMenuOpen}
                        >
                            <div className="w-5 h-5 flex items-center justify-center">
                                {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
                            </div>
                        </button>
                    </div>
                </div>

                {/* Mobile Search Expanded View */}
                <div
                    className={`absolute top-0 left-0 right-0 bg-white dark:bg-gray-900 shadow-md p-3 transition-transform duration-300 ease-in-out lg:hidden
                        ${isMobileSearchExpanded ? 'translate-y-0' : '-translate-y-full'}`}
                    ref={dropdownRef} // Keep ref here for the mobile container
                >
                    <form onSubmit={handleSearchSubmit} className="relative">
                        <input
                            ref={mobileSearchInputRef}
                            type="search"
                            placeholder="Search site..."
                            value={searchQuery}
                            onChange={handleSearchInputChange}
                            onFocus={() => { if (searchQuery.trim().length > 1) setIsSuggestionsVisible(true); }}
                            onKeyDown={handleKeyDown}
                            className="w-full pl-10 pr-10 py-2.5 text-sm bg-gray-100 dark:bg-gray-800 rounded-lg
                                focus:outline-none focus:ring-2 focus:ring-teal-500 focus:bg-white dark:focus:bg-gray-800"
                            aria-label="Search"
                            autoComplete="off"
                        />
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" />
                        {isSuggestionsLoading && (
                            <Loader2 className="absolute right-10 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 animate-spin" />
                        )}
                        <button
                            type="button"
                            onClick={() => setIsMobileSearchExpanded(false)}
                            className="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                            aria-label="Close search"
                        >
                            <X size={18} />
                        </button>
                    </form>
                    {/* Mobile Suggestions Dropdown */}
                    {isSuggestionsVisible && suggestions.length > 0 && (
                        <div className="mt-2 max-h-60 overflow-y-auto bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                            <ul>
                                {suggestions.map((suggestion, index) => (
                                    <li key={suggestion.id}>
                                        <button
                                            type="button"
                                            onClick={() => handleSuggestionClick(suggestion)}
                                            className={`w-full text-left px-4 py-3 flex items-center space-x-3 text-sm transition-colors duration-150
                                                ${index === activeIndex
                                                    ? 'bg-teal-50 dark:bg-teal-900/50'
                                                    : 'hover:bg-gray-100 dark:hover:bg-gray-700/50'}
                                            `}
                                        >
                                            {suggestion.type === 'Procurement' ? (
                                                <Package className="w-4 h-4 text-blue-500 flex-shrink-0" />
                                            ) : (
                                                <FileText className="w-4 h-4 text-teal-500 flex-shrink-0" />
                                            )}
                                            <span className="flex-grow truncate text-gray-800 dark:text-gray-200">{suggestion.title}</span>
                                            <span className={`flex-shrink-0 px-1.5 py-0.5 rounded text-xs font-medium ${suggestion.type === 'Procurement' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200'}`}>
                                                {suggestion.type}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {isSuggestionsVisible && !isSuggestionsLoading && suggestions.length === 0 && searchQuery.trim().length > 1 && (
                        <div className="mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            No suggestions found.
                        </div>
                    )}
                </div>
            </header>

            <div
                className={`fixed inset-0 z-50 bg-white dark:bg-gray-900 md:hidden transition-transform duration-300 transform overflow-y-auto
                    ${mobileMenuOpen ? 'translate-y-0' : '-translate-y-full'}`}
            >
                <div className="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
                    <Link
                        href={route('home')}
                        className="flex items-center space-x-2"
                        onClick={resetSearchState}
                    >
                        <div className="h-9 w-9 rounded-lg overflow-hidden">
                            <AppLogoIcon className="w-full h-full object-cover" />
                        </div>
                        <span className="font-bold text-lg bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                            ProcuChain
                        </span>
                    </Link>
                    <button
                        onClick={() => setMobileMenuOpen(false)}
                        className="p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-label="Close menu"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="p-4">
                    <nav className="space-y-1 mt-4">
                        <MobileNavLink href={route('home')} active={route().current('home')} onClick={resetSearchState} icon={<Home size={18} className="text-gray-500 dark:text-gray-400" />}>Home</MobileNavLink>
                        <MobileNavLink href={route('about')} active={route().current('about')} onClick={resetSearchState} icon={<Info size={18} className="text-gray-500 dark:text-gray-400" />}>About</MobileNavLink>
                        <MobileNavLink href={route('features')} active={route().current('features')} onClick={resetSearchState} icon={<Layers size={18} className="text-gray-500 dark:text-gray-400" />}>Features</MobileNavLink>
                        <MobileNavLink href={route('development')} active={route().current('development')} onClick={resetSearchState} icon={<Code size={18} className="text-gray-500 dark:text-gray-400" />}>Development</MobileNavLink>
                        <MobileNavLink href={route('team')} active={route().current('team')} onClick={resetSearchState} icon={<Users size={18} className="text-gray-500 dark:text-gray-400" />}>Team</MobileNavLink>
                        <MobileNavLink href={route('documentation')} active={route().current('documentation')} onClick={resetSearchState} icon={<FileArchive size={18} className="text-gray-500 dark:text-gray-400" />}>Documentation</MobileNavLink>
                        <MobileNavLink href={route('contact')} active={route().current('contact')} onClick={resetSearchState} icon={<Mail size={18} className="text-gray-500 dark:text-gray-400" />}>Contact</MobileNavLink>
                    </nav>

                    <div className="mt-8 space-y-3">
                        {auth?.user ? (
                            <Link
                                href={getDashboardRouteByRole(auth.user.role)}
                                onClick={resetSearchState}
                                className="block w-full py-3 text-center font-medium text-white
                                    bg-teal-600 hover:bg-teal-700 rounded-lg"
                            >
                                Access Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                onClick={resetSearchState}
                                className="block w-full py-3 text-center font-medium
                                    border border-teal-600 text-teal-600 hover:bg-teal-50 rounded-lg
                                    dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20"
                            >
                                Sign In
                            </Link>
                        )}
                    </div>
                </div>
            </div>

            <div className={`${scrolled ? 'h-16' : 'h-20'} transition-all duration-300`}></div>
        </>
    );
}

interface NavLinkProps {
    href: string;
    active?: boolean;
    children: React.ReactNode;
}

function NavLink({ href, active, children }: NavLinkProps) {
    return (
        <Link
            href={href}
            className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-200
                ${active
                    ? 'text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-900/30'
                    : 'text-gray-700 dark:text-gray-300 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-gray-100/80 dark:hover:bg-gray-800/80'}`}
            aria-current={active ? 'page' : undefined}
        >
            {children}
        </Link>
    );
}

interface MobileNavLinkProps {
    href: string;
    active?: boolean;
    children: React.ReactNode;
    onClick?: () => void;
    icon?: React.ReactNode;
}

function MobileNavLink({ href, active, children, onClick, icon }: MobileNavLinkProps) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className={`flex items-center p-3 rounded-lg
                ${active
                    ? 'bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'}`}
            aria-current={active ? 'page' : undefined}
        >
            {icon && <span className="mr-3">{icon}</span>}
            <span>{children}</span>
            {active && (
                <div className="ml-auto w-1.5 h-5 rounded-full bg-teal-500"></div>
            )}
        </Link>
    );
}

function getDashboardRouteByRole(role: string): string {
    switch (role) {
        case 'hope':
            return route('hope.dashboard');
        case 'bac_secretariat':
            return route('bac-secretariat.dashboard');
        case 'bac_chairman':
            return route('bac-chairman.dashboard');
        default:
            return route('home');
    }
}
