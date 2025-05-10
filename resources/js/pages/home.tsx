import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, Lock, ArrowRight, Shield, Database, ChevronRight, ChevronLeft, ExternalLink, BookOpen, Building, Boxes, Zap, BarChart, Clock, Globe } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function Home() {
    const [isVisible, setIsVisible] = useState(false);
    const [activeTestimonial, setActiveTestimonial] = useState(0);

    useEffect(() => {
        setIsVisible(true);

        // Auto-rotate testimonials
        const testimonialInterval = setInterval(() => {
            setActiveTestimonial((prev) => (prev + 1) % testimonials.length);
        }, 5000);

        return () => clearInterval(testimonialInterval);
    }, []);

    const featuresList = [
        {
            title: "Blockchain Document Verification",
            description: "Every document is hashed and stored on the blockchain, creating an immutable record that ensures transparency and prevents tampering.",
            icon: <Shield className="w-10 h-10 text-teal-500" />,
            stats: "99.9% Document Integrity"
        },
        {
            title: "End-to-End Procurement Tracking",
            description: "Monitor the entire procurement process from initiation to completion with real-time status updates and automatic notifications.",
            icon: <FileText className="w-10 h-10 text-teal-500" />,
            stats: "24/7 Real-time Updates"
        },
        {
            title: "Secure Role-Based Access",
            description: "Different stakeholders have specific permissions ensuring proper segregation of duties and appropriate access control.",
            icon: <Lock className="w-10 h-10 text-teal-500" />,
            stats: "Multi-level Security"
        },
        {
            title: "Comprehensive Audit Trail",
            description: "Every action is recorded on the blockchain, providing a complete and unalterable history of all procurement activities.",
            icon: <Database className="w-10 h-10 text-teal-500" />,
            stats: "100% Audit Coverage"
        },
    ];

    const testimonials = [
        {
            quote: "ProcuChain represents a significant innovation in procurement systems. The blockchain integration ensures transparency while maintaining security and compliance with government regulations.",
            author: "Dr. Sheryl Mae D. Lainez",
            title: "Department Chair, BS in Information Technology",
            organization: "Mindoro State University",
            image: "/images/testimonial-1.jpg"
        },
        {
            quote: "As someone involved in procurement processes, I'm impressed by how ProcuChain maintains document integrity while streamlining workflows. The interface is intuitive and the blockchain component adds real value.",
            author: "Manulito S. Rodriguez",
            title: "BAC Chairperson",
            organization: "Local Government of Gloria",
            image: "/images/testimonial-2.jpg"
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
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            </Head>

            <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white overflow-hidden">
                <Header />

                {/* Hero Section */}
                <div className={`relative bg-gradient-to-br from-teal-50 via-blue-50 to-teal-50 dark:from-teal-950 dark:via-blue-950 dark:to-teal-950 transition-opacity duration-1000 ${isVisible ? 'opacity-100' : 'opacity-0'}`}>
                    {/* Background Elements */}
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="absolute top-0 -left-20 w-72 sm:w-96 h-72 sm:h-96 rounded-full bg-teal-400/10 dark:bg-teal-400/5 blur-3xl animate-pulse"></div>
                        <div className="absolute bottom-0 right-0 w-72 sm:w-96 h-72 sm:h-96 rounded-full bg-blue-400/10 dark:bg-blue-400/5 blur-3xl animate-pulse delay-1000"></div>
                        <div className="absolute inset-0 bg-[url('/images/grid-pattern.svg')] bg-center opacity-5 dark:opacity-[0.07]"></div>
                        <div className="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-white/50 dark:to-gray-950/50"></div>
                    </div>

                    {/* Content Container */}
                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-24">
                        <div className="flex flex-col lg:flex-row items-center gap-8 lg:gap-16">
                            {/* Left Column - Content */}
                            <div className="w-full lg:w-1/2 text-center lg:text-left">
                                {/* Project Badge */}
                                <div className={`inline-flex items-center py-1.5 px-4 rounded-full text-sm bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm border border-teal-100 dark:border-teal-800/50 shadow-sm mb-6 transition-all duration-700 delay-50 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <Building className="w-4 h-4 text-teal-600 dark:text-teal-400 mr-2" />
                                    <span className="text-gray-700 dark:text-gray-200">Capstone Project</span>
                                    <div className="mx-2 w-px h-4 bg-gray-200 dark:bg-gray-700"></div>
                                    <span className="text-teal-600 dark:text-teal-400 font-medium">MinSU - Bongabong</span>
                                </div>

                                {/* Main Heading */}
                                <h1 className={`text-3xl sm:text-4xl lg:text-6xl font-bold leading-tight mb-4 sm:mb-6 transition-all duration-700 delay-100 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <span className="bg-gradient-to-r from-teal-600 via-blue-600 to-teal-600 bg-clip-text text-transparent bg-size-200 animate-gradient">
                                        Revolutionizing Government Procurement
                                    </span>
                                </h1>

                                {/* Value Proposition */}
                                <p className={`text-base sm:text-lg lg:text-xl text-gray-600 dark:text-gray-300 mb-6 sm:mb-8 transition-all duration-700 delay-200 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    ProcuChain brings transparency and efficiency to government bidding processes through blockchain technology, benefiting both procurement officers and contractors.
                                </p>

                                {/* Key Benefits */}
                                <div className={`grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6 sm:mb-8 transition-all duration-700 delay-300 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <div className="flex items-center p-2 sm:p-3 bg-white/80 dark:bg-gray-800/80 rounded-lg backdrop-blur-sm border border-gray-100 dark:border-gray-700">
                                        <Shield className="w-4 h-4 sm:w-5 sm:h-5 text-teal-500 mr-2" />
                                        <span className="text-xs sm:text-sm font-medium">Tamper-Proof Records</span>
                                    </div>
                                    <div className="flex items-center p-2 sm:p-3 bg-white/80 dark:bg-gray-800/80 rounded-lg backdrop-blur-sm border border-gray-100 dark:border-gray-700">
                                        <Lock className="w-4 h-4 sm:w-5 sm:h-5 text-teal-500 mr-2" />
                                        <span className="text-xs sm:text-sm font-medium">Secure Access</span>
                                    </div>
                                    <div className="flex items-center p-2 sm:p-3 bg-white/80 dark:bg-gray-800/80 rounded-lg backdrop-blur-sm border border-gray-100 dark:border-gray-700">
                                        <Boxes className="w-4 h-4 sm:w-5 sm:h-5 text-teal-500 mr-2" />
                                        <span className="text-xs sm:text-sm font-medium">Smart Workflows</span>
                                    </div>
                                </div>

                                {/* CTAs */}
                                <div className={`flex flex-col sm:flex-row justify-center lg:justify-start gap-3 sm:gap-4 transition-all duration-700 delay-400 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
                                    <Button asChild size="lg" className="bg-teal-600 hover:bg-teal-700 text-white shadow-lg hover:shadow-xl transition-all duration-300 px-6 sm:px-8 py-2 sm:py-3 text-sm sm:text-base">
                                        <a href={route('features')} className="flex items-center justify-center">
                                            Try Demo
                                            <ArrowRight className="ml-2 w-4 h-4 sm:w-5 sm:h-5" />
                                        </a>
                                    </Button>
                                    <Button asChild size="lg" variant="outline" className="border-2 border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20 px-6 sm:px-8 py-2 sm:py-3 text-sm sm:text-base">
                                        <a href={route('documentation')} className="flex items-center justify-center">
                                            Learn More
                                            <BookOpen className="ml-2 w-4 h-4 sm:w-5 sm:h-5" />
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            {/* Right Column - Visual */}
                            <div className={`w-full lg:w-1/2 transition-all duration-1000 delay-500 ${isVisible ? 'opacity-100 scale-100' : 'opacity-0 scale-95'}`}>
                                <div className="relative mx-auto max-w-lg">
                                    {/* Main Image */}
                                    <div className="relative bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg sm:shadow-xl border border-gray-100 dark:border-gray-700 p-2 sm:p-3">
                                        <div className="aspect-w-16 aspect-h-10 rounded-lg sm:rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700">
                                            <img
                                                src="/images/blockchain-procurement-system.png"
                                                alt="ProcuChain System Interface"
                                                className="w-full h-full object-cover"
                                                loading="eager"
                                            />
                                        </div>
                                    </div>

                                    {/* Feature Indicators */}
                                    <div className="absolute -bottom-3 sm:-bottom-4 -left-3 sm:-left-4 p-2 sm:p-3 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg border border-gray-100 dark:border-gray-700 transform hover:scale-105 transition-transform">
                                        <div className="flex items-center gap-2 sm:gap-3">
                                            <div className="p-1.5 sm:p-2 bg-teal-50 dark:bg-teal-900/30 rounded-md sm:rounded-lg">
                                                <Shield className="w-4 h-4 sm:w-5 sm:h-5 text-teal-600 dark:text-teal-400" />
                                            </div>
                                            <div>
                                                <div className="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">Blockchain Secured</div>
                                                <div className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">Documents & Records</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="absolute -top-3 sm:-top-4 -right-3 sm:-right-4 p-2 sm:p-3 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg border border-gray-100 dark:border-gray-700 transform hover:scale-105 transition-transform">
                                        <div className="text-center">
                                            <div className="text-lg sm:text-2xl font-bold text-teal-600 dark:text-teal-400">100%</div>
                                            <div className="text-xs sm:text-sm text-gray-600 dark:text-gray-300">Transparent</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Scroll Indicator */}
                    <div className="absolute bottom-4 sm:bottom-8 left-0 right-0 flex justify-center">
                        <div className={`transition-opacity duration-1000 delay-700 ${isVisible ? 'opacity-40' : 'opacity-0'} animate-bounce`}>
                            <ChevronRight className="w-5 h-5 sm:w-6 sm:h-6 text-gray-400 dark:text-gray-500 transform rotate-90" />
                        </div>
                    </div>
                </div>

                <main className="flex-grow">
                    {/* Features Section */}
                    <section className="py-12 sm:py-16 lg:py-20 bg-white dark:bg-gray-900">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="text-center mb-8 sm:mb-12">
                                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-bold mb-3 sm:mb-4">
                                    <span className="bg-gradient-to-r from-teal-600 to-blue-600 bg-clip-text text-transparent">
                                        Key Features & Benefits
                                    </span>
                                </h2>
                                <p className="text-base sm:text-lg text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    ProcuChain transforms procurement processes with powerful blockchain technology
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                                {featuresList.map((feature, index) => (
                                    <Card key={index} className={`bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 dark:border-gray-700 hover:-translate-y-1 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`} style={{ transitionDelay: `${index * 100}ms` }}>
                                        <CardContent className="p-4 sm:p-6">
                                            <div className="flex items-start gap-4 sm:gap-6">
                                                <div className="bg-teal-50 dark:bg-teal-900/30 p-2 sm:p-3 rounded-lg sm:rounded-xl">
                                                    {feature.icon}
                                                </div>
                                                <div className="flex-1">
                                                    <h3 className="text-lg sm:text-xl font-bold mb-2">{feature.title}</h3>
                                                    <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 mb-3 sm:mb-4">{feature.description}</p>
                                                    <div className="flex items-center text-xs sm:text-sm text-teal-600 dark:text-teal-400">
                                                        <Zap className="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
                                                        {feature.stats}
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            <div className="text-center mt-8 sm:mt-12">
                                <Button asChild className="bg-teal-600 hover:bg-teal-700 text-white px-6 sm:px-8 py-2 sm:py-3 text-sm sm:text-base">
                                    <a href={route('features')} className="flex items-center justify-center">
                                        Explore All Features
                                        <ArrowRight className="ml-2 w-4 h-4 sm:w-5 sm:h-5" />
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
                                        <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                            Blockchain Technology
                                        </span>
                                        <span className="block mt-1 sm:mt-2">for Procurement Integrity</span>
                                    </h2>
                                    <p className="text-base sm:text-lg text-gray-600 dark:text-gray-300 mb-6 sm:mb-8">
                                        ProcuChain leverages blockchain technology to create an immutable record of all
                                        procurement documents and activities, ensuring transparency and preventing manipulation.
                                    </p>

                                    <div className="space-y-4 sm:space-y-6">
                                        {[
                                            {
                                                title: "Tamper-proof Verification",
                                                description: "Every document is cryptographically secured on the blockchain",
                                                icon: <Shield className="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                                            },
                                            {
                                                title: "Complete Audit Trail",
                                                description: "Track every action with timestamped blockchain records",
                                                icon: <Clock className="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                                            },
                                            {
                                                title: "Decentralized Storage",
                                                description: "Enhanced security through distributed document storage",
                                                icon: <Globe className="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                                            },
                                            {
                                                title: "Real-time Verification",
                                                description: "Instant document authenticity verification",
                                                icon: <BarChart className="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                                            }
                                        ].map((item, index) => (
                                            <div key={index} className="flex items-start gap-3 sm:gap-4 p-3 sm:p-4 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                                                <div className="bg-teal-50 dark:bg-teal-900/30 p-1.5 sm:p-2 rounded-md sm:rounded-lg">
                                                    {item.icon}
                                                </div>
                                                <div>
                                                    <h4 className="text-sm sm:text-base font-semibold mb-1">{item.title}</h4>
                                                    <p className="text-xs sm:text-sm text-gray-600 dark:text-gray-300">{item.description}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="mt-6 sm:mt-8">
                                        <Button asChild variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20 px-6 sm:px-8 py-2 sm:py-3 text-sm sm:text-base">
                                            <a href={route('documentation')} className="flex items-center justify-center">
                                                Learn About Our Technology
                                                <ArrowRight className="ml-2 w-4 h-4 sm:w-5 sm:h-5" />
                                            </a>
                                        </Button>
                                    </div>
                                </div>

                                <div className="w-full lg:w-1/2 order-1 lg:order-2 mb-8 lg:mb-0">
                                    <div className="relative mx-auto max-w-lg">
                                        <div className="rounded-xl sm:rounded-2xl overflow-hidden shadow-lg sm:shadow-xl">
                                            <div className="aspect-w-4 aspect-h-3">
                                                <img
                                                    src="/images/blockchain-diagram.png"
                                                    alt="Blockchain Integration"
                                                    className="w-full h-full object-cover"
                                                    loading="lazy"
                                                />
                                            </div>
                                        </div>

                                        <div className="absolute -bottom-4 sm:-bottom-6 -left-4 sm:-left-6 p-3 sm:p-4 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg border border-gray-100 dark:border-gray-700 transform hover:scale-105 transition-transform">
                                            <div className="flex items-center gap-2 sm:gap-3">
                                                <div className="bg-teal-50 dark:bg-teal-900/30 p-1.5 sm:p-2 rounded-md sm:rounded-lg">
                                                    <Shield className="w-4 h-4 sm:w-5 sm:h-5 text-teal-600 dark:text-teal-400" />
                                                </div>
                                                <div>
                                                    <div className="text-xs sm:text-sm font-semibold">Document Security</div>
                                                    <div className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">SHA-256 Hashing</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="absolute -top-4 sm:-top-6 -right-4 sm:-right-6 p-3 sm:p-4 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg border border-gray-100 dark:border-gray-700 transform hover:scale-105 transition-transform">
                                            <div className="text-center">
                                                <div className="text-xl sm:text-2xl font-bold text-teal-600 dark:text-teal-400">100%</div>
                                                <div className="text-xs sm:text-sm text-gray-600 dark:text-gray-300">Tamper-Proof</div>
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
                                <p className="text-base sm:text-lg text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                                    What industry experts and stakeholders are saying about ProcuChain
                                </p>
                            </div>

                            <div className="relative max-w-4xl mx-auto">
                                <div className="bg-white dark:bg-gray-800 rounded-xl sm:rounded-2xl shadow-lg sm:shadow-xl p-6 sm:p-8 lg:p-10">
                                    <div className="text-teal-500 text-4xl sm:text-5xl lg:text-6xl font-serif mb-4 sm:mb-6">"</div>
                                    <blockquote className="text-lg sm:text-xl lg:text-2xl text-gray-700 dark:text-gray-300 mb-6 sm:mb-8">
                                        {testimonials[activeTestimonial].quote}
                                    </blockquote>

                                    <div className="flex items-center">
                                        <div className="w-10 h-10 sm:w-12 sm:h-12 lg:w-14 lg:h-14 rounded-full bg-teal-100 dark:bg-teal-900 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-3 sm:mr-4">
                                            <img
                                                src={testimonials[activeTestimonial].image}
                                                alt={testimonials[activeTestimonial].author}
                                                className="w-full h-full rounded-full object-cover"
                                            />
                                        </div>
                                        <div>
                                            <div className="font-bold text-base sm:text-lg lg:text-xl">{testimonials[activeTestimonial].author}</div>
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
                                        className="bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 w-8 h-8 sm:w-10 sm:h-10 hover:bg-gray-50 dark:hover:bg-gray-700"
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
                                        className="bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 w-8 h-8 sm:w-10 sm:h-10 hover:bg-gray-50 dark:hover:bg-gray-700"
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
                            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-bold mb-4 sm:mb-6">Ready to Transform Your Procurement Process?</h2>
                            <p className="text-base sm:text-lg mb-6 sm:mb-8 max-w-2xl mx-auto opacity-90">
                                Discover how our blockchain-powered system can revolutionize your procurement processes with enhanced
                                transparency, security, and efficiency.
                            </p>
                            <div className="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4">
                                <Button asChild size="lg" className="bg-white text-teal-600 hover:bg-gray-100 px-6 sm:px-8 py-2 sm:py-3 text-sm sm:text-base">
                                    <a href={route('documentation')} className="flex items-center justify-center">
                                        <BookOpen className="mr-2 w-4 h-4 sm:w-5 sm:h-5" />
                                        Read Documentation
                                    </a>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="border-white text-white hover:bg-teal-700 px-6 sm:px-8 py-2 sm:py-3 text-sm sm:text-base">
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
