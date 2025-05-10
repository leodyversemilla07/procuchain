import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, Lock, Shield, Database, Building } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export default function Home() {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        setIsVisible(true);
    }, []);

    const featuresList = [
        {
            title: "Blockchain Document Verification",
            description: "Every document is hashed and stored on the blockchain, creating an immutable record that ensures transparency and prevents tampering.",
            icon: <Shield className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-teal-600 dark:text-teal-400" />,
        },
        {
            title: "End-to-End Procurement Tracking",
            description: "Monitor the entire procurement process from initiation to completion with real-time status updates and automatic notifications.",
            icon: <FileText className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-teal-600 dark:text-teal-400" />,
        },
        {
            title: "Secure Role-Based Access",
            description: "Different stakeholders have specific permissions ensuring proper segregation of duties and appropriate access control.",
            icon: <Lock className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-teal-600 dark:text-teal-400" />,
        },
        {
            title: "Comprehensive Audit Trail",
            description: "Every action is recorded on the blockchain, providing a complete and unalterable history of all procurement activities.",
            icon: <Database className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-teal-600 dark:text-teal-400" />,
        },
    ];

    return (
        <>
            <Head title="ProcuChain: Blockchain-Powered Government Procurement Management System">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="ProcuChain revolutionizes government procurement with blockchain technology, ensuring transparency, security, and efficiency in document management and bidding processes." />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            </Head>

            <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white">
                <Header />

                <main className="flex-grow pt-12 sm:pt-16 md:pt-20 lg:pt-24 pb-6 sm:pb-8 md:pb-12 lg:pb-16">
                    <div className="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="mb-6 sm:mb-8 md:mb-12 lg:mb-16">
                            {/* Project Badge */}
                            <div className="flex justify-center mb-3 sm:mb-4 md:mb-6">
                                <div className={`inline-flex items-center py-1 px-3 sm:py-1.5 sm:px-4 rounded-full text-xs sm:text-sm bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 transition-all duration-700 delay-50 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <Building className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-600 dark:text-teal-400 mr-1.5 sm:mr-2" />
                                    <span className="text-gray-700 dark:text-gray-200">Capstone Project</span>
                                    <div className="mx-1.5 sm:mx-2 w-px h-3.5 sm:h-4 bg-gray-200 dark:bg-gray-700"></div>
                                    <span className="text-teal-600 dark:text-teal-400 font-medium">MinSU - Bongabong</span>
                                </div>
                            </div>

                            {/* Main Heading */}
                            <h1 className={`text-xl sm:text-2xl md:text-3xl lg:text-4xl font-medium mb-2 sm:mb-3 md:mb-4 text-center transition-all duration-700 delay-100 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                Revolutionizing Government Procurement
                            </h1>

                            {/* Value Proposition */}
                            <p className={`text-xs sm:text-sm md:text-base text-gray-600 dark:text-gray-400 mb-4 sm:mb-6 md:mb-8 text-center max-w-2xl mx-auto px-2 sm:px-4 transition-all duration-700 delay-200 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                ProcuChain brings transparency and efficiency to government bidding processes through blockchain technology, benefiting both procurement officers and contractors.
                            </p>

                            {/* Main Image */}
                            <div className={`relative mx-auto max-w-4xl transition-all duration-1000 delay-500 ${isVisible ? 'opacity-100 scale-100' : 'opacity-0 scale-95'}`}>
                                <div className="relative bg-gray-50 dark:bg-gray-900/50 rounded-lg p-1.5 sm:p-2 md:p-3">
                                    <div className="aspect-w-16 aspect-h-10 rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                        <img
                                            src="/images/blockchain-procurement-system.png"
                                            alt="ProcuChain System Interface"
                                            className="w-full h-full object-cover"
                                            loading="eager"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Features Section */}
                        <div className="mb-6 sm:mb-8 md:mb-12 lg:mb-16">
                            <h2 className="text-base sm:text-lg md:text-xl lg:text-2xl font-medium mb-3 sm:mb-4 md:mb-6 text-center">
                                Key Features & Benefits
                            </h2>
                            <p className="text-xs sm:text-sm md:text-base text-gray-600 dark:text-gray-400 text-center max-w-2xl mx-auto px-2 sm:px-4 mb-4 sm:mb-6 md:mb-8">
                                ProcuChain transforms procurement processes with powerful blockchain technology
                            </p>

                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
                                {featuresList.map((feature, index) => (
                                    <Card key={index} className={`bg-gray-50 dark:bg-gray-900/50 border-0 transition-all duration-300 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`} style={{ transitionDelay: `${index * 100}ms` }}>
                                        <CardContent className="p-3 sm:p-4 md:p-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="bg-white dark:bg-gray-800 p-1.5 sm:p-2 md:p-3 rounded-lg mb-3 sm:mb-4">
                                                    {feature.icon}
                                                </div>
                                                <h3 className="text-sm sm:text-base md:text-lg font-medium mb-1 sm:mb-2">{feature.title}</h3>
                                                <p className="text-xs sm:text-sm md:text-base text-gray-600 dark:text-gray-400">{feature.description}</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
