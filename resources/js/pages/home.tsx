import Footer from '@/components/footer';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { about, login } from '@/routes';
import { Head, Link } from '@inertiajs/react';
import { Database, FileText, Lock, Shield } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Home() {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        setIsVisible(true);
    }, []);

    const featuresList = [
        {
            title: 'Blockchain Document Storage',
            description:
                'All procurement documents are securely stored on blockchain technology, ensuring permanent archiving with cryptographic integrity that prevents tampering and provides immutable records.',
            icon: <Shield className="text-primary h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />,
        },
        {
            title: 'BAC Document Management',
            description:
                'Comprehensive document management system designed specifically for Bids and Awards Committee offices, handling all procurement-related documents from initiation to completion.',
            icon: <FileText className="text-primary h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />,
        },
        {
            title: 'Real-Time Monitoring & Tracking',
            description:
                'Live monitoring and tracking of all document activities, providing complete visibility into procurement processes with instant status updates and progress tracking.',
            icon: <Lock className="text-primary h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />,
        },
        {
            title: 'Secure Role-Based Access',
            description:
                'Different stakeholders have specific permissions ensuring proper segregation of duties and appropriate access control for BAC committee members and procurement officers.',
            icon: <Database className="text-primary h-6 w-6 sm:h-8 sm:w-8 md:h-10 md:w-10" />,
        },
    ];

    return (
        <>
            <Head title="ProcuChain: Blockchain Document Management for BAC Offices">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta
                    name="description"
                    content="ProcuChain is a blockchain-powered document management system for Bids and Awards Committee offices, providing secure archiving, storage, monitoring, and tracking of procurement documents simultaneously."
                />
                <meta
                    name="keywords"
                    content="blockchain, procurement, document management, BAC, government procurement, transparency, secure archiving, audit trail"
                />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />

                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="ProcuChain: Blockchain Document Management for BAC Offices" />
                <meta
                    property="og:description"
                    content="Blockchain-powered document management system for Bids and Awards Committee offices, ensuring transparency and security in government procurement."
                />
                <meta property="og:image" content="/logo.png" />

                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="ProcuChain: Blockchain Document Management for BAC Offices" />
                <meta
                    property="twitter:description"
                    content="Blockchain-powered document management system for Bids and Awards Committee offices, ensuring transparency and security in government procurement."
                />
                <meta property="twitter:image" content="/logo.png" />

                {/* JSON-LD Structured Data */}
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'SoftwareApplication',
                        name: 'ProcuChain',
                        applicationCategory: 'BusinessApplication',
                        description:
                            'A blockchain-powered document management system for Bids and Awards Committee offices, providing secure archiving, storage, monitoring, and tracking of procurement documents.',
                        operatingSystem: 'Web',
                        offers: {
                            '@type': 'Offer',
                            price: '0',
                            priceCurrency: 'USD',
                        },
                        featureList: [
                            'Blockchain Document Storage',
                            'BAC Document Management',
                            'Real-Time Monitoring & Tracking',
                            'Secure Role-Based Access',
                        ],
                        screenshot: '/logo.png',
                        author: {
                            '@type': 'Organization',
                            name: 'Mindoro State University - Bongabong Campus',
                        },
                    })}
                </script>
            </Head>

            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <Header />

                <main className="flex-grow pt-12 pb-6 sm:pt-16 sm:pb-8 md:pt-20 md:pb-12 lg:pt-24 lg:pb-16">
                    <div className="mx-auto max-w-7xl px-3 sm:px-4 md:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="relative mb-6 sm:mb-8 md:mb-12 lg:mb-16">
                            <div className="relative z-10">
                                {/* Main Heading */}
                                <div className="mb-6 text-center sm:mb-8 md:mb-10">
                                    <h1
                                        className={`mb-4 text-2xl font-bold transition-all delay-100 duration-700 sm:mb-6 sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
                                    >
                                        Document Management System for BAC Offices
                                    </h1>

                                    {/* Value Proposition */}
                                    <p
                                        className={`text-muted-foreground mx-auto max-w-3xl px-4 text-sm leading-relaxed transition-all delay-200 duration-700 sm:text-base md:text-lg lg:text-xl ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
                                    >
                                        ProcuChain provides <span className="text-primary font-semibold">blockchain-based document storage</span> for
                                        Bids and Awards Committee offices, enabling secure archiving, storing, monitoring, and tracking of procurement
                                        documents simultaneously.
                                    </p>
                                </div>

                                {/* Hero Content */}
                                <div
                                    className={`mx-auto max-w-4xl text-center transition-all delay-300 duration-700 ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
                                >
                                    <div className="space-y-6 sm:space-y-8">
                                        <div className="space-y-4">
                                            <h2 className="text-foreground text-lg font-semibold sm:text-xl md:text-2xl">
                                                Bids and Awards Committee (BAC)
                                            </h2>
                                            <p className="text-muted-foreground mx-auto max-w-2xl text-sm leading-relaxed sm:text-base">
                                                Specialized document management for government procurement committees, ensuring compliance with
                                                procurement laws and regulations.
                                            </p>
                                        </div>

                                        {/* CTA Buttons */}
                                        <div className="flex flex-col justify-center gap-3 sm:flex-row sm:gap-4">
                                            <Button size="lg" className="shadow-lg hover:shadow-xl" asChild>
                                                <Link href={login.url()}>Get Started</Link>
                                            </Button>
                                            <Button variant="outline" size="lg" asChild>
                                                <Link href={about.url()}>Learn More</Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Features Section */}
                        <div className="mb-6 sm:mb-8 md:mb-12 lg:mb-16">
                            <h2 className="mb-3 text-center text-base font-medium sm:mb-4 sm:text-lg md:mb-6 md:text-xl lg:text-2xl">
                                BAC Document Management Features
                            </h2>
                            <p className="text-muted-foreground mx-auto mb-4 max-w-2xl px-2 text-center text-xs sm:mb-6 sm:px-4 sm:text-sm md:mb-8 md:text-base">
                                ProcuChain combines blockchain storage with comprehensive document management capabilities designed specifically for
                                Bids and Awards Committee offices.
                            </p>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:gap-6 lg:grid-cols-4">
                                {featuresList.map((feature, index) => (
                                    <Card
                                        key={index}
                                        className={`bg-muted border-0 transition-all duration-300 ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
                                        style={{ transitionDelay: `${index * 100}ms` }}
                                    >
                                        <CardContent className="p-3 sm:p-4 md:p-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="bg-card border-border mb-3 rounded-lg border p-1.5 sm:mb-4 sm:p-2 md:p-3">
                                                    {feature.icon}
                                                </div>
                                                <h3 className="mb-1 text-sm font-medium sm:mb-2 sm:text-base md:text-lg">{feature.title}</h3>
                                                <p className="text-muted-foreground text-xs sm:text-sm md:text-base">{feature.description}</p>
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
