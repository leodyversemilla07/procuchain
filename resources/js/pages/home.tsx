import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, CheckCircle, Lock, ArrowRight, Shield, Database, ChevronRight, ChevronLeft, ExternalLink, BookOpen, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

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
            <Head title="ProcuChain: A Blockchain-powered Document Management System for Bids and Awards Committee Office">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="ProcuChain is a blockchain-based procurement management system providing transparency, security, and efficiency in government procurement processes." />
            </Head>

            <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white overflow-hidden">
                <Header />

                {/* Hero Section */}
                <div
                    className={`relative bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-950 dark:to-blue-950 py-12 lg:py-16 transition-opacity duration-1000 ${isVisible ? 'opacity-100' : 'opacity-0'} min-h-[85vh] lg:min-h-[70vh] flex items-center`}
                >
                    {/* Background Abstract Elements */}
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="absolute -top-24 -left-24 w-64 h-64 rounded-full bg-teal-400/15 dark:bg-teal-400/10 backdrop-blur-3xl"></div>
                        <div className="absolute top-1/2 right-0 transform -translate-y-1/2 translate-x-1/4 w-96 h-96 rounded-full bg-blue-400/15 dark:bg-blue-400/10 backdrop-blur-3xl"></div>
                    </div>

                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                        <div className="flex flex-col lg:flex-row items-center justify-between gap-8 lg:gap-12">
                            {/* Hero Content */}
                            <div className="max-w-2xl lg:max-w-xl text-center lg:text-left">
                                {/* Badge for Credibility - Improved Styling */}
                                <div className={`mb-4 inline-flex items-center py-1 px-4 rounded-full text-xs sm:text-sm bg-teal-100/80 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 shadow-sm transition-all duration-700 delay-50 transform ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'}`}>
                                    <Badge variant="outline" className="px-2 py-0 mr-2 text-xs sm:text-sm bg-white dark:bg-gray-800 text-teal-600 dark:text-teal-400 border-teal-200 dark:border-teal-800 flex-shrink-0">Capstone Project</Badge>
                                    <span className="whitespace-nowrap overflow-hidden text-ellipsis">Mindoro State University - Bongabong Campus</span>
                                </div>

                                <h1 className={`text-3xl sm:text-4xl md:text-5xl lg:text-5xl xl:text-6xl font-bold leading-tight mb-4 transition-all duration-700 delay-100 transform ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'}`}>
                                    <span className="bg-gradient-to-r from-teal-600 to-teal-500 bg-clip-text text-transparent">ProcuChain:</span>
                                    <span className="text-gray-900 dark:text-white block mt-1">
                                        Blockchain for Transparent Procurement
                                    </span>
                                </h1>

                                <p className={`text-base md:text-lg text-gray-700 dark:text-gray-300 mb-8 max-w-2xl transition-all duration-700 delay-200 transform ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'}`}>
                                    A capstone project demonstrating secure, verifiable document management for government bids and awards using blockchain technology.
                                </p>

                                <div className={`flex flex-wrap justify-center lg:justify-start gap-3 sm:gap-4 transition-all duration-700 delay-300 transform ${isVisible ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'}`}>
                                    <Button asChild size="lg" className="bg-teal-600 hover:bg-teal-700 text-white px-6 py-3 text-base shadow-md hover:shadow-lg transition-all">
                                        <a href={route('features')}>
                                            Explore Project Demo
                                            <ArrowRight className="ml-2 w-5 h-5" />
                                        </a>
                                    </Button>
                                    <Button asChild size="lg" variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20 px-6 py-3 text-base">
                                        <a href={route('documentation')}>
                                            View Documentation
                                            <BookOpen className="ml-2 w-5 h-5" />
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            <div className={`w-full max-w-md lg:w-2/5 transition-all duration-1000 delay-500 transform ${isVisible ? 'scale-100 opacity-100' : 'scale-90 opacity-0'}`}>
                                <div className="relative p-2 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                                    <div className="aspect-w-16 aspect-h-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                                        <img
                                            src="/images/blockchain-procurement-system.png"
                                            alt="ProcuChain System Interface"
                                            className="w-full h-full object-cover"
                                            onError={(e) => {
                                                e.currentTarget.src = "https://via.placeholder.com/800x500?text=ProcuChain+System";
                                            }}
                                        />
                                    </div>
                                    <div className="absolute -bottom-3 -left-3 p-2 bg-teal-500 text-white rounded-lg shadow-md flex items-center text-xs">
                                        <Lock className="w-3 h-3 mr-1" />
                                        Blockchain Secured
                                    </div>
                                    <div className="absolute -top-3 -right-3 p-1.5 bg-white dark:bg-gray-800 rounded-full shadow-md border border-gray-200 dark:border-gray-700">
                                        <CheckCircle className="w-4 h-4 text-green-500" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className={`absolute bottom-5 left-0 right-0 flex justify-center transition-opacity duration-1000 delay-500 ${isVisible ? 'opacity-70' : 'opacity-0'} animate-bounce`}>
                        <div className="p-1 rounded-full border-2 border-teal-500 dark:border-teal-400">
                            <ChevronRight className="w-5 h-5 text-teal-500 dark:text-teal-400 transform rotate-90" />
                        </div>
                        <span className="sr-only">Scroll down</span>
                    </div>
                </div>

                <main className="flex-grow">
                    {/* Features Section */}
                    <section className="py-12 sm:py-16 bg-white dark:bg-gray-900">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="text-center mb-12">
                                <h2 className="text-3xl font-bold mb-4">
                                    Key Features &amp; Benefits
                                </h2>
                                <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    ProcuChain transforms procurement processes with powerful blockchain technology
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                {featuresList.map((feature, index) => (
                                    <Card key={index} className="bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition-shadow">
                                        <CardContent className="p-6">
                                            <div className="mb-4 bg-teal-50 dark:bg-teal-900/30 p-3 inline-block rounded-lg">
                                                {feature.icon}
                                            </div>
                                            <h3 className="text-xl font-bold mb-2">{feature.title}</h3>
                                            <p className="text-gray-600 dark:text-gray-300">{feature.description}</p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            <div className="text-center mt-10">
                                <Button asChild className="bg-teal-600 hover:bg-teal-700 text-white">
                                    <a href={route('features')}>
                                        View All Features
                                        <ArrowRight className="ml-2 w-4 h-4" />
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </section>

                    {/* Blockchain Section */}
                    <section className="py-12 sm:py-16 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="flex flex-col lg:flex-row items-center gap-10 lg:gap-16">
                                <div className="lg:w-1/2 order-2 lg:order-1">
                                    <h2 className="text-3xl font-bold mb-6">
                                        <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">Blockchain Technology</span><br />
                                        for Procurement Integrity
                                    </h2>
                                    <p className="text-lg text-gray-600 dark:text-gray-300 mb-6">
                                        ProcuChain leverages blockchain technology to create an immutable record of all
                                        procurement documents and activities, ensuring transparency and preventing manipulation.
                                    </p>

                                    <ul className="space-y-4 mb-8">
                                        {[
                                            "Tamper-proof document verification",
                                            "Permanent audit trail of all activities",
                                            "Decentralized storage for enhanced security",
                                            "Real-time verification of document authenticity"
                                        ].map((item, index) => (
                                            <li key={index} className="flex items-start">
                                                <CheckCircle className="w-6 h-6 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                <span className="text-gray-600 dark:text-gray-300">{item}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <Button asChild variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20">
                                        <a href={route('documentation')}>
                                            Learn About Our Technology
                                            <ArrowRight className="ml-2 w-4 h-4" />
                                        </a>
                                    </Button>
                                </div>

                                <div className="lg:w-1/2 order-1 lg:order-2 mb-8 lg:mb-0 w-full">
                                    <div className="relative">
                                        <div className="rounded-xl overflow-hidden shadow-xl">
                                            <div className="aspect-w-4 aspect-h-3">
                                                <img
                                                    src="/images/blockchain-diagram.png"
                                                    alt="Blockchain Integration"
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.src = "https://via.placeholder.com/800x600?text=Blockchain+Diagram";
                                                    }}
                                                />
                                            </div>
                                        </div>

                                        {/* Floating elements */}
                                        <div className="absolute -bottom-4 -left-4 sm:-bottom-5 sm:-left-5 bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow-lg transform scale-90 sm:scale-100">
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

                                        <div className="absolute -top-4 -right-4 sm:-top-5 sm:-right-5 bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow-lg border border-gray-100 dark:border-gray-700 transform scale-90 sm:scale-100">
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
                    <section className="py-12 sm:py-16 bg-teal-50 dark:bg-teal-900/20">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="text-center mb-12">
                                <h2 className="text-3xl font-bold mb-4">
                                    Expert Feedback
                                </h2>
                                <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    What industry experts and stakeholders are saying about ProcuChain
                                </p>
                            </div>

                            <div className="relative max-w-4xl mx-auto">
                                <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 sm:p-8 md:p-12">
                                    <div className="text-teal-500 text-5xl sm:text-6xl font-serif mb-4 sm:mb-6">"</div>
                                    <blockquote className="text-lg sm:text-xl md:text-2xl text-gray-700 dark:text-gray-300 mb-6 sm:mb-8">
                                        {testimonials[activeTestimonial].quote}
                                    </blockquote>

                                    <div className="flex items-center">
                                        <div className="w-12 h-12 rounded-full bg-teal-100 dark:bg-teal-900 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-4">
                                            <Users className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <div className="font-bold text-lg">{testimonials[activeTestimonial].author}</div>
                                            <div className="text-gray-600 dark:text-gray-400">{testimonials[activeTestimonial].title}</div>
                                            <div className="text-sm text-teal-600 dark:text-teal-400">{testimonials[activeTestimonial].organization}</div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-between mt-6">
                                    <Button
                                        onClick={prevTestimonial}
                                        variant="outline"
                                        size="icon"
                                        className="bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700"
                                    >
                                        <ChevronLeft className="w-5 h-5" />
                                    </Button>

                                    <div className="flex space-x-2">
                                        {testimonials.map((_, idx) => (
                                            <button
                                                key={idx}
                                                onClick={() => setActiveTestimonial(idx)}
                                                className={`w-3 h-3 rounded-full ${activeTestimonial === idx ? 'bg-teal-500' : 'bg-gray-300 dark:bg-gray-600'}`}
                                                aria-label={`Go to testimonial ${idx + 1}`}
                                            />
                                        ))}
                                    </div>

                                    <Button
                                        onClick={nextTestimonial}
                                        variant="outline"
                                        size="icon"
                                        className="bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700"
                                    >
                                        <ChevronRight className="w-5 h-5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* CTA Section */}
                    <section className="py-12 sm:py-16 bg-gradient-to-br from-teal-600 to-teal-500 text-white">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                            <h2 className="text-3xl md:text-4xl font-bold mb-6">Ready to Explore ProcuChain?</h2>
                            <p className="text-xl mb-8 max-w-2xl mx-auto opacity-90">
                                Discover how our blockchain-powered system can transform your procurement processes with enhanced
                                transparency, security, and efficiency.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Button asChild size="lg" className="bg-white text-teal-600 hover:bg-gray-100">
                                    <a href={route('documentation')}>
                                        <BookOpen className="mr-2 h-5 w-5" />
                                        Read Documentation
                                    </a>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="border-white text-white hover:bg-teal-700">
                                    <a href={route('contact')}>
                                        Contact Us
                                        <ExternalLink className="ml-2 h-5 w-5" />
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
