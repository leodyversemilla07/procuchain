import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, CheckCircle, Lock, ArrowRight, Shield, Database, ChevronRight, ChevronLeft, ExternalLink, BookOpen, Users, Building, Boxes } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function Home() {
    const [isVisible, setIsVisible] = useState(false);
    const [activeTestimonial, setActiveTestimonial] = useState(0);

    useEffect(() => {
        // Adding animation effect on load
        setIsVisible(true);
    }, []);

    const featuresList = [
        {
            title: "Blockchain Document Verification",
            description: "Every document is hashed and stored on the blockchain, creating an immutable record that ensures transparency and prevents tampering.",
            icon: <Shield className="w-10 h-10 text-teal-500" />
        },
        {
            title: "End-to-End Procurement Tracking",
            description: "Monitor the entire procurement process from initiation to completion with real-time status updates and automatic notifications.",
            icon: <FileText className="w-10 h-10 text-teal-500" />
        },
        {
            title: "Secure Role-Based Access",
            description: "Different stakeholders have specific permissions ensuring proper segregation of duties and appropriate access control.",
            icon: <Lock className="w-10 h-10 text-teal-500" />
        },
        {
            title: "Comprehensive Audit Trail",
            description: "Every action is recorded on the blockchain, providing a complete and unalterable history of all procurement activities.",
            icon: <Database className="w-10 h-10 text-teal-500" />
        },
    ];

    const testimonials = [
        {
            quote: "ProcuChain represents a significant innovation in procurement systems. The blockchain integration ensures transparency while maintaining security and compliance with government regulations.",
            author: "Dr. Sheryl Mae D. Lainez",
            title: "Department Chair, BS in Information Technology",
            organization: "Mindoro State University"
        },
        {
            quote: "As someone involved in procurement processes, I'm impressed by how ProcuChain maintains document integrity while streamlining workflows. The interface is intuitive and the blockchain component adds real value.",
            author: "Manulito S. Rodriguez",
            title: "BAC Chairperson",
            organization: "Local Government of Gloria"
        }
    ];

    const nextTestimonial = () => {
        setActiveTestimonial((prev) => (prev + 1) % testimonials.length);
    };

    const prevTestimonial = () => {
        setActiveTestimonial((prev) => (prev - 1 + testimonials.length) % testimonials.length);
    };

    return (
        <>
            <Head title="ProcuChain: Blockchain-Powered Government Procurement Management System">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="ProcuChain revolutionizes government procurement with blockchain technology, ensuring transparency, security, and efficiency in document management and bidding processes." />
            </Head>

            <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white overflow-hidden">
                <Header />

                {/* Hero Section */}
                <div className={`relative bg-gradient-to-br from-teal-50 via-blue-50 to-teal-50 dark:from-teal-950 dark:via-blue-950 dark:to-teal-950 transition-opacity duration-1000 ${isVisible ? 'opacity-100' : 'opacity-0'}`}>
                    {/* Background Elements */}
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="absolute top-0 -left-20 w-72 h-72 rounded-full bg-teal-400/10 dark:bg-teal-400/5 blur-3xl"></div>
                        <div className="absolute bottom-0 right-0 w-72 h-72 rounded-full bg-blue-400/10 dark:bg-blue-400/5 blur-3xl"></div>
                        <div className="absolute inset-0 bg-[url('/images/grid-pattern.svg')] bg-center opacity-5 dark:opacity-[0.07]"></div>
                    </div>

                    {/* Content Container - Reduced max height */}
                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
                        <div className="flex flex-col lg:flex-row items-center gap-8 lg:gap-12">
                            {/* Left Column - Content */}
                            <div className="w-full lg:w-1/2 text-center lg:text-left">
                                {/* Project Badge */}
                                <div className={`inline-flex items-center py-1 px-3 rounded-full text-xs bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-teal-100 dark:border-teal-800/50 shadow-sm mb-4 transition-all duration-700 delay-50 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <Building className="w-3.5 h-3.5 text-teal-600 dark:text-teal-400 mr-1.5" />
                                    <span className="text-gray-600 dark:text-gray-300">Capstone Project</span>
                                    <div className="mx-2 w-px h-3.5 bg-gray-200 dark:bg-gray-700"></div>
                                    <span className="text-teal-600 dark:text-teal-400 font-medium">MinSU - Bongabong</span>
                                </div>

                                {/* Main Heading - More concise */}
                                <h1 className={`text-4xl lg:text-5xl font-bold leading-tight mb-4 transition-all duration-700 delay-100 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <span className="bg-gradient-to-r from-teal-600 to-blue-600 bg-clip-text text-transparent">
                                        Revolutionizing Government Procurement
                                    </span>
                                </h1>

                                {/* Value Proposition - Clear and focused */}
                                <p className={`text-lg text-gray-600 dark:text-gray-300 mb-6 transition-all duration-700 delay-200 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    ProcuChain brings transparency and efficiency to government bidding processes through blockchain technology, benefiting both procurement officers and contractors.
                                </p>

                                {/* Key Benefits - Compact layout */}
                                <div className={`flex flex-wrap justify-center lg:justify-start gap-4 mb-6 transition-all duration-700 delay-300 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <span className="inline-flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <Shield className="w-4 h-4 text-teal-500 mr-1.5" />
                                        Tamper-Proof Records
                                    </span>
                                    <span className="inline-flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <Lock className="w-4 h-4 text-teal-500 mr-1.5" />
                                        Secure Access
                                    </span>
                                    <span className="inline-flex items-center text-sm text-gray-600 dark:text-gray-300">
                                        <Boxes className="w-4 h-4 text-teal-500 mr-1.5" />
                                        Smart Workflows
                                    </span>
                                </div>

                                {/* CTAs - Clear hierarchy */}
                                <div className={`flex flex-wrap justify-center lg:justify-start gap-3 transition-all duration-700 delay-400 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <Button asChild size="lg" className="bg-teal-600 hover:bg-teal-700 text-white shadow-md hover:shadow-lg transition-all duration-300">
                                        <a href={route('features')} className="flex items-center">
                                            Try Demo
                                            <ArrowRight className="ml-2 w-4 h-4" />
                                        </a>
                                    </Button>
                                    <Button asChild size="lg" variant="outline" className="border-2 border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20">
                                        <a href={route('documentation')} className="flex items-center">
                                            Learn More
                                            <BookOpen className="ml-2 w-4 h-4" />
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            {/* Right Column - Visual */}
                            <div className={`w-full lg:w-1/2 transition-all duration-1000 delay-500 ${isVisible ? 'opacity-100 scale-100' : 'opacity-0 scale-95'}`}>
                                <div className="relative mx-auto max-w-lg">
                                    {/* Main Image */}
                                    <div className="relative bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 p-2">
                                        <div className="aspect-w-16 aspect-h-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                                            <img
                                                src="/images/blockchain-procurement-system.png"
                                                alt="ProcuChain System Interface"
                                                className="w-full h-full object-cover"
                                                loading="eager"
                                            />
                                        </div>
                                    </div>

                                    {/* Feature Indicators - Smaller and more subtle */}
                                    <div className="absolute -bottom-2 -left-2 p-2 bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-100 dark:border-gray-700">
                                        <div className="flex items-center gap-2">
                                            <div className="p-1 bg-teal-50 dark:bg-teal-900/30 rounded-md">
                                                <Shield className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                                            </div>
                                            <div>
                                                <div className="text-xs font-medium text-gray-900 dark:text-white">Blockchain Secured</div>
                                                <div className="text-[10px] text-gray-500 dark:text-gray-400">Documents & Records</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="absolute -top-2 -right-2 p-2 bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-100 dark:border-gray-700">
                                        <div className="text-center">
                                            <div className="text-lg font-bold text-teal-600 dark:text-teal-400">100%</div>
                                            <div className="text-[10px] text-gray-600 dark:text-gray-300">Transparent</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Subtle Scroll Indicator */}
                    <div className="absolute bottom-4 left-0 right-0 flex justify-center">
                        <div className={`transition-opacity duration-1000 delay-700 ${isVisible ? 'opacity-40' : 'opacity-0'} animate-bounce`}>
                            <ChevronRight className="w-5 h-5 text-gray-400 dark:text-gray-500 transform rotate-90" />
                        </div>
                    </div>
                </div>

                <main className="flex-grow">
                    {/* Features Section */}
                    <section className="py-12 sm:py-16 lg:py-20 bg-white dark:bg-gray-900">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="text-center mb-8 sm:mb-12">
                                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-bold mb-3 sm:mb-4">
                                    Key Features &amp; Benefits
                                </h2>
                                <p className="text-base sm:text-lg lg:text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    ProcuChain transforms procurement processes with powerful blockchain technology
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                                {featuresList.map((feature, index) => (
                                    <Card key={index} className="bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700">
                                        <CardContent className="p-4 sm:p-6">
                                            <div className="mb-4 bg-teal-50 dark:bg-teal-900/30 p-2 sm:p-3 inline-block rounded-lg">
                                                {feature.icon}
                                            </div>
                                            <h3 className="text-lg sm:text-xl font-bold mb-2">{feature.title}</h3>
                                            <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300">{feature.description}</p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            <div className="text-center mt-8 sm:mt-10 lg:mt-12">
                                <Button asChild className="bg-teal-600 hover:bg-teal-700 text-white px-4 sm:px-6 py-2 sm:py-3 text-sm sm:text-base">
                                    <a href={route('features')} className="flex items-center justify-center">
                                        View All Features
                                        <ArrowRight className="ml-2 w-4 h-4" />
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </section>

                    {/* Blockchain Section */}
                    <section className="py-12 sm:py-16 lg:py-20 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="flex flex-col lg:flex-row items-center gap-8 lg:gap-16">
                                <div className="w-full lg:w-1/2 order-2 lg:order-1">
                                    <h2 className="text-2xl sm:text-3xl lg:text-4xl font-bold mb-4 sm:mb-6">
                                        <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">Blockchain Technology</span>
                                        <span className="block mt-1">for Procurement Integrity</span>
                                    </h2>
                                    <p className="text-base sm:text-lg text-gray-600 dark:text-gray-300 mb-5 sm:mb-6">
                                        ProcuChain leverages blockchain technology to create an immutable record of all
                                        procurement documents and activities, ensuring transparency and preventing manipulation.
                                    </p>

                                    <ul className="space-y-3 sm:space-y-4 mb-6 sm:mb-8">
                                        {[
                                            "Tamper-proof document verification",
                                            "Permanent audit trail of all activities",
                                            "Decentralized storage for enhanced security",
                                            "Real-time verification of document authenticity"
                                        ].map((item, index) => (
                                            <li key={index} className="flex items-start">
                                                <CheckCircle className="w-5 h-5 sm:w-6 sm:h-6 text-teal-500 mr-2 sm:mr-3 flex-shrink-0 mt-0.5" />
                                                <span className="text-sm sm:text-base text-gray-600 dark:text-gray-300">{item}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <Button asChild variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20 w-full sm:w-auto">
                                        <a href={route('documentation')} className="flex items-center justify-center">
                                            Learn About Our Technology
                                            <ArrowRight className="ml-2 w-4 h-4" />
                                        </a>
                                    </Button>
                                </div>

                                <div className="w-full lg:w-1/2 order-1 lg:order-2 mb-8 lg:mb-0">
                                    <div className="relative mx-auto max-w-md lg:max-w-none">
                                        <div className="rounded-xl overflow-hidden shadow-xl">
                                            <div className="aspect-w-4 aspect-h-3">
                                                <img
                                                    src="/images/blockchain-diagram.png"
                                                    alt="Blockchain Integration"
                                                    className="w-full h-full object-cover"
                                                    loading="lazy"
                                                    onError={(e) => {
                                                        e.currentTarget.src = "https://via.placeholder.com/800x600?text=Blockchain+Diagram";
                                                    }}
                                                />
                                            </div>
                                        </div>

                                        {/* Floating elements with responsive sizing */}
                                        <div className="absolute -bottom-4 -left-4 sm:-bottom-5 sm:-left-5 bg-white dark:bg-gray-800 p-2 sm:p-3 md:p-4 rounded-lg shadow-lg transform scale-90 sm:scale-100">
                                            <div className="flex items-center space-x-2 sm:space-x-3">
                                                <div className="bg-teal-100 dark:bg-teal-900/30 p-1.5 sm:p-2 rounded-full">
                                                    <Shield className="w-4 h-4 sm:w-5 sm:h-5 text-teal-600 dark:text-teal-400" />
                                                </div>
                                                <div>
                                                    <div className="text-xs sm:text-sm font-semibold">Document Security</div>
                                                    <div className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">SHA-256 Hashing</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="absolute -top-4 -right-4 sm:-top-5 sm:-right-5 bg-white dark:bg-gray-800 p-2 sm:p-3 md:p-4 rounded-lg shadow-lg border border-gray-100 dark:border-gray-700 transform scale-90 sm:scale-100">
                                            <div className="text-sm font-medium text-center">
                                                <div className="text-teal-600 dark:text-teal-400 font-bold text-xl sm:text-2xl">100%</div>
                                                <div className="text-xs sm:text-sm">Tamper-Proof</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Testimonials Section */}
                    <section className="py-12 sm:py-16 lg:py-20 bg-teal-50 dark:bg-teal-900/20">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="text-center mb-8 sm:mb-12">
                                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-bold mb-3 sm:mb-4">
                                    Expert Feedback
                                </h2>
                                <p className="text-base sm:text-lg lg:text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    What industry experts and stakeholders are saying about ProcuChain
                                </p>
                            </div>

                            <div className="relative max-w-4xl mx-auto">
                                <div className="bg-white dark:bg-gray-800 rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 md:p-8 lg:p-10">
                                    <div className="text-teal-500 text-4xl sm:text-5xl lg:text-6xl font-serif mb-3 sm:mb-4 md:mb-6">"</div>
                                    <blockquote className="text-base sm:text-lg md:text-xl lg:text-2xl text-gray-700 dark:text-gray-300 mb-4 sm:mb-6 md:mb-8">
                                        {testimonials[activeTestimonial].quote}
                                    </blockquote>

                                    <div className="flex items-center">
                                        <div className="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-teal-100 dark:bg-teal-900 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-3 sm:mr-4">
                                            <Users className="w-5 h-5 sm:w-6 sm:h-6" />
                                        </div>
                                        <div>
                                            <div className="font-bold text-base sm:text-lg">{testimonials[activeTestimonial].author}</div>
                                            <div className="text-sm sm:text-base text-gray-600 dark:text-gray-400">{testimonials[activeTestimonial].title}</div>
                                            <div className="text-xs sm:text-sm text-teal-600 dark:text-teal-400">{testimonials[activeTestimonial].organization}</div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-between items-center mt-4 sm:mt-6">
                                    <Button
                                        onClick={prevTestimonial}
                                        variant="outline"
                                        size="icon"
                                        className="bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 w-8 h-8 sm:w-10 sm:h-10"
                                    >
                                        <ChevronLeft className="w-4 h-4 sm:w-5 sm:h-5" />
                                        <span className="sr-only">Previous testimonial</span>
                                    </Button>

                                    <div className="flex space-x-2">
                                        {testimonials.map((_, idx) => (
                                            <button
                                                key={idx}
                                                onClick={() => setActiveTestimonial(idx)}
                                                className={`w-2 h-2 sm:w-3 sm:h-3 rounded-full transition-colors ${activeTestimonial === idx ? 'bg-teal-500' : 'bg-gray-300 dark:bg-gray-600'}`}
                                                aria-label={`Go to testimonial ${idx + 1}`}
                                            />
                                        ))}
                                    </div>

                                    <Button
                                        onClick={nextTestimonial}
                                        variant="outline"
                                        size="icon"
                                        className="bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 w-8 h-8 sm:w-10 sm:h-10"
                                    >
                                        <ChevronRight className="w-4 h-4 sm:w-5 sm:h-5" />
                                        <span className="sr-only">Next testimonial</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* CTA Section */}
                    <section className="py-12 sm:py-16 lg:py-20 bg-gradient-to-br from-teal-600 to-teal-500 text-white">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                            <h2 className="text-2xl sm:text-3xl md:text-4xl font-bold mb-4 sm:mb-6">Ready to Explore ProcuChain?</h2>
                            <p className="text-base sm:text-lg md:text-xl mb-6 sm:mb-8 max-w-2xl mx-auto opacity-90">
                                Discover how our blockchain-powered system can transform your procurement processes with enhanced
                                transparency, security, and efficiency.
                            </p>
                            <div className="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4">
                                <Button asChild size="lg" className="bg-white text-teal-600 hover:bg-gray-100 w-full sm:w-auto px-4 sm:px-6 py-2 sm:py-3 text-sm sm:text-base">
                                    <a href={route('documentation')} className="flex items-center justify-center">
                                        <BookOpen className="mr-2 w-4 h-4 sm:w-5 sm:h-5" />
                                        Read Documentation
                                    </a>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="border-white text-white hover:bg-teal-700 w-full sm:w-auto px-4 sm:px-6 py-2 sm:py-3 text-sm sm:text-base">
                                    <a href={route('contact')} className="flex items-center justify-center">
                                        Contact Us
                                        <ExternalLink className="ml-2 w-4 h-4 sm:w-5 sm:h-5" />
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </section>
                </main>

                <Footer />
            </div>
        </>
    );
}
