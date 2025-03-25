import { Head, Link } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Welcome() {
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const checkMobile = () => {
            setIsMobile(window.innerWidth < 768);
        };

        // Check on initial load
        checkMobile();

        // Add listener for window resize
        window.addEventListener('resize', checkMobile);

        // Clean up
        return () => window.removeEventListener('resize', checkMobile);
    }, []);

    return (
        <>
            <Head title="Welcome">
                <Link rel="preconnect" href="https://fonts.bunny.net" />
                <Link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|inter:400,500,600&display=swap" rel="stylesheet" />
            </Head>
            <div className={`${isMobile ? 'min-h-screen' : 'h-screen'} flex flex-col ${isMobile ? 'overflow-y-auto' : 'overflow-hidden'} bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative`}>
                <Header />

                <div className={`flex-grow flex items-center ${isMobile ? 'py-20 md:py-0' : ''}`}>
                    <section className="px-4 md:px-10 w-full">
                        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-16 items-center">
                            <div className="lg:col-span-7 space-y-4 md:space-y-6 text-center lg:text-left">
                                <h1 className="text-3xl sm:text-4xl md:text-5xl xl:text-6xl font-bold tracking-tight leading-tight">
                                    <span className="block mb-1 sm:mb-2">Making Your</span>
                                    <span className="bg-gradient-to-r from-teal-600 to-teal-500 bg-clip-text text-transparent">Document Handling Easy</span>
                                </h1>

                                <p className="text-lg sm:text-xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-xl mx-auto lg:mx-0">
                                    Safe, clear, and fast document handling using new technology that keeps your BAC office files safe and easy to track.
                                </p>
                            </div>

                            {/* Simple Hero image */}
                            <div className="lg:col-span-5 relative max-w-sm sm:max-w-md mx-auto lg:max-w-lg lg:mx-0 mt-6 lg:mt-0">
                                <div className="rounded-lg bg-white dark:bg-gray-800 p-4 sm:p-6 shadow-lg">
                                    <div className="flex flex-col items-center text-center">
                                        <FileText className="w-20 h-20 sm:w-24 sm:h-24 md:w-32 md:h-32 text-teal-500 mb-3 md:mb-4" />
                                        <h3 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mb-1 sm:mb-2">ProcuChain</h3>
                                        <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300">Better way to keep track of your files</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <Footer />
            </div>
        </>
    );
}
