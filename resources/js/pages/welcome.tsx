import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);

    // Handle scroll effect for header
    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Simple fade-in animation on page load
    useEffect(() => {
        const timer = setTimeout(() => {
            document.querySelector('.fade-in-content')?.classList.add('is-visible');
        }, 100);
        return () => clearTimeout(timer);
    }, []);

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|inter:400,500,600&display=swap" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                {/* Enhanced header with scroll effect */}
                <header className={`fixed top-0 left-0 right-0 z-10 backdrop-blur-lg ${scrolled
                    ? "bg-white/90 dark:bg-gray-900/90 py-3 shadow-md"
                    : "bg-white/70 dark:bg-gray-900/70 py-4"
                    } px-6 md:px-12 border-b border-gray-100 dark:border-gray-800 transition-all duration-300 ease-in-out`}>
                    <div className="max-w-7xl mx-auto flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            {/* Animated logo */}
                            <div className="h-10 w-10 rounded-lg bg-gradient-to-r from-teal-600 to-teal-500 flex items-center justify-center overflow-hidden relative group">
                                <span className="text-white font-bold text-xl relative z-10">P</span>
                                <div className="absolute inset-0 bg-gradient-to-r from-teal-500 to-teal-400 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                            </div>
                            <span className="font-bold text-xl text-teal-600 dark:text-teal-400">ProcuChain</span>
                        </div>

                        <nav className="flex items-center gap-5">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="relative px-5 py-2.5 font-medium text-sm transition-all duration-300 ease-in-out hover:text-teal-600 dark:hover:text-teal-400 after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-teal-600 dark:after:bg-teal-400 after:transition-all after:duration-300"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="relative px-5 py-2.5 font-medium text-sm transition-all duration-300 ease-in-out hover:text-teal-600 dark:hover:text-teal-400 after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-0 hover:after:w-full after:bg-teal-600 dark:after:bg-teal-400 after:transition-all after:duration-300"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-full hover:shadow-lg hover:shadow-teal-500/20 dark:hover:shadow-teal-700/20 transition-all duration-300"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Background elements */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none z-0">
                    <div className="absolute top-[20%] right-[10%] w-64 h-64 bg-teal-300/10 dark:bg-teal-600/5 rounded-full blur-3xl"></div>
                    <div className="absolute bottom-[10%] left-[5%] w-80 h-80 bg-teal-400/10 dark:bg-teal-700/5 rounded-full blur-3xl"></div>
                </div>

                {/* Main content with fade-in effect */}
                <div className="fade-in-content opacity-0 transition-opacity duration-1000 pt-24 pb-12 relative z-1">
                    {/* Enhanced Hero section */}
                    <section className="px-6 md:px-12 pt-16 pb-24">
                        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
                            <div className="lg:col-span-7 space-y-8">
                                <div className="inline-flex items-center px-4 py-2 bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-300 rounded-full text-sm font-medium shadow-sm">
                                    <svg className="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                    </svg>
                                    <span className="animate-pulse-slow">Trusted by leading enterprises</span>
                                </div>

                                <h1 className="text-4xl md:text-5xl xl:text-6xl font-bold tracking-tight leading-tight">
                                    <span className="block mb-2">Revolutionize Your</span>
                                    <span className="bg-gradient-to-r from-teal-600 to-teal-500 bg-clip-text text-transparent">Supply Chain Management</span>
                                </h1>

                                <p className="text-xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-xl">
                                    Secure, transparent, and efficient procurement powered by blockchain technology that brings trust and visibility to every transaction.
                                </p>

                                <div className="flex flex-wrap gap-4 pt-4">
                                    <Link
                                        href={route('register')}
                                        className="px-8 py-4 text-white bg-gradient-to-r from-teal-600 to-teal-500 rounded-lg hover:shadow-xl hover:shadow-teal-500/20 dark:hover:shadow-teal-700/20 transition-all duration-300 font-medium hover:translate-y-[-2px] flex items-center"
                                    >
                                        Start Free Trial
                                        <svg className="w-5 h-5 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                                        </svg>
                                    </Link>
                                    <Link
                                        href="#demo"
                                        className="px-8 py-4 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-300 font-medium flex items-center gap-2 border border-gray-200 dark:border-gray-700 hover:shadow-lg"
                                    >
                                        <svg className="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clipRule="evenodd" />
                                        </svg>
                                        Watch Demo
                                    </Link>
                                </div>

                                <div className="pt-6">
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">Trusted by industry leaders</p>
                                    <div className="flex flex-wrap items-center gap-6 opacity-70">
                                        {['Amazon', 'Microsoft', 'IBM', 'Oracle', 'SAP'].map((company) => (
                                            <span key={company} className="text-gray-500 dark:text-gray-400 font-semibold">{company}</span>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Enhanced Hero image with animation */}
                            <div className="lg:col-span-5 relative aspect-square max-w-lg mx-auto lg:mx-0">
                                <div className="absolute inset-0 rounded-full bg-gradient-to-tr from-teal-100 to-teal-100 dark:from-teal-900/20 dark:to-teal-900/20 animate-pulse-slow"></div>
                                <div className="absolute inset-4 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center shadow-2xl">
                                    <div className="relative w-4/5 h-4/5">
                                        <svg className="w-full h-full text-teal-500" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                            {/* Abstract blockchain illustration */}
                                            <g fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                <circle cx="100" cy="100" r="50" opacity="0.2" className="animate-pulse-slow" />
                                                <circle cx="100" cy="100" r="40" opacity="0.4" />
                                                <circle cx="100" cy="100" r="30" opacity="0.6" />
                                                {[0, 60, 120, 180, 240, 300].map((angle, i) => (
                                                    <g key={i} transform={`rotate(${angle} 100 100)`}>
                                                        <circle cx="100" cy="50" r="8" fill={i % 2 ? "#14b8a6" : "#0d9488"} stroke="none" className="animate-pulse" style={{ animationDelay: `${i * 200}ms` }} />
                                                        <line x1="100" y1="58" x2="100" y2="80" />
                                                    </g>
                                                ))}
                                            </g>
                                        </svg>
                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <span className="text-2xl font-bold text-gray-900 dark:text-white">BlockChain</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="absolute -bottom-6 -right-6 w-24 h-24 bg-teal-200/50 dark:bg-teal-800/20 rounded-full blur-xl"></div>
                            </div>
                        </div>
                    </section>

                    {/* Enhanced Features section */}
                    <section id="features" className="px-6 md:px-12 py-24 bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm">
                        <div className="max-w-7xl mx-auto">
                            <div className="text-center mb-20">
                                <div className="inline-flex items-center px-4 py-2 bg-teal-100/50 dark:bg-teal-900/20 rounded-full text-sm font-medium text-teal-600 dark:text-teal-300 mb-4">
                                    <svg className="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M6.672 1.911a1 1 0 10-1.932.518l.259.966a1 1 0 001.932-.518l-.26-.966zM2.429 4.74a1 1 0 10-.517 1.932l.966.259a1 1 0 00.517-1.932l-.966-.26zm8.814-.569a1 1 0 00-1.415-1.414l-.707.707a1 1 0 101.415 1.415l.707-.708zm-7.071 7.072a1 1 0 00-1.415-1.415l-.707.708a1 1 0 101.415 1.414l.707-.707z" clipRule="evenodd" />
                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Key Features
                                </div>
                                <h2 className="text-3xl md:text-4xl font-bold mb-6">Why Choose ProcuChain</h2>
                                <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    Our blockchain-powered platform delivers unmatched security and efficiency for your procurement processes
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                                {[
                                    {
                                        icon: (
                                            <svg className="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        ),
                                        title: "Secure Transactions",
                                        description: "End-to-end encryption and immutable ledger technology ensures your transactions are secure and tamper-proof."
                                    },
                                    {
                                        icon: (
                                            <svg className="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        ),
                                        title: "Smart Contracts",
                                        description: "Automated execution of agreements with predefined rules, eliminating the need for intermediaries."
                                    },
                                    {
                                        icon: (
                                            <svg className="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        ),
                                        title: "Full Transparency",
                                        description: "Complete visibility into procurement processes with real-time tracking and comprehensive audit trails."
                                    },
                                ].map((feature, index) => (
                                    <div key={index} className="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-500 group hover:scale-[1.02] transform border border-gray-100/50 dark:border-gray-700/50">
                                        <div className="rounded-2xl w-16 h-16 flex items-center justify-center bg-gradient-to-br from-teal-50 to-teal-100 dark:from-teal-900/30 dark:to-teal-800/30 text-teal-600 dark:text-teal-300 mb-6 group-hover:bg-gradient-to-br group-hover:from-teal-600 group-hover:to-teal-500 group-hover:text-white dark:group-hover:from-teal-500 dark:group-hover:to-teal-400 transition-all duration-500 shadow-md">
                                            {feature.icon}
                                        </div>
                                        <h3 className="text-xl font-semibold mb-4 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">{feature.title}</h3>
                                        <p className="text-gray-600 dark:text-gray-300">{feature.description}</p>
                                        <div className="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                                            <a href="#" className="text-sm font-medium text-teal-600 dark:text-teal-400 flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                Learn more
                                                <svg className="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* New stats section */}
                    <section className="px-6 md:px-12 py-16 bg-gradient-to-r from-teal-50/50 to-white/50 dark:from-gray-900 dark:to-gray-900/50">
                        <div className="max-w-7xl mx-auto">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
                                {[
                                    { number: "98%", label: "Client Satisfaction" },
                                    { number: "50+", label: "Enterprise Clients" },
                                    { number: "$2.5M", label: "Transaction Volume" },
                                    { number: "24/7", label: "Support Available" },
                                ].map((stat, i) => (
                                    <div key={i} className="text-center p-6 rounded-lg backdrop-blur-sm">
                                        <div className="text-3xl md:text-4xl font-bold text-teal-600 dark:text-teal-400 mb-2">{stat.number}</div>
                                        <div className="text-gray-600 dark:text-gray-300 font-medium">{stat.label}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* Enhanced Call-to-action section */}
                    <section className="px-6 md:px-12 py-24">
                        <div className="max-w-7xl mx-auto bg-gradient-to-r from-teal-600 to-teal-500 rounded-3xl p-12 md:p-16 relative overflow-hidden shadow-2xl">
                            {/* Enhanced abstract background */}
                            <div className="absolute top-0 right-0 w-1/3 h-full opacity-10">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="white" d="M42.7,-62.9C56.7,-51.8,70.3,-40.6,76.4,-25.8C82.4,-11,80.8,7.5,74.1,23.2C67.4,39,55.6,52,41.5,61.8C27.3,71.7,10.9,78.3,-4.6,84.2C-20.1,90.1,-34.6,95.3,-44.9,88.9C-55.2,82.5,-61.3,65.6,-72.1,50.1C-83,34.7,-98.5,20.7,-99.9,6C-101.2,-8.7,-88.3,-24.1,-76.1,-38.9C-64,-53.8,-52.6,-68,-38.7,-78.7C-24.9,-89.4,-8.7,-96.4,3.4,-101.6C15.4,-106.8,28.8,-110.2,38.4,-98.2C47.9,-86.2,53.6,-58.8,59.8,-46.5C66,-34.3,72.6,-37.1,72.4,-31.7C72.2,-26.2,65,-12.6,57.9,0.2C50.7,13,43.5,26,40.3,42.2C37.1,58.3,37.9,77.5,28.7,94.2C19.6,110.8,0.5,124.8,-18.3,123.8C-37.1,122.8,-55.7,106.8,-73.2,89.7C-90.7,72.5,-107.2,54.2,-113.8,33.4C-120.4,12.7,-117.1,-10.6,-110.3,-32.3C-103.5,-54,-93.2,-74.2,-76.1,-86.5C-59,-98.9,-35.1,-103.4,-15.8,-96.3C3.4,-89.3,17.9,-70.5,31.9,-58.2C45.9,-45.8,59.3,-39.9,42.7,-62.9Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-white/5 to-transparent pointer-events-none"></div>

                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
                                <div className="lg:col-span-7 text-white">
                                    <h2 className="text-3xl md:text-4xl font-bold mb-6">Ready to transform your procurement process?</h2>
                                    <div className="h-1 w-20 bg-white/30 mb-6 rounded-full"></div>
                                    <p className="text-xl opacity-90 mb-8 leading-relaxed">Join thousands of companies that have revolutionized their supply chain with ProcuChain's blockchain technology.</p>
                                    <div className="flex flex-wrap gap-4">
                                        <Link
                                            href={route('register')}
                                            className="px-8 py-4 bg-white text-teal-600 rounded-lg hover:shadow-xl hover:translate-y-[-2px] transition-all duration-300 font-medium flex items-center"
                                        >
                                            Get Started Now
                                            <svg className="w-5 h-5 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                                            </svg>
                                        </Link>
                                        <Link
                                            href="#contact"
                                            className="px-8 py-4 bg-transparent border border-white text-white rounded-lg hover:bg-white/10 transition-all duration-300 font-medium"
                                        >
                                            Contact Sales
                                        </Link>
                                    </div>
                                </div>

                                <div className="lg:col-span-5 hidden lg:block">
                                    <img
                                        src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=2300&q=80"
                                        alt="Dashboard visualization"
                                        className="rounded-lg shadow-2xl transform hover:scale-105 transition-transform duration-500 border-4 border-white/20"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                {/* Enhanced Footer */}
                <footer className="px-6 md:px-12 py-12 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                    <div className="max-w-7xl mx-auto">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                            <div>
                                <div className="flex items-center space-x-2 mb-6">
                                    <div className="h-9 w-9 rounded-md bg-gradient-to-r from-teal-600 to-teal-500 flex items-center justify-center">
                                        <span className="text-white font-bold text-lg">P</span>
                                    </div>
                                    <span className="font-semibold text-lg text-gray-900 dark:text-white">ProcuChain</span>
                                </div>
                                <p className="text-gray-600 dark:text-gray-400 mb-4">Revolutionizing procurement with blockchain technology</p>
                            </div>

                            {[
                                {
                                    title: "Product",
                                    links: ["Features", "Security", "Pricing", "Resources"]
                                },
                                {
                                    title: "Company",
                                    links: ["About", "Careers", "Blog", "Press"]
                                },
                                {
                                    title: "Legal",
                                    links: ["Privacy", "Terms", "Security", "Compliance"]
                                }
                            ].map((column, i) => (
                                <div key={i}>
                                    <h3 className="font-semibold text-gray-900 dark:text-white mb-4">{column.title}</h3>
                                    <ul className="space-y-3">
                                        {column.links.map((link, j) => (
                                            <li key={j}>
                                                <a href="#" className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                                    {link}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>

                        <div className="pt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col md:flex-row justify-between items-center">
                            <div className="text-sm text-gray-600 dark:text-gray-400 mb-4 md:mb-0">
                                © {new Date().getFullYear()} ProcuChain. All rights reserved.
                            </div>
                            <div className="flex space-x-6">
                                {["Twitter", "LinkedIn", "GitHub", "Instagram"].map((social, i) => (
                                    <a key={i} href="#" className="text-gray-500 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                        {social}
                                    </a>
                                ))}
                            </div>
                        </div>
                    </div>
                </footer>

                {/* Add global styles */}
                <style>{`
                    body {
                        font-family: 'Inter', sans-serif;
                    }
                    h1, h2, h3, h4, h5, h6 {
                        font-family: 'Outfit', sans-serif;
                    }
                    .fade-in-content.is-visible {
                        opacity: 1;
                    }
                    .animate-pulse-slow {
                        animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
                    }
                    @keyframes pulse {
                        0%, 100% {
                            opacity: 0.5;
                        }
                        50% {
                            opacity: 0.8;
                        }
                    }
                `}</style>
            </div>
        </>
    );
}
