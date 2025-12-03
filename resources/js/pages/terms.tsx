import Footer from '@/components/footer';
import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { AlertCircle, Ban, FileText, Mail, RefreshCw, Scale, Shield, UserCheck } from 'lucide-react';

export default function Terms() {
    return (
        <>
            <Head title="Terms of Service">
                <meta
                    name="description"
                    content="Terms of Service for ProcuChain - Read our terms and conditions for using the blockchain-powered procurement system."
                />
                <meta name="keywords" content="terms of service, terms and conditions, procuchain terms, user agreement, legal terms" />

                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="Terms of Service - ProcuChain" />
                <meta
                    property="og:description"
                    content="Terms of Service for ProcuChain - Read our terms and conditions for using the blockchain-powered procurement system."
                />
                <meta property="og:image" content="/logo.png" />

                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="Terms of Service - ProcuChain" />
                <meta
                    property="twitter:description"
                    content="Terms of Service for ProcuChain - Read our terms and conditions for using the blockchain-powered procurement system."
                />
                <meta property="twitter:image" content="/logo.png" />
            </Head>
            <div className="bg-background flex min-h-screen flex-col">
                <Header />

                <main className="flex-1">
                    <div className="container mx-auto px-4 py-16 sm:px-12 lg:px-16 xl:px-20">
                        {/* Hero Section */}
                        <div className="mx-auto mb-16 max-w-4xl text-center">
                            <div className="bg-primary/10 mb-6 inline-flex rounded-full p-4">
                                <Scale className="text-primary h-8 w-8" />
                            </div>
                            <h1 className="mb-4 text-4xl font-bold sm:text-5xl md:text-6xl">Terms of Service</h1>
                            <p className="text-muted-foreground text-lg">Please read these terms carefully before using ProcuChain.</p>
                            <p className="text-muted-foreground mt-2 text-sm">Last updated: December 4, 2025</p>
                        </div>

                        {/* Introduction */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mx-auto max-w-3xl">
                                        <h2 className="mb-4 text-2xl font-semibold">Introduction</h2>
                                        <p className="text-muted-foreground mb-4">
                                            Welcome to ProcuChain. These Terms of Service ("Terms") govern your access to and use of the ProcuChain
                                            platform, a blockchain-powered document management system designed for Bids and Awards Committee (BAC)
                                            offices.
                                        </p>
                                        <p className="text-muted-foreground">
                                            By accessing or using ProcuChain, you agree to be bound by these Terms. If you disagree with any part of
                                            the Terms, you may not access the service.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Terms Sections */}
                        <div className="mb-12 grid gap-8 md:grid-cols-2">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <UserCheck className="text-primary h-5 w-5" />
                                        </div>
                                        <h2 className="text-xl font-semibold">User Responsibilities</h2>
                                    </div>
                                    <ul className="space-y-3">
                                        {[
                                            'Maintain the confidentiality of your account credentials',
                                            'Ensure all information provided is accurate and current',
                                            'Use the platform only for authorized government procurement purposes',
                                            'Report any security vulnerabilities or unauthorized access',
                                            'Comply with all applicable laws and regulations',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                <FileText className="text-primary mt-0.5 mr-2 h-4 w-4 shrink-0" />
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>

                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <Shield className="text-primary h-5 w-5" />
                                        </div>
                                        <h2 className="text-xl font-semibold">Acceptable Use</h2>
                                    </div>
                                    <ul className="space-y-3">
                                        {[
                                            'Access the system using only authorized credentials',
                                            'Upload only legitimate procurement documents',
                                            'Maintain the integrity of blockchain records',
                                            'Respect role-based access permissions',
                                            'Use the platform in accordance with government policies',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                <FileText className="text-primary mt-0.5 mr-2 h-4 w-4 shrink-0" />
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Prohibited Activities */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-destructive/10 rounded-lg p-2">
                                            <Ban className="text-destructive h-5 w-5" />
                                        </div>
                                        <h2 className="text-xl font-semibold">Prohibited Activities</h2>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {[
                                            'Attempting to bypass security measures or access controls',
                                            'Uploading fraudulent, falsified, or tampered documents',
                                            'Sharing account credentials with unauthorized individuals',
                                            'Interfering with the blockchain network or consensus mechanism',
                                            'Using automated tools to scrape or extract data',
                                            'Impersonating other users or government officials',
                                            'Attempting to modify or delete blockchain records',
                                            'Using the platform for any illegal activities',
                                        ].map((item, index) => (
                                            <div key={index} className="text-muted-foreground flex items-start text-sm">
                                                <AlertCircle className="text-destructive mt-0.5 mr-2 h-4 w-4 shrink-0" />
                                                <span>{item}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Additional Terms */}
                        <div className="mb-12 grid gap-8 md:grid-cols-2">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <FileText className="text-primary h-5 w-5" />
                                        </div>
                                        <h2 className="text-xl font-semibold">Intellectual Property</h2>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        The ProcuChain platform, including its design, features, and underlying technology, is the intellectual
                                        property of the development team at Mindoro State University - Bongabong Campus. Documents uploaded to the
                                        platform remain the property of their respective government agencies. The blockchain infrastructure ensures
                                        document authenticity without transferring ownership.
                                    </p>
                                </CardContent>
                            </Card>

                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <RefreshCw className="text-primary h-5 w-5" />
                                        </div>
                                        <h2 className="text-xl font-semibold">Service Modifications</h2>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        We reserve the right to modify, suspend, or discontinue any aspect of ProcuChain at any time. We will provide
                                        reasonable notice of significant changes. Continued use of the platform after modifications constitutes
                                        acceptance of the updated Terms. Critical updates affecting data integrity or security may be implemented
                                        without prior notice.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Disclaimer and Limitation */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mx-auto max-w-3xl">
                                        <h2 className="mb-4 text-xl font-semibold">Disclaimer & Limitation of Liability</h2>
                                        <p className="text-muted-foreground mb-4 text-sm">
                                            ProcuChain is provided "as is" without warranties of any kind, either express or implied. While we strive
                                            to maintain high availability and data integrity through blockchain technology, we do not guarantee
                                            uninterrupted service or absolute security.
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            In no event shall ProcuChain, its developers, or Mindoro State University be liable for any indirect,
                                            incidental, special, consequential, or punitive damages arising from your use of the platform.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Contact Section */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mx-auto max-w-3xl text-center">
                                        <div className="bg-primary/10 mx-auto mb-4 inline-flex rounded-full p-3">
                                            <Mail className="text-primary h-6 w-6" />
                                        </div>
                                        <h2 className="mb-4 text-xl font-semibold">Questions About These Terms?</h2>
                                        <p className="text-muted-foreground mb-4 text-sm">
                                            If you have any questions about these Terms of Service, please contact us.
                                        </p>
                                        <a
                                            href="mailto:semilla.leodyver@minsu.edu.ph"
                                            className="text-primary hover:text-primary/80 text-sm font-medium transition-colors"
                                        >
                                            semilla.leodyver@minsu.edu.ph
                                        </a>
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
