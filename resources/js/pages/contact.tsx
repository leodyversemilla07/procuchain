import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Mail, MapPin, Phone, Building } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export default function Contact() {
    return (
        <>
            <Head title="Contact Us">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system." />
            </Head>
            <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white">
                <Header />

                <main className="flex-grow pt-20 sm:pt-24 md:pt-32 pb-12 sm:pb-16 md:pb-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        {/* Hero Section */}
                        <div className="mb-12 sm:mb-16 md:mb-20">
                            <h1 className="text-2xl sm:text-3xl md:text-4xl font-medium mb-4 sm:mb-6 md:mb-8 text-center">
                                Contact Us
                            </h1>
                            <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 text-center max-w-xl mx-auto px-4 sm:px-0">
                                Have questions? We're here to help. Reach out to us through the following channels.
                            </p>
                        </div>

                        {/* Contact Information */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                            <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-white dark:bg-gray-800 p-3 rounded-full shadow-sm mb-3">
                                            <Mail className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                                        </div>
                                        <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-1">Email</h3>
                                        <a
                                            href="mailto:semilla.leodyver@minsu.edu.ph"
                                            className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors text-sm"
                                        >
                                            semilla.leodyver@minsu.edu.ph
                                        </a>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-white dark:bg-gray-800 p-3 rounded-full shadow-sm mb-3">
                                            <Phone className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                                        </div>
                                        <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-1">Phone</h3>
                                        <a
                                            href="tel:+639777616265"
                                            className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors text-sm"
                                        >
                                            +63 977 761 6365
                                        </a>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-white dark:bg-gray-800 p-3 rounded-full shadow-sm mb-3">
                                            <MapPin className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                                        </div>
                                        <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-1">Location</h3>
                                        <p className="text-gray-600 dark:text-gray-400 text-sm">
                                            Mindoro State University<br />
                                            Bongabong Campus
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-white dark:bg-gray-800 p-3 rounded-full shadow-sm mb-3">
                                            <Building className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                                        </div>
                                        <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-1">Department</h3>
                                        <p className="text-gray-600 dark:text-gray-400 text-sm">
                                            Information Technology<br />
                                            Capstone Project
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}