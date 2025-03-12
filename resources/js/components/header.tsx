import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);

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

    return (
        <header className={`fixed top-0 left-0 right-0 z-50 backdrop-blur-xl w-full ${scrolled
            ? "bg-white/95 dark:bg-gray-900/95 py-3 shadow-lg"
            : "bg-white/80 dark:bg-gray-900/80 py-4"
            } px-6 md:px-12 border-b border-gray-100/50 dark:border-gray-800/50 transition-all duration-300 ease-in-out`}>
            <div className="max-w-7xl mx-auto flex items-center justify-between">
                {/* Logo section with enhanced animation */}
                <Link href={route('home')} className="flex items-center space-x-3 group">
                    <div className="h-11 w-11 rounded-xl bg-gradient-to-tr from-teal-600 via-teal-500 to-teal-400 flex items-center justify-center overflow-hidden relative transform transition-all duration-300 group-hover:scale-105 group-hover:rotate-3 shadow-lg">
                        <span className="text-white font-bold text-xl relative z-10 group-hover:scale-110 transition-transform duration-300">P</span>
                        <div className="absolute inset-0 bg-gradient-to-br from-teal-400 to-teal-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rotate-180"></div>
                    </div>
                    <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent group-hover:to-teal-500 transition-all duration-300">ProcuChain</span>
                </Link>

                {/* Navigation with enhanced hover effects */}
                <nav className="flex items-center gap-6">
                    <Link
                        href={route('home')}
                        className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                    >
                        <span className="relative z-10">Home</span>
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
                                    className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                                >
                                    <span className="relative z-10">Dashboard</span>
                                    <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                                </Link>
                            ) : auth.user.role === 'bac_secretariat' ? (
                                <Link
                                    href={route('bac-secretariat.dashboard')}
                                    className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                                >
                                    <span className="relative z-10">Dashboard</span>
                                    <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
                                </Link>
                            ) : auth.user.role === 'bac_chairman' ? (
                                <Link
                                    href={route('bac-chairman.dashboard')}
                                    className="relative px-4 py-2 font-medium text-sm text-gray-700 dark:text-gray-300 transition-all duration-300 hover:text-teal-600 dark:hover:text-teal-400 group"
                                >
                                    <span className="relative z-10">Dashboard</span>
                                    <div className="absolute inset-0 bg-teal-50 dark:bg-teal-900/30 rounded-lg scale-90 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-300"></div>
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
                                className="relative px-6 py-2.5 text-sm font-medium text-white group"
                            >
                                <span className="absolute inset-0 bg-gradient-to-r from-teal-600 to-teal-500 rounded-full transition-all duration-300 group-hover:scale-105"></span>
                                <span className="relative z-10 flex items-center">
                                    Get Started
                                    <svg className="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </span>
                                <div className="absolute inset-0 rounded-full shadow-lg shadow-teal-500/25 dark:shadow-teal-700/25 transition-opacity duration-300 opacity-0 group-hover:opacity-100"></div>
                            </Link>
                        </>
                    )}
                </nav>
            </div>
        </header>
    );
}
