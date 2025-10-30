import Footer from '@/components/footer';
import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { Building, Mail, MapPin, Phone } from 'lucide-react';

export default function Contact() {
    return (
        <>
            <Head title="Contact Us">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta
                    name="description"
                    content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system."
                />
                <meta name="keywords" content="contact procuchain, support, feedback, inquiries, mindoro state university, bongabong campus" />
                
                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="Contact ProcuChain Team" />
                <meta property="og:description" content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system." />
                <meta property="og:image" content="/logo.png" />
                
                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="Contact ProcuChain Team" />
                <meta property="twitter:description" content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system." />
                <meta property="twitter:image" content="/logo.png" />
            </Head>{' '}
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <Header />

                <main className="flex-grow pt-20 pb-12 sm:pt-24 sm:pb-16 md:pt-32 md:pb-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
                        {/* Hero Section */}
                        <div className="mb-12 sm:mb-16 md:mb-20">
                            <h1 className="mb-4 text-center text-2xl font-medium sm:mb-6 sm:text-3xl md:mb-8 md:text-4xl">Contact Us</h1>
                            <p className="text-muted-foreground mx-auto max-w-xl px-4 text-center text-sm sm:px-0 sm:text-base">
                                Have questions? We're here to help. Reach out to us through the following channels.
                            </p>
                        </div>{' '}
                        {/* Contact Information */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-card border-border mb-3 rounded-full border p-3 shadow-sm">
                                            <Mail className="text-primary h-5 w-5" />
                                        </div>
                                        <h3 className="text-foreground mb-1 text-sm font-medium">Email</h3>
                                        <a
                                            href="mailto:semilla.leodyver@minsu.edu.ph"
                                            className="text-muted-foreground hover:text-primary text-sm transition-colors"
                                        >
                                            semilla.leodyver@minsu.edu.ph
                                        </a>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-card border-border mb-3 rounded-full border p-3 shadow-sm">
                                            <Phone className="text-primary h-5 w-5" />
                                        </div>
                                        <h3 className="text-foreground mb-1 text-sm font-medium">Phone</h3>
                                        <a href="tel:+639777616265" className="text-muted-foreground hover:text-primary text-sm transition-colors">
                                            +63 977 761 6365
                                        </a>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-card border-border mb-3 rounded-full border p-3 shadow-sm">
                                            <MapPin className="text-primary h-5 w-5" />
                                        </div>
                                        <h3 className="text-foreground mb-1 text-sm font-medium">Location</h3>
                                        <p className="text-muted-foreground text-sm">
                                            Mindoro State University
                                            <br />
                                            Bongabong Campus
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-card border-border mb-3 rounded-full border p-3 shadow-sm">
                                            <Building className="text-primary h-5 w-5" />
                                        </div>
                                        <h3 className="text-foreground mb-1 text-sm font-medium">Department</h3>
                                        <p className="text-muted-foreground text-sm">
                                            Information Technology
                                            <br />
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
