import { Head, Link } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, ArrowRight, Shield, Users, Zap, Rocket, Book, Code } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function Welcome() {
    return (
        <>
            <Head title="ProcuChain - Capstone Project">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="ProcuChain - Streamline your procurement process with blockchain-powered document management and tracking." />
            </Head>
            <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative overflow-x-hidden">
                <Header />

                <main className="flex-grow mt-[72px] sm:mt-[76px] pb-24">
                    <section className="relative min-h-[85vh] flex items-center">
                        {/* Background Patterns */}
                        <div className="absolute inset-0 overflow-hidden pointer-events-none">
                            <div className="absolute -top-1/2 -right-1/2 w-[100rem] h-[100rem] rounded-full bg-gradient-to-br from-teal-50/40 to-blue-50/40 dark:from-teal-900/20 dark:to-blue-900/20 blur-3xl transform rotate-45"></div>
                        </div>

                        <div className="container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16 items-center py-12">
                                {/* Content */}
                                <div className="lg:col-span-7 space-y-6 md:space-y-8 text-center lg:text-left">
                                    {/* Project Title */}
                                    <div className="mb-4">
                                        <span className="text-teal-500 font-semibold text-lg">Capstone Project 2025</span>
                                    </div>
                                    <h1 className="text-4xl sm:text-5xl md:text-6xl xl:text-7xl font-bold tracking-tight leading-tight">
                                        <span className="block mb-2">Revolutionizing</span>
                                        <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                            Government Procurement
                                        </span>
                                    </h1>

                                    {/* Project Description */}
                                    <p className="text-xl md:text-2xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                                        An innovative blockchain-based solution for transparent and efficient public procurement management.
                                    </p>

                                    {/* Key Points */}
                                    <div className="flex flex-col sm:flex-row gap-6 justify-center lg:justify-start text-gray-600 dark:text-gray-300 mt-8">
                                        <div className="flex items-center gap-2">
                                            <Rocket className="w-5 h-5 text-teal-500" />
                                            <span>Research Project</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Book className="w-5 h-5 text-teal-500" />
                                            <span>Academic Innovation</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Code className="w-5 h-5 text-teal-500" />
                                            <span>Blockchain Technology</span>
                                        </div>
                                    </div>

                                    {/* CTA Buttons */}
                                    <div className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mt-8">
                                        <Button
                                            asChild
                                            size="lg"
                                            className="bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 text-white shadow-lg hover:shadow-xl transition-all duration-300 group"
                                        >
                                            <Link href="/documentation">
                                                View Documentation
                                                <ArrowRight className="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="lg"
                                            className="border-2 hover:bg-gray-50 dark:hover:bg-gray-800"
                                        >
                                            <Link href="/demo">Try Demo</Link>
                                        </Button>
                                    </div>
                                </div>

                                {/* Project Visual */}
                                <div className="lg:col-span-5 relative">
                                    <div className="relative">
                                        {/* Decorative Elements */}
                                        <div className="absolute -top-8 -right-8 w-48 h-48 bg-teal-100/50 dark:bg-teal-900/30 rounded-full blur-2xl"></div>
                                        <div className="absolute -bottom-8 -left-8 w-48 h-48 bg-blue-100/50 dark:bg-blue-900/30 rounded-full blur-2xl"></div>

                                        {/* Main Visual */}
                                        <div className="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 backdrop-blur-sm">
                                            <div className="flex flex-col items-center text-center space-y-6">
                                                <div className="relative">
                                                    <div className="absolute inset-0 bg-gradient-to-br from-teal-400/20 to-blue-400/20 rounded-full blur-2xl"></div>
                                                    <FileText className="w-32 h-32 text-teal-500 relative z-10" />
                                                </div>
                                                <div>
                                                    <h3 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">ProcuChain</h3>
                                                    <p className="text-gray-600 dark:text-gray-300">Streamlined Document Management</p>
                                                </div>

                                                {/* Document Flow Visual */}
                                                <div className="grid grid-cols-3 gap-4 w-full mt-4">
                                                    {[1, 2, 3].map((i) => (
                                                        <div
                                                            key={i}
                                                            className="aspect-[3/4] rounded-lg bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-3 flex items-center justify-center"
                                                        >
                                                            <FileText className="w-6 h-6 text-teal-500/70" />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Research Objectives Section */}
                            <section className="py-24 bg-gray-50 dark:bg-gray-900">
                                <div className="container mx-auto px-4">
                                    <div className="text-center mb-16">
                                        <h2 className="text-3xl md:text-4xl font-bold mb-4">Research Objectives</h2>
                                        <p className="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                                            Exploring the intersection of blockchain technology and government procurement
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                        {[
                                            {
                                                icon: <Shield className="w-10 h-10 text-teal-500" />,
                                                title: "Enhance Transparency",
                                                description: "Investigate how blockchain can improve transparency in government procurement processes"
                                            },
                                            {
                                                icon: <Users className="w-10 h-10 text-teal-500" />,
                                                title: "Streamline Workflows",
                                                description: "Develop efficient digital solutions for procurement document management"
                                            },
                                            {
                                                icon: <Zap className="w-10 h-10 text-teal-500" />,
                                                title: "Reduce Corruption",
                                                description: "Implement immutable audit trails to prevent tampering and ensure accountability"
                                            },
                                        ].map((objective, index) => (
                                            <div key={index} className="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                                                <div className="mb-4">{objective.icon}</div>
                                                <h3 className="text-xl font-bold mb-2">{objective.title}</h3>
                                                <p className="text-gray-600 dark:text-gray-300">{objective.description}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </section>

                            {/* Methodology Section */}
                            <section className="py-24">
                                <div className="container mx-auto px-4">
                                    <div className="text-center mb-16">
                                        <h2 className="text-3xl md:text-4xl font-bold mb-4">Research Methodology</h2>
                                        <p className="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                                            Our systematic approach to solving procurement challenges
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                                        {[
                                            {
                                                step: "1",
                                                title: "Problem Analysis",
                                                description: "In-depth study of current procurement processes and challenges"
                                            },
                                            {
                                                step: "2",
                                                title: "Solution Design",
                                                description: "Development of blockchain-based system architecture"
                                            },
                                            {
                                                step: "3",
                                                title: "Implementation",
                                                description: "Building and testing the procurement management system"
                                            }
                                        ].map((step, index) => (
                                            <div key={index} className="relative">
                                                <div className="bg-teal-50 dark:bg-teal-900/20 p-8 rounded-xl">
                                                    <div className="text-4xl font-bold text-teal-500 mb-4">Phase {step.step}</div>
                                                    <h3 className="text-xl font-bold mb-2">{step.title}</h3>
                                                    <p className="text-gray-600 dark:text-gray-300">{step.description}</p>
                                                </div>
                                                {index < 2 && (
                                                    <ArrowRight className="hidden md:block absolute top-1/2 -right-6 w-12 h-12 text-teal-500 transform -translate-y-1/2" />
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </section>

                            {/* Team Section */}
                            <section className="py-24 bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20">
                                <div className="container mx-auto px-4">
                                    <div className="max-w-4xl mx-auto text-center">
                                        <h2 className="text-3xl md:text-4xl font-bold mb-4">Research Team</h2>
                                        <p className="text-gray-600 dark:text-gray-300 mb-8">
                                            Meet the innovative minds behind ProcuChain
                                        </p>
                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">
                                            {/* Add team member cards here */}
                                        </div>
                                        <div className="mt-16">
                                            <Button
                                                asChild
                                                size="lg"
                                                className="bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 text-white"
                                            >
                                                <Link href="/about">
                                                    <Users className="w-5 h-5 mr-2" />
                                                    About the Team
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </section>
                </main>

                <Footer />
            </div>
        </>
    );
}
