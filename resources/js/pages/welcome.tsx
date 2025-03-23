import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import Header from '@/components/header';
import Footer from '@/components/footer';

export default function Welcome() {
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

                <Header />

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
                                    <span className="animate-pulse-slow">Trusted by Government Procurement Offices</span>
                                </div>

                                <h1 className="text-4xl md:text-5xl xl:text-6xl font-bold tracking-tight leading-tight">
                                    <span className="block mb-2">Streamline Your</span>
                                    <span className="bg-gradient-to-r from-teal-600 to-teal-500 bg-clip-text text-transparent">BAC Document Management</span>
                                </h1>

                                <p className="text-xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-xl">
                                    Secure, transparent, and efficient procurement document processing powered by blockchain technology that ensures integrity and traceability for your BAC office.
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
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">Trusted by government agencies</p>
                                    <div className="flex flex-wrap items-center gap-6 opacity-70">
                                        {['DPWH', 'DepEd', 'DOH', 'LGUs', 'SUCs'].map((agency) => (
                                            <span key={agency} className="text-gray-500 dark:text-gray-400 font-semibold">{agency}</span>
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
                                            {/* Document-focused blockchain illustration */}
                                            <g fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                <rect x="60" y="40" width="80" height="100" rx="4" opacity="0.6" />
                                                <line x1="80" y1="60" x2="120" y2="60" />
                                                <line x1="80" y1="80" x2="120" y2="80" />
                                                <line x1="80" y1="100" x2="120" y2="100" />
                                                <circle cx="100" cy="100" r="50" opacity="0.2" className="animate-pulse-slow" />
                                                <circle cx="100" cy="100" r="40" opacity="0.4" />
                                                {[0, 60, 120, 180, 240, 300].map((angle, i) => (
                                                    <g key={i} transform={`rotate(${angle} 100 100)`}>
                                                        <circle cx="100" cy="50" r="8" fill={i % 2 ? "#14b8a6" : "#0d9488"} stroke="none" className="animate-pulse" style={{ animationDelay: `${i * 200}ms` }} />
                                                    </g>
                                                ))}
                                            </g>
                                        </svg>
                                        <div className="absolute inset-0 flex items-center justify-center">
                                            <span className="text-2xl font-bold text-gray-900 dark:text-white">DocChain</span>
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
                                    Our blockchain-powered platform delivers unmatched security and efficiency for BAC document processing
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
                                        title: "Tamper-Proof Documents",
                                        description: "Blockchain-verified documents with digital signatures ensure the highest level of security and authenticity for all procurement records."
                                    },
                                    {
                                        icon: (
                                            <svg className="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        ),
                                        title: "Automated Workflows",
                                        description: "Streamline document processing with predefined workflows specific to BAC procedures, eliminating manual handoffs and delays."
                                    },
                                    {
                                        icon: (
                                            <svg className="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        ),
                                        title: "Audit Compliance",
                                        description: "Comprehensive audit trails and document history that meet government procurement transparency requirements and simplify compliance."
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
                                                    <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H3a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* Updated stats section */}
                    <section className="px-6 md:px-12 py-16 bg-gradient-to-r from-teal-50/50 to-white/50 dark:from-gray-900 dark:to-gray-900/50">
                        <div className="max-w-7xl mx-auto">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
                                {[
                                    { number: "85%", label: "Reduction in Processing Time" },
                                    { number: "100%", label: "Document Traceability" },
                                    { number: "50+", label: "Government Agencies" },
                                    { number: "24/7", label: "Document Access" },
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
                                    <h2 className="text-3xl md:text-4xl font-bold mb-6">Ready to transform your BAC document processes?</h2>
                                    <div className="h-1 w-20 bg-white/30 mb-6 rounded-full"></div>
                                    <p className="text-xl opacity-90 mb-8 leading-relaxed">Join government procurement offices that have revolutionized their document management with ProcuChain's blockchain technology.</p>
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
                                    <div className="relative">
                                        <img
                                            src="/img/blockchain-documents.webp"
                                            alt="Blockchain document verification"
                                            className="rounded-lg shadow-2xl transform hover:scale-105 transition-transform duration-500 border-4 border-white/20"
                                        />
                                        <div className="absolute -bottom-4 -right-4 bg-white dark:bg-gray-800 rounded-lg p-3 shadow-xl border border-teal-100 dark:border-teal-900/30">
                                            <svg className="w-12 h-12 text-teal-600 dark:text-teal-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* How It Works Section */}
                    <section id="how-it-works" className="px-6 md:px-12 py-24 bg-white/80 dark:bg-gray-900/80">
                        <div className="max-w-7xl mx-auto">
                            <div className="text-center mb-16">
                                <div className="inline-flex items-center px-4 py-2 bg-teal-100/50 dark:bg-teal-900/20 rounded-full text-sm font-medium text-teal-600 dark:text-teal-300 mb-4">
                                    <svg className="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z" />
                                    </svg>
                                    Process Explained
                                </div>
                                <h2 className="text-3xl md:text-4xl font-bold mb-6">How ProcuChain Works</h2>
                                <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    Secure, transparent document management powered by blockchain technology
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-12">
                                {[
                                    {
                                        icon: (
                                            <svg className="w-12 h-12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            </svg>
                                        ),
                                        title: "1. Upload Documents",
                                        description: "BAC officials securely upload procurement documents to the ProcuChain platform with role-based access controls."
                                    },
                                    {
                                        icon: (
                                            <svg className="w-12 h-12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        ),
                                        title: "2. Blockchain Verification",
                                        description: "Documents are cryptographically hashed and recorded on the blockchain, creating an immutable record of each procurement file."
                                    },
                                    {
                                        icon: (
                                            <svg className="w-12 h-12" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        ),
                                        title: "3. Automated Workflows",
                                        description: "Smart contracts automatically route documents through the approval process based on government procurement regulations."
                                    },
                                ].map((step, index) => (
                                    <div key={index} className="relative">
                                        <div className="bg-white/90 dark:bg-gray-800/90 rounded-xl p-8 shadow-lg border border-gray-100 dark:border-gray-700 h-full">
                                            <div className="w-16 h-16 bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center rounded-full text-teal-600 dark:text-teal-300 mb-6">
                                                {step.icon}
                                            </div>
                                            <h3 className="text-xl font-bold mb-4">{step.title}</h3>
                                            <p className="text-gray-600 dark:text-gray-300">{step.description}</p>
                                        </div>
                                        {index < 2 && (
                                            <div className="hidden md:block absolute top-1/2 -right-6 transform -translate-y-1/2">
                                                <svg className="w-12 h-12 text-teal-300 dark:text-teal-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="mt-20 p-8 border border-dashed border-teal-200 dark:border-teal-900 rounded-xl bg-teal-50/50 dark:bg-teal-900/10">
                                <div className="flex flex-col md:flex-row items-start md:items-center gap-6">
                                    <div className="p-4 bg-teal-100 dark:bg-teal-900/40 rounded-full">
                                        <svg className="w-8 h-8 text-teal-600 dark:text-teal-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 className="text-lg font-semibold mb-2">Blockchain Benefits for Government Procurement</h4>
                                        <p className="text-gray-600 dark:text-gray-300">ProcuChain uses distributed ledger technology to create tamper-proof records that satisfy COA audit requirements and provide real-time visibility into the procurement process—eliminating manual verification steps and reducing processing time by up to 85%.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Testimonials section */}
                    <section className="px-6 md:px-12 py-24 bg-gradient-to-br from-teal-50 to-white dark:from-gray-950 dark:to-gray-900">
                        <div className="max-w-7xl mx-auto">
                            <div className="text-center mb-16">
                                <div className="inline-flex items-center px-4 py-2 bg-teal-100/50 dark:bg-teal-900/20 rounded-full text-sm font-medium text-teal-600 dark:text-teal-300 mb-4">
                                    <svg className="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clipRule="evenodd" />
                                    </svg>
                                    Testimonials
                                </div>
                                <h2 className="text-3xl md:text-4xl font-bold mb-6">Success Stories from Government Agencies</h2>
                                <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    Hear from procurement offices that have transformed their document management
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                {[
                                    {
                                        quote: "ProcuChain has reduced our document processing time by 87% and eliminated instances of missing paperwork. The blockchain verification gives us confidence in the integrity of our records during audits.",
                                        author: "Maria Santos",
                                        title: "BAC Chairperson, Department of Public Works and Highways",
                                        image: "/img/testimonial-1.jpg"
                                    },
                                    {
                                        quote: "The transparency and traceability features have been game-changers for our procurement office. We've been able to satisfy COA requirements with just a few clicks thanks to the comprehensive audit trails.",
                                        author: "Antonio Reyes",
                                        title: "Procurement Officer, Department of Education",
                                        image: "/img/testimonial-2.jpg"
                                    }
                                ].map((testimonial, index) => (
                                    <div key={index} className="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-xl border border-gray-100 dark:border-gray-700">
                                        <div className="flex items-start mb-6">
                                            <svg className="w-12 h-12 text-teal-200 dark:text-teal-800" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z" />
                                            </svg>
                                        </div>
                                        <blockquote className="text-lg text-gray-700 dark:text-gray-300 italic mb-6">"{testimonial.quote}"</blockquote>
                                        <div className="flex items-center">
                                            <div className="w-12 h-12 rounded-full overflow-hidden mr-4 border-2 border-teal-200 dark:border-teal-700">
                                                <img src={testimonial.image} alt={testimonial.author} className="w-full h-full object-cover" />
                                            </div>
                                            <div>
                                                <div className="font-semibold">{testimonial.author}</div>
                                                <div className="text-sm text-gray-500 dark:text-gray-400">{testimonial.title}</div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* FAQ Section */}
                    <section id="faq" className="px-6 md:px-12 py-24 bg-white/80 dark:bg-gray-900/80">
                        <div className="max-w-5xl mx-auto">
                            <div className="text-center mb-16">
                                <div className="inline-flex items-center px-4 py-2 bg-teal-100/50 dark:bg-teal-900/20 rounded-full text-sm font-medium text-teal-600 dark:text-teal-300 mb-4">
                                    <svg className="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                    </svg>
                                    FAQs
                                </div>
                                <h2 className="text-3xl md:text-4xl font-bold mb-6">Frequently Asked Questions</h2>
                                <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    Everything you need to know about blockchain document management
                                </p>
                            </div>

                            <div className="space-y-6">
                                {[
                                    {
                                        question: "How does blockchain ensure document security?",
                                        answer: "ProcuChain uses blockchain technology to create a cryptographic hash of each document, which is recorded on a distributed ledger. This creates an immutable record that proves document authenticity and prevents tampering, as any change to the document would result in a different hash that wouldn't match the blockchain record."
                                    },
                                    {
                                        question: "Is ProcuChain compliant with government procurement regulations?",
                                        answer: "Yes, ProcuChain is specifically designed to comply with RA 9184 (Government Procurement Reform Act) and COA audit requirements. The system maintains comprehensive digital trails of all document actions that satisfy transparency and accountability requirements."
                                    },
                                    {
                                        question: "What technical infrastructure is required to implement ProcuChain?",
                                        answer: "ProcuChain is a cloud-based solution that requires minimal technical infrastructure. Government agencies only need computers with internet access and modern browsers. Our team handles all backend blockchain infrastructure, security updates, and maintenance."
                                    },
                                    {
                                        question: "How does ProcuChain handle document approvals and signatures?",
                                        answer: "ProcuChain incorporates digital signature technology that complies with the Electronic Commerce Act. Authorized officials can securely sign documents through the platform using their credentials and optional two-factor authentication. Each signature is cryptographically secured and recorded on the blockchain."
                                    }
                                ].map((faq, index) => (
                                    <div key={index} className="bg-gray-50 dark:bg-gray-800 rounded-xl p-6 transition-all duration-300 hover:shadow-md">
                                        <div className="flex justify-between items-start">
                                            <h3 className="text-xl font-semibold mb-4 text-gray-800 dark:text-white">{faq.question}</h3>
                                            <div className="p-1.5 bg-teal-100 dark:bg-teal-900/40 rounded-full text-teal-600 dark:text-teal-400 mt-1">
                                                <svg className="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                        <p className="text-gray-600 dark:text-gray-300">{faq.answer}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                </div>

                <Footer />

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
