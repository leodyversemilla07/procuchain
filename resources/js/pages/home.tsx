import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, Lock, Shield, Database } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

export default function Home() {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        setIsVisible(true);
    }, []);

    const featuresList = [
        {
            title: "Blockchain Document Storage",
            description: "All procurement documents are securely stored on blockchain technology, ensuring permanent archiving with cryptographic integrity that prevents tampering and provides immutable records.",
            icon: <Shield className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-primary" />,
        },
        {
            title: "BAC Document Management",
            description: "Comprehensive document management system designed specifically for Bids and Awards Committee offices, handling all procurement-related documents from initiation to completion.",
            icon: <FileText className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-primary" />,
        },
        {
            title: "Real-Time Monitoring & Tracking",
            description: "Live monitoring and tracking of all document activities, providing complete visibility into procurement processes with instant status updates and progress tracking.",
            icon: <Lock className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-primary" />,
        },
        {
            title: "Secure Role-Based Access",
            description: "Different stakeholders have specific permissions ensuring proper segregation of duties and appropriate access control for BAC committee members and procurement officers.",
            icon: <Database className="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 text-primary" />,
        },
    ];

    return (
        <>
            <Head title="ProcuChain: Blockchain Document Management for BAC Offices">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="ProcuChain is a blockchain-powered document management system for Bids and Awards Committee offices, providing secure archiving, storage, monitoring, and tracking of procurement documents simultaneously." />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            </Head>

            <div className="min-h-screen flex flex-col bg-background text-foreground">
                <Header />

                <main className="flex-grow pt-12 sm:pt-16 md:pt-20 lg:pt-24 pb-6 sm:pb-8 md:pb-12 lg:pb-16">
                    <div className="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="relative mb-6 sm:mb-8 md:mb-12 lg:mb-16">
                            <div className="relative z-10">

                                {/* Main Heading */}
                                <div className="text-center mb-6 sm:mb-8 md:mb-10">
                                    <h1 className={`text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-bold mb-4 sm:mb-6 transition-all duration-700 delay-100 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                        Document Management System for BAC Offices
                                    </h1>

                                    {/* Value Proposition */}
                                    <p className={`text-sm sm:text-base md:text-lg lg:text-xl text-muted-foreground max-w-3xl mx-auto px-4 leading-relaxed transition-all duration-700 delay-200 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                        ProcuChain provides <span className="text-primary font-semibold">blockchain-based document storage</span> for Bids and Awards Committee offices, enabling secure archiving, storing, monitoring, and tracking of procurement documents simultaneously.
                                    </p>
                                </div>

                                {/* Hero Content */}
                                <div className={`max-w-4xl mx-auto text-center transition-all duration-700 delay-300 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <div className="space-y-6 sm:space-y-8">
                                        <div className="space-y-4">
                                            <h2 className="text-lg sm:text-xl md:text-2xl font-semibold text-foreground">
                                                Bids and Awards Committee (BAC)
                                            </h2>
                                            <p className="text-sm sm:text-base text-muted-foreground leading-relaxed max-w-2xl mx-auto">
                                                Specialized document management for government procurement committees, ensuring compliance with procurement laws and regulations.
                                            </p>
                                        </div>

                                        {/* CTA Buttons */}
                                        <div className="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center">
                                            <Button size="lg" className="shadow-lg hover:shadow-xl" asChild>
                                                <Link href="/login">Get Started</Link>
                                            </Button>
                                            <Button variant="outline" size="lg" asChild>
                                                <Link href="/about">Learn More</Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Features Section */}
                        <div className="mb-6 sm:mb-8 md:mb-12 lg:mb-16">
                            <h2 className="text-base sm:text-lg md:text-xl lg:text-2xl font-medium mb-3 sm:mb-4 md:mb-6 text-center">
                                BAC Document Management Features
                            </h2>
                            <p className="text-xs sm:text-sm md:text-base text-muted-foreground text-center max-w-2xl mx-auto px-2 sm:px-4 mb-4 sm:mb-6 md:mb-8">
                                ProcuChain combines blockchain storage with comprehensive document management capabilities designed specifically for Bids and Awards Committee offices.
                            </p>

                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
                                {featuresList.map((feature, index) => (
                                    <Card key={index} className={`bg-muted border-0 transition-all duration-300 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`} style={{ transitionDelay: `${index * 100}ms` }}>
                                        <CardContent className="p-3 sm:p-4 md:p-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="bg-card p-1.5 sm:p-2 md:p-3 rounded-lg mb-3 sm:mb-4 border border-border">
                                                    {feature.icon}
                                                </div>
                                                <h3 className="text-sm sm:text-base md:text-lg font-medium mb-1 sm:mb-2">{feature.title}</h3>
                                                <p className="text-xs sm:text-sm md:text-base text-muted-foreground">{feature.description}</p>
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
