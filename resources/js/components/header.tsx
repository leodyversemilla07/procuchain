import { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { Menu, X, Search, Sun, Moon, Monitor, Loader2 } from 'lucide-react';
import { type SharedData } from '@/types';
import AppLogoIcon from './app-logo-icon';
import { useDebounce } from '@uidotdev/usehooks';
import { useAppearance } from '@/hooks/use-appearance';
import axios from 'axios';

interface Suggestion {
    id: string | number;
    title: string;
    link: string;
    type: 'Page' | 'Procurement';
}

function getDashboardRouteByRole(role: string): string {
    switch (role) {
        case 'hope':
            return route('hope.dashboard');
        case 'bac_secretariat':
            return route('bac-secretariat.dashboard');
        case 'bac_chairman':
            return route('bac-chairman.dashboard');
        case 'admin':
            return route('admin.dashboard');
        default:
            return route('home');
    }
}

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const { appearance, updateAppearance } = useAppearance();
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [isSuggestionsLoading, setIsSuggestionsLoading] = useState(false);
    const [isSuggestionsVisible, setIsSuggestionsVisible] = useState(false);
    const [isMobileSearchExpanded, setIsMobileSearchExpanded] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [isLoading, setIsLoading] = useState(true);

    const debouncedSearchQuery = useDebounce(searchQuery, 300);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);
    const mobileSearchInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const timer = setTimeout(() => setIsLoading(false), 500);
        return () => clearTimeout(timer);
    }, []);

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

    const cycleAppearance = () => {
        const nextAppearance = appearance === 'light' ? 'dark' : appearance === 'dark' ? 'system' : 'light';
        updateAppearance(nextAppearance);
    };

    const getThemeIcon = () => {
        switch (appearance) {
            case 'light':
                return Sun;
            case 'dark':
                return Moon;
            case 'system':
                return Monitor;
            default:
                return Sun;
        }
    };

    const getThemeLabel = () => {
        switch (appearance) {
            case 'light':
                return 'Switch to dark mode';
            case 'dark':
                return 'Switch to system mode';
            case 'system':
                return 'Switch to light mode';
            default:
                return 'Switch theme';
        }
    };

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
    if (isLoading) {
        return (
            <>
                <div className="fixed top-0 left-0 right-0 z-40 bg-background h-[52px] border-b border-border">
                    <div className="max-w-7xl mx-auto h-full flex items-center justify-between px-3 sm:px-4 md:px-6 lg:px-8">
                        <div className="flex items-center space-x-3">
                            <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-muted animate-pulse"></div>
                            <div className="h-6 w-28 bg-muted rounded-md animate-pulse"></div>
                        </div>
                        <div className="hidden lg:flex items-center justify-center flex-grow">
                            <div className="flex items-center space-x-1">
                                {[...Array(4)].map((_, i) => (
                                    <div key={i} className="h-8 w-16 bg-muted rounded-md animate-pulse"></div>
                                ))}
                            </div>
                        </div>
                        <div className="flex items-center space-x-2">
                            <div className="hidden xl:block h-8 w-40 xl:w-48 bg-muted rounded-md animate-pulse"></div>
                            <div className="hidden md:block h-8 w-8 bg-muted rounded-md animate-pulse"></div>
                            <div className="hidden lg:block h-8 w-16 bg-muted rounded-md animate-pulse"></div>
                            <div className="lg:hidden h-8 w-8 bg-muted rounded-md animate-pulse"></div>
                            <div className="lg:hidden h-8 w-8 bg-muted rounded-md animate-pulse"></div>
                        </div>
                    </div>
                </div>

                {/* Spacing div to prevent layout shift */}
                <div className="h-[52px]"></div>
            </>
        );
    }

    return (<>
        <header
            className={`fixed top-0 left-0 right-0 z-40 w-full transition-all duration-200
                    ${scrolled
                    ? "bg-background/80 backdrop-blur-sm shadow-md border-b border-border"
                    : "bg-background"}`}
        >
            <div className="max-w-7xl mx-auto flex items-center justify-between px-3 sm:px-4 md:px-6 lg:px-8 h-[52px]">
                <Link
                    href={route('home')}
                    className="flex items-center space-x-3 group flex-shrink-0"
                    aria-label="ProcuChain Home"
                >
                    <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-xl overflow-hidden transform transition-transform duration-200 group-hover:scale-105">
                        <AppLogoIcon className="w-full h-full object-cover" />
                    </div>
                    <span className="font-medium text-lg sm:text-xl text-foreground">
                        ProcuChain
                    </span>
                </Link><nav className="hidden lg:flex items-center justify-center flex-grow" role="navigation" aria-label="Main navigation">
                    <div className="flex items-center space-x-6 md:space-x-8">
                        <NavLink href={route('home')} active={route().current('home')}>Home</NavLink>
                        <NavLink href={route('about')} active={route().current('about')}>About</NavLink>
                        <NavLink href={route('team')} active={route().current('team')}>Team</NavLink>
                        <NavLink href={route('contact')} active={route().current('contact')}>Contact</NavLink>
                    </div>
                </nav>

                <div className="flex items-center space-x-2">
                    {/* Desktop Search */}
                    <div
                        className="relative hidden xl:block"
                        ref={dropdownRef}
                    >
                        <form
                            onSubmit={handleSearchSubmit}
                            className="relative"
                            role="search"
                            aria-label="Site search"
                        >
                            <div className="relative">
                                <input
                                    ref={searchInputRef}
                                    type="search"
                                    placeholder="Search..."
                                    value={searchQuery}
                                    onChange={handleSearchInputChange}
                                    onFocus={() => { if (searchQuery.trim().length > 1) setIsSuggestionsVisible(true); }}
                                    onKeyDown={handleKeyDown} className="w-40 xl:w-48 pl-8 pr-3 py-1.5 text-sm bg-muted rounded-md
                                            focus:outline-none focus:ring-1 focus:ring-ring focus:bg-card
                                            transition-all duration-200"
                                    aria-label="Search"
                                    aria-expanded={isSuggestionsVisible}
                                    aria-controls="search-suggestions"
                                    aria-activedescendant={activeIndex >= 0 ? `suggestion-${suggestions[activeIndex]?.id}` : undefined}
                                    autoComplete="off"
                                />
                                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                                {isSuggestionsLoading && (
                                    <Loader2 className="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground animate-spin" />
                                )}
                            </div>
                        </form>

                        {/* Suggestions Dropdown */}
                        {isSuggestionsVisible && suggestions.length > 0 && (
                            <div
                                id="search-suggestions"
                                className="absolute mt-1 w-64 max-h-72 overflow-y-auto bg-card rounded-md shadow-lg border border-border
                                        transform transition-all duration-150 ease-out"
                                role="listbox"
                            >
                                <ul>
                                    {suggestions.map((suggestion, index) => (
                                        <li key={suggestion.id} role="option" id={`suggestion-${suggestion.id}`} aria-selected={index === activeIndex}>
                                            <button
                                                type="button"
                                                onClick={() => handleSuggestionClick(suggestion)} className={`w-full text-left px-3 py-2 flex items-center space-x-2 text-sm transition-colors duration-150
                                                        ${index === activeIndex
                                                        ? 'bg-accent'
                                                        : 'hover:bg-accent'}
                                                    `}
                                            >
                                                <span className="flex-grow truncate text-foreground">{suggestion.title}</span>
                                                <span className="flex-shrink-0 px-1.5 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                                                    {suggestion.type}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>                    {/* Theme Toggle */}
                    <button
                        onClick={cycleAppearance}
                        className="hidden md:flex p-1.5 rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground
                                focus:outline-none focus:ring-1 focus:ring-ring transition-colors duration-200"
                        aria-label={getThemeLabel()}
                    >
                        {(() => {
                            const Icon = getThemeIcon();
                            return <Icon className="w-4 h-4" />;
                        })()}
                    </button>

                    {/* Auth Buttons */}
                    <div className="hidden lg:flex items-center space-x-2">
                        {auth.user ? (
                            <Link
                                href={getDashboardRouteByRole(auth.user.role)}
                                className="px-3 py-1.5 rounded-md text-sm font-medium text-primary-foreground
                                        bg-primary hover:bg-primary/90
                                        focus:outline-none focus:ring-1 focus:ring-ring
                                        transition-colors duration-200"
                            >
                                Dashboard
                            </Link>) : (
                            <Link
                                href={route('login')}
                                className="px-3 py-1.5 rounded-md text-sm font-medium text-primary-foreground
                                        bg-primary hover:bg-primary/90
                                        focus:outline-none focus:ring-1 focus:ring-ring
                                        transition-colors duration-200"
                            >
                                Sign In
                            </Link>
                        )}
                    </div>

                    {/* Mobile Search Button */}
                    <button
                        onClick={() => {
                            setIsMobileSearchExpanded(true);
                            setTimeout(() => mobileSearchInputRef.current?.focus(), 100);
                        }}
                        className="lg:hidden p-1.5 rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground
                                focus:outline-none focus:ring-1 focus:ring-ring"
                        aria-label="Open search"
                    >
                        <Search className="w-4 h-4" />
                    </button>

                    {/* Mobile Menu Button */}
                    <button
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        className="lg:hidden p-1.5 rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground
                                focus:outline-none focus:ring-1 focus:ring-ring"
                        aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                        aria-expanded={mobileMenuOpen}
                    >
                        <div className="w-4 h-4 flex items-center justify-center">
                            {mobileMenuOpen ? <X size={16} /> : <Menu size={16} />}
                        </div>
                    </button>
                </div>
            </div>

            {/* Mobile Search Expanded View */}
            <div
                className={`absolute top-0 left-0 right-0 bg-background shadow-md border-b border-border p-3 sm:p-4 md:p-6 lg:p-8 transition-transform duration-300 ease-in-out lg:hidden
                        ${isMobileSearchExpanded ? 'translate-y-0' : '-translate-y-full'}`}
                ref={dropdownRef}
            >
                <form onSubmit={handleSearchSubmit} className="relative" role="search" aria-label="Mobile site search">
                    <input
                        ref={mobileSearchInputRef}
                        type="search"
                        placeholder="Search site..."
                        value={searchQuery}
                        onChange={handleSearchInputChange}
                        onFocus={() => { if (searchQuery.trim().length > 1) setIsSuggestionsVisible(true); }}
                        onKeyDown={handleKeyDown} className="w-full pl-9 pr-10 py-2 text-sm bg-muted rounded-lg
                                focus:outline-none focus:ring-2 focus:ring-ring focus:bg-card"
                        aria-label="Search"
                        aria-expanded={isSuggestionsVisible}
                        aria-controls="mobile-search-suggestions"
                        aria-activedescendant={activeIndex >= 0 ? `mobile-suggestion-${suggestions[activeIndex]?.id}` : undefined}
                        autoComplete="off"
                    />                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 text-muted-foreground pointer-events-none" />
                    {isSuggestionsLoading && (
                        <Loader2 className="absolute right-10 top-1/2 -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 text-muted-foreground animate-spin" />
                    )}
                    <button
                        type="button"
                        onClick={() => setIsMobileSearchExpanded(false)}
                        className="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent"
                        aria-label="Close search"
                    >
                        <X size={16} />
                    </button>
                </form>
                {/* Mobile Suggestions Dropdown */}
                {isSuggestionsVisible && suggestions.length > 0 && (
                    <div
                        id="mobile-search-suggestions"
                        className="mt-2 max-h-60 overflow-y-auto bg-card rounded-lg shadow-md border border-border
                                transform transition-all duration-200 ease-out"
                        role="listbox"
                    >
                        <ul>
                            {suggestions.map((suggestion, index) => (
                                <li key={suggestion.id} role="option" id={`mobile-suggestion-${suggestion.id}`} aria-selected={index === activeIndex}>
                                    <button
                                        type="button"
                                        onClick={() => handleSuggestionClick(suggestion)} className={`w-full text-left px-3 sm:px-4 py-2 sm:py-3 flex items-center space-x-2 sm:space-x-3 text-sm transition-colors duration-150
                                                ${index === activeIndex
                                                ? 'bg-accent'
                                                : 'hover:bg-accent'}
                                            `}
                                    >
                                        <span className="flex-grow truncate text-foreground">{suggestion.title}</span>
                                        <span className="flex-shrink-0 px-1.5 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                                            {suggestion.type}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {isSuggestionsVisible && !isSuggestionsLoading && suggestions.length === 0 && searchQuery.trim().length > 1 && (
                    <div className="mt-2 bg-card rounded-lg shadow-md border border-border p-3 sm:p-4 text-sm text-muted-foreground text-center">
                        No suggestions found.
                    </div>
                )}
            </div>

            {/* Mobile Menu */}
            <div
                className={`fixed inset-0 z-50 transform transition-transform duration-300 ease-in-out lg:hidden
                        ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}
                style={{ backgroundColor: 'var(--background, #ffffff)' }}
            >
                <div className="flex items-center justify-between p-3 sm:p-4 md:p-6 lg:p-8 border-b border-border">
                    <Link
                        href={route('home')}
                        className="flex items-center space-x-3"
                        onClick={resetSearchState}
                    >
                        <div className="h-8 w-8 sm:h-10 sm:w-10 rounded-xl overflow-hidden">
                            <AppLogoIcon className="w-full h-full object-cover" />
                        </div>
                        <span className="font-medium text-lg sm:text-xl text-foreground">
                            ProcuChain
                        </span>
                    </Link>                    <div className="flex items-center space-x-2">
                        <button
                            onClick={cycleAppearance}
                            className="p-1.5 sm:p-2 rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            aria-label={getThemeLabel()}
                        >
                            {(() => {
                                const Icon = getThemeIcon();
                                return <Icon className="w-4 h-4 sm:w-5 sm:h-5" />;
                            })()}
                        </button>
                        <button
                            onClick={() => setMobileMenuOpen(false)}
                            className="p-1.5 sm:p-2 rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            aria-label="Close menu"
                        >
                            <X size={18} />
                        </button>
                    </div>
                </div>
                <nav className="space-y-4 mt-6 px-3 sm:px-4 md:px-6 lg:px-8" role="navigation" aria-label="Mobile navigation">
                    <MobileNavLink href={route('home')} active={route().current('home')} onClick={resetSearchState}>Home</MobileNavLink>
                    <MobileNavLink href={route('about')} active={route().current('about')} onClick={resetSearchState}>About</MobileNavLink>
                    <MobileNavLink href={route('team')} active={route().current('team')} onClick={resetSearchState}>Team</MobileNavLink>
                    <MobileNavLink href={route('contact')} active={route().current('contact')} onClick={resetSearchState}>Contact</MobileNavLink>
                </nav>

                <div className="mt-6 sm:mt-8 px-3 sm:px-4 md:px-6 lg:px-8">
                    {auth.user ? (
                        <Link
                            href={getDashboardRouteByRole(auth.user.role)}
                            onClick={resetSearchState}
                            className="block w-full py-2.5 sm:py-3 text-center font-medium text-primary-foreground
                                    bg-primary hover:bg-primary/90 rounded-md
                                    focus:outline-none focus:ring-1 focus:ring-ring
                                    transition-colors duration-200"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <Link
                            href={route('login')}
                            onClick={resetSearchState}
                            className="block w-full py-2.5 sm:py-3 text-center font-medium text-primary-foreground
                                    bg-primary hover:bg-primary/90 rounded-md
                                    focus:outline-none focus:ring-1 focus:ring-ring
                                    transition-colors duration-200"
                        >
                            Sign In
                        </Link>
                    )}
                </div>
            </div>
        </header>

        {/* Adjust the spacing div to prevent gaps */}
        <div className="h-[52px]"></div>
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
            className={`text-sm font-medium transition-colors duration-200
                ${active
                    ? 'text-foreground'
                    : 'text-muted-foreground hover:text-foreground'}`}
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
}

function MobileNavLink({ href, active, children, onClick }: MobileNavLinkProps) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className={`block px-3 py-3 text-base font-medium transition-colors duration-200
                ${active
                    ? 'text-foreground'
                    : 'text-muted-foreground hover:text-foreground'}`}
            aria-current={active ? 'page' : undefined}
        >
            {children}
        </Link>
    );
}

