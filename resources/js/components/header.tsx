import { useAppearance } from '@/hooks/use-appearance';
import { about, contact, home, login, search as searchRoute, team } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as bacChairmanDashboard } from '@/routes/bac-chairman';
import { dashboard as bacSecretariatDashboard } from '@/routes/bac-secretariat';
import { dashboard as hopeDashboard } from '@/routes/hope';
import { suggestions as searchSuggestions } from '@/routes/search';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useDebounce } from '@uidotdev/usehooks';
import axios from 'axios';
import { Loader2, Menu, Monitor, Moon, Search, Sun, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from './app-logo-icon';

interface Suggestion {
    id: string | number;
    title: string;
    link: string;
    type: 'Page' | 'Procurement';
}

function getDashboardRouteByRole(role: string): string {
    switch (role) {
        case 'hope':
            return hopeDashboard.url();
        case 'bac_secretariat':
            return bacSecretariatDashboard.url();
        case 'bac_chairman':
            return bacChairmanDashboard.url();
        case 'admin':
            return adminDashboard.url();
        default:
            return home.url();
    }
}

export default function Header() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
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

    // Helper to check if a route is currently active
    const isRouteActive = (routeUrl: string): boolean => {
        return page.url === routeUrl || page.url.startsWith(routeUrl + '?');
    };

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
            axios
                .get(searchSuggestions.url(), { params: { query: debouncedSearchQuery } })
                .then((response) => {
                    setSuggestions(response.data.suggestions || []);
                    setIsSuggestionsVisible(true);
                    setActiveIndex(-1);
                })
                .catch((error) => {
                    console.error('Error fetching search suggestions:', error);
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

        router.get(
            searchRoute.url(),
            { query: searchQuery },
            {
                preserveState: true,
                onSuccess: () => resetSearchState(),
            },
        );
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
                setActiveIndex((prev) => (prev + 1) % suggestions.length);
                break;
            case 'ArrowUp':
                e.preventDefault();
                setActiveIndex((prev) => (prev - 1 + suggestions.length) % suggestions.length);
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
                <div className="bg-background border-border fixed top-0 right-0 left-0 z-40 h-[52px] border-b">
                    <div className="mx-auto flex h-full max-w-7xl items-center justify-between px-3 sm:px-4 md:px-6 lg:px-8">
                        <div className="flex items-center space-x-3">
                            <div className="bg-muted h-8 w-8 animate-pulse rounded-xl sm:h-10 sm:w-10"></div>
                            <div className="bg-muted h-6 w-28 animate-pulse rounded-md"></div>
                        </div>
                        <div className="hidden flex-grow items-center justify-center lg:flex">
                            <div className="flex items-center space-x-1">
                                {[...Array(4)].map((_, i) => (
                                    <div key={i} className="bg-muted h-8 w-16 animate-pulse rounded-md"></div>
                                ))}
                            </div>
                        </div>
                        <div className="flex items-center space-x-2">
                            <div className="bg-muted hidden h-8 w-40 animate-pulse rounded-md xl:block xl:w-48"></div>
                            <div className="bg-muted hidden h-8 w-8 animate-pulse rounded-md md:block"></div>
                            <div className="bg-muted hidden h-8 w-16 animate-pulse rounded-md lg:block"></div>
                            <div className="bg-muted h-8 w-8 animate-pulse rounded-md lg:hidden"></div>
                            <div className="bg-muted h-8 w-8 animate-pulse rounded-md lg:hidden"></div>
                        </div>
                    </div>
                </div>

                {/* Spacing div to prevent layout shift */}
                <div className="h-[52px]"></div>
            </>
        );
    }

    return (
        <>
            <header
                className={`fixed top-0 right-0 left-0 z-40 w-full transition-all duration-200 ${
                    scrolled ? 'bg-background/80 border-border border-b shadow-md backdrop-blur-sm' : 'bg-background'
                }`}
            >
                <div className="mx-auto flex h-[52px] max-w-7xl items-center justify-between px-3 sm:px-4 md:px-6 lg:px-8">
                    <Link href={home.url()} className="group flex flex-shrink-0 items-center space-x-3" aria-label="ProcuChain Home">
                        <div className="h-8 w-8 transform overflow-hidden rounded-xl transition-transform duration-200 group-hover:scale-105 sm:h-10 sm:w-10">
                            <AppLogoIcon className="h-full w-full object-cover" />
                        </div>
                        <span className="text-foreground text-lg font-medium sm:text-xl">ProcuChain</span>
                    </Link>
                    <nav className="hidden flex-grow items-center justify-center lg:flex" role="navigation" aria-label="Main navigation">
                        <div className="flex items-center space-x-6 md:space-x-8">
                            <NavLink href={home.url()} active={isRouteActive(home.url())}>
                                Home
                            </NavLink>
                            <NavLink href={about.url()} active={isRouteActive(about.url())}>
                                About
                            </NavLink>
                            <NavLink href={team.url()} active={isRouteActive(team.url())}>
                                Team
                            </NavLink>
                            <NavLink href={contact.url()} active={isRouteActive(contact.url())}>
                                Contact
                            </NavLink>
                        </div>
                    </nav>

                    <div className="flex items-center space-x-2">
                        {/* Desktop Search */}
                        <div className="relative hidden xl:block" ref={dropdownRef}>
                            <form onSubmit={handleSearchSubmit} className="relative" role="search" aria-label="Site search">
                                <div className="relative">
                                    <input
                                        ref={searchInputRef}
                                        type="search"
                                        placeholder="Search..."
                                        value={searchQuery}
                                        onChange={handleSearchInputChange}
                                        onFocus={() => {
                                            if (searchQuery.trim().length > 1) setIsSuggestionsVisible(true);
                                        }}
                                        onKeyDown={handleKeyDown}
                                        className="bg-muted focus:ring-ring focus:bg-card w-40 rounded-md py-1.5 pr-3 pl-8 text-sm transition-all duration-200 focus:ring-1 focus:outline-none xl:w-48"
                                        aria-label="Search"
                                        aria-expanded={isSuggestionsVisible}
                                        aria-controls="search-suggestions"
                                        aria-activedescendant={activeIndex >= 0 ? `suggestion-${suggestions[activeIndex]?.id}` : undefined}
                                        autoComplete="off"
                                    />
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2" />
                                    {isSuggestionsLoading && (
                                        <Loader2 className="text-muted-foreground absolute top-1/2 right-2.5 h-4 w-4 -translate-y-1/2 animate-spin" />
                                    )}
                                </div>
                            </form>

                            {/* Suggestions Dropdown */}
                            {isSuggestionsVisible && suggestions.length > 0 && (
                                <div
                                    id="search-suggestions"
                                    className="bg-card border-border absolute mt-1 max-h-72 w-64 transform overflow-y-auto rounded-md border shadow-lg transition-all duration-150 ease-out"
                                    role="listbox"
                                >
                                    <ul>
                                        {suggestions.map((suggestion, index) => (
                                            <li
                                                key={suggestion.id}
                                                role="option"
                                                id={`suggestion-${suggestion.id}`}
                                                aria-selected={index === activeIndex}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => handleSuggestionClick(suggestion)}
                                                    className={`flex w-full items-center space-x-2 px-3 py-2 text-left text-sm transition-colors duration-150 ${
                                                        index === activeIndex ? 'bg-accent' : 'hover:bg-accent'
                                                    } `}
                                                >
                                                    <span className="text-foreground flex-grow truncate">{suggestion.title}</span>
                                                    <span className="bg-muted text-muted-foreground flex-shrink-0 rounded px-1.5 py-0.5 text-xs font-medium">
                                                        {suggestion.type}
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>{' '}
                        {/* Theme Toggle */}
                        <button
                            onClick={cycleAppearance}
                            className="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus:ring-ring hidden rounded-md p-1.5 transition-colors duration-200 focus:ring-1 focus:outline-none md:flex"
                            aria-label={getThemeLabel()}
                        >
                            {(() => {
                                const Icon = getThemeIcon();
                                return <Icon className="h-4 w-4" />;
                            })()}
                        </button>
                        {/* Auth Buttons */}
                        <div className="hidden items-center space-x-2 lg:flex">
                            {auth.user ? (
                                <Link
                                    href={getDashboardRouteByRole(auth.roles?.[0] || auth.user.role)}
                                    className="text-primary-foreground bg-primary hover:bg-primary/90 focus:ring-ring rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-200 focus:ring-1 focus:outline-none"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login.url()}
                                    className="text-primary-foreground bg-primary hover:bg-primary/90 focus:ring-ring rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-200 focus:ring-1 focus:outline-none"
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
                            className="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus:ring-ring rounded-md p-1.5 focus:ring-1 focus:outline-none lg:hidden"
                            aria-label="Open search"
                        >
                            <Search className="h-4 w-4" />
                        </button>
                        {/* Mobile Menu Button */}
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus:ring-ring rounded-md p-1.5 focus:ring-1 focus:outline-none lg:hidden"
                            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={mobileMenuOpen}
                        >
                            <div className="flex h-4 w-4 items-center justify-center">{mobileMenuOpen ? <X size={16} /> : <Menu size={16} />}</div>
                        </button>
                    </div>
                </div>

                {/* Mobile Search Expanded View */}
                <div
                    className={`bg-background border-border absolute top-0 right-0 left-0 border-b p-3 shadow-md transition-transform duration-300 ease-in-out sm:p-4 md:p-6 lg:hidden lg:p-8 ${isMobileSearchExpanded ? 'translate-y-0' : '-translate-y-full'}`}
                    ref={dropdownRef}
                >
                    <form onSubmit={handleSearchSubmit} className="relative" role="search" aria-label="Mobile site search">
                        <input
                            ref={mobileSearchInputRef}
                            type="search"
                            placeholder="Search site..."
                            value={searchQuery}
                            onChange={handleSearchInputChange}
                            onFocus={() => {
                                if (searchQuery.trim().length > 1) setIsSuggestionsVisible(true);
                            }}
                            onKeyDown={handleKeyDown}
                            className="bg-muted focus:ring-ring focus:bg-card w-full rounded-lg py-2 pr-10 pl-9 text-sm focus:ring-2 focus:outline-none"
                            aria-label="Search"
                            aria-expanded={isSuggestionsVisible}
                            aria-controls="mobile-search-suggestions"
                            aria-activedescendant={activeIndex >= 0 ? `mobile-suggestion-${suggestions[activeIndex]?.id}` : undefined}
                            autoComplete="off"
                        />{' '}
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 sm:h-5 sm:w-5" />
                        {isSuggestionsLoading && (
                            <Loader2 className="text-muted-foreground absolute top-1/2 right-10 h-4 w-4 -translate-y-1/2 animate-spin sm:h-5 sm:w-5" />
                        )}
                        <button
                            type="button"
                            onClick={() => setIsMobileSearchExpanded(false)}
                            className="text-muted-foreground hover:text-foreground hover:bg-accent absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5"
                            aria-label="Close search"
                        >
                            <X size={16} />
                        </button>
                    </form>
                    {/* Mobile Suggestions Dropdown */}
                    {isSuggestionsVisible && suggestions.length > 0 && (
                        <div
                            id="mobile-search-suggestions"
                            className="bg-card border-border mt-2 max-h-60 transform overflow-y-auto rounded-lg border shadow-md transition-all duration-200 ease-out"
                            role="listbox"
                        >
                            <ul>
                                {suggestions.map((suggestion, index) => (
                                    <li
                                        key={suggestion.id}
                                        role="option"
                                        id={`mobile-suggestion-${suggestion.id}`}
                                        aria-selected={index === activeIndex}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => handleSuggestionClick(suggestion)}
                                            className={`flex w-full items-center space-x-2 px-3 py-2 text-left text-sm transition-colors duration-150 sm:space-x-3 sm:px-4 sm:py-3 ${
                                                index === activeIndex ? 'bg-accent' : 'hover:bg-accent'
                                            } `}
                                        >
                                            <span className="text-foreground flex-grow truncate">{suggestion.title}</span>
                                            <span className="bg-muted text-muted-foreground flex-shrink-0 rounded px-1.5 py-0.5 text-xs font-medium">
                                                {suggestion.type}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {isSuggestionsVisible && !isSuggestionsLoading && suggestions.length === 0 && searchQuery.trim().length > 1 && (
                        <div className="bg-card border-border text-muted-foreground mt-2 rounded-lg border p-3 text-center text-sm shadow-md sm:p-4">
                            No suggestions found.
                        </div>
                    )}
                </div>

                {/* Mobile Menu */}
                <div
                    className={`fixed inset-0 z-50 transform transition-transform duration-300 ease-in-out lg:hidden ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}
                    style={{ backgroundColor: 'var(--background, #ffffff)' }}
                >
                    <div className="border-border flex items-center justify-between border-b p-3 sm:p-4 md:p-6 lg:p-8">
                        <Link href={home.url()} className="flex items-center space-x-3" onClick={resetSearchState}>
                            <div className="h-8 w-8 overflow-hidden rounded-xl sm:h-10 sm:w-10">
                                <AppLogoIcon className="h-full w-full object-cover" />
                            </div>
                            <span className="text-foreground text-lg font-medium sm:text-xl">ProcuChain</span>
                        </Link>{' '}
                        <div className="flex items-center space-x-2">
                            <button
                                onClick={cycleAppearance}
                                className="text-muted-foreground hover:bg-accent hover:text-accent-foreground rounded-lg p-1.5 sm:p-2"
                                aria-label={getThemeLabel()}
                            >
                                {(() => {
                                    const Icon = getThemeIcon();
                                    return <Icon className="h-4 w-4 sm:h-5 sm:w-5" />;
                                })()}
                            </button>
                            <button
                                onClick={() => setMobileMenuOpen(false)}
                                className="text-muted-foreground hover:bg-accent hover:text-accent-foreground rounded-lg p-1.5 sm:p-2"
                                aria-label="Close menu"
                            >
                                <X size={18} />
                            </button>
                        </div>
                    </div>
                    <nav className="mt-6 space-y-4 px-3 sm:px-4 md:px-6 lg:px-8" role="navigation" aria-label="Mobile navigation">
                        <MobileNavLink href={home.url()} active={isRouteActive(home.url())} onClick={resetSearchState}>
                            Home
                        </MobileNavLink>
                        <MobileNavLink href={about.url()} active={isRouteActive(about.url())} onClick={resetSearchState}>
                            About
                        </MobileNavLink>
                        <MobileNavLink href={team.url()} active={isRouteActive(team.url())} onClick={resetSearchState}>
                            Team
                        </MobileNavLink>
                        <MobileNavLink href={contact.url()} active={isRouteActive(contact.url())} onClick={resetSearchState}>
                            Contact
                        </MobileNavLink>
                    </nav>

                    <div className="mt-6 px-3 sm:mt-8 sm:px-4 md:px-6 lg:px-8">
                        {auth.user ? (
                            <Link
                                href={getDashboardRouteByRole(auth.roles?.[0] || auth.user.role)}
                                onClick={resetSearchState}
                                className="text-primary-foreground bg-primary hover:bg-primary/90 focus:ring-ring block w-full rounded-md py-2.5 text-center font-medium transition-colors duration-200 focus:ring-1 focus:outline-none sm:py-3"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={login.url()}
                                onClick={resetSearchState}
                                className="text-primary-foreground bg-primary hover:bg-primary/90 focus:ring-ring block w-full rounded-md py-2.5 text-center font-medium transition-colors duration-200 focus:ring-1 focus:outline-none sm:py-3"
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
            className={`text-sm font-medium transition-colors duration-200 ${
                active ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'
            }`}
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
            className={`block px-3 py-3 text-base font-medium transition-colors duration-200 ${
                active ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'
            }`}
            aria-current={active ? 'page' : undefined}
        >
            {children}
        </Link>
    );
}
