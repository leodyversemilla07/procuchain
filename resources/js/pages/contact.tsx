import Footer from '@/components/footer';
import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { Building, Mail, MapPin, Phone } from 'lucide-react';

export default function Contact() {
    return (
        <>
            <Head title="Contact Us">
                <meta
                    name="description"
                    content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system."
                />
                <meta name="keywords" content="contact procuchain, support, feedback, inquiries, mindoro state university, bongabong campus" />

                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="Contact ProcuChain Team" />
                <meta
                    property="og:description"
                    content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system."
                />
                <meta property="og:image" content="/logo.png" />

                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="Contact ProcuChain Team" />
                <meta
                    property="twitter:description"
                    content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system."
                />
                <meta property="twitter:image" content="/logo.png" />
            </Head>{' '}
            <div className="bg-background flex min-h-screen flex-col">
                <Header />

                <main className="flex-1">
                    <div className="container mx-auto px-4 py-16 sm:px-12 lg:px-16 xl:px-20">
                        {/* Hero Section */}
                        <div className="mx-auto mb-16 max-w-4xl text-center">
                            <h1 className="mb-4 text-4xl font-bold sm:text-5xl md:text-6xl">Contact Us</h1>
                            <p className="text-muted-foreground text-lg">
                                Have questions? We're here to help. Reach out to us through the following channels.
                            </p>
                        </div>

                        {/* Contact Information */}
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-primary/10 mb-4 rounded-full p-3">
                                            <Mail className="text-primary h-6 w-6" />
                                        </div>
                                        <h3 className="mb-2 font-semibold">Email</h3>
                                        <a
                                            href="mailto:semilla.leodyver@minsu.edu.ph"
                                            className="text-muted-foreground hover:text-primary text-sm transition-colors"
                                        >
                                            semilla.leodyver@minsu.edu.ph
                                        </a>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-primary/10 mb-4 rounded-full p-3">
                                            <Phone className="text-primary h-6 w-6" />
                                        </div>
                                        <h3 className="mb-2 font-semibold">Phone</h3>
                                        <a href="tel:+639777616265" className="text-muted-foreground hover:text-primary text-sm transition-colors">
                                            +63 977 761 6365
                                        </a>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-primary/10 mb-4 rounded-full p-3">
                                            <MapPin className="text-primary h-6 w-6" />
                                        </div>
                                        <h3 className="mb-2 font-semibold">Location</h3>
                                        <p className="text-muted-foreground text-sm">
                                            Mindoro State University
                                            <br />
                                            Bongabong Campus
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="flex flex-col items-center text-center">
                                        <div className="bg-primary/10 mb-4 rounded-full p-3">
                                            <Building className="text-primary h-6 w-6" />
                                        </div>
                                        <h3 className="mb-2 font-semibold">Department</h3>
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
