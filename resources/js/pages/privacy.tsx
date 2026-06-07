import Footer from '@/components/footer';
import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { Bell, Database, Eye, Globe, Lock, Mail, Shield, UserCheck } from 'lucide-react';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy">
                <meta
                    name="description"
                    content="Privacy Policy for ProcuChain - Learn how we collect, use, and protect your data in our blockchain-powered procurement system."
                />
                <meta name="keywords" content="privacy policy, data protection, procuchain privacy, user data, blockchain privacy" />

                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="Privacy Policy - ProcuChain" />
                <meta
                    property="og:description"
                    content="Privacy Policy for ProcuChain - Learn how we collect, use, and protect your data in our blockchain-powered procurement system."
                />
                <meta property="og:image" content="/logo.png" />

                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="Privacy Policy - ProcuChain" />
                <meta
                    property="twitter:description"
                    content="Privacy Policy for ProcuChain - Learn how we collect, use, and protect your data in our blockchain-powered procurement system."
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
                                <Shield className="text-primary h-8 w-8" />
                            </div>
                            <h1 className="mb-4 text-4xl font-bold sm:text-5xl md:text-6xl">Privacy Policy</h1>
                            <p className="text-muted-foreground text-lg">Your privacy is important to us. Learn how we handle your data.</p>
                            <p className="text-muted-foreground mt-2 text-sm">Last updated: December 4, 2025</p>
                        </div>

                        {/* Introduction */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mx-auto max-w-3xl">
                                        <h2 className="mb-4 text-2xl font-semibold">Our Commitment to Privacy</h2>
                                        <p className="text-muted-foreground mb-4">
                                            ProcuChain is committed to protecting the privacy and security of your personal information. This Privacy
                                            Policy explains how we collect, use, disclose, and safeguard your information when you use our
                                            blockchain-powered procurement document management system.
                                        </p>
                                        <p className="text-muted-foreground">
                                            By using ProcuChain, you consent to the data practices described in this policy. We encourage you to
                                            review this policy periodically to stay informed about how we protect your information.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Data Collection */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <Database />
                                        </div>
                                        <h2 className="text-xl font-semibold">Information We Collect</h2>
                                    </div>
                                    <div className="grid gap-6 md:grid-cols-2">
                                        <div>
                                            <h3 className="mb-3 font-medium">Personal Information</h3>
                                            <ul className="flex flex-col gap-2">
                                                {[
                                                    'Name and contact information',
                                                    'Government employee ID and credentials',
                                                    'Email address and phone number',
                                                    'Role and department information',
                                                    'Authentication credentials',
                                                ].map((item, index) => (
                                                    <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                        <UserCheck className="text-primary mt-0.5 mr-2 shrink-0" />
                                                        <span>{item}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                        <div>
                                            <h3 className="mb-3 font-medium">Usage Information</h3>
                                            <ul className="flex flex-col gap-2">
                                                {[
                                                    'Login timestamps and session data',
                                                    'Document upload and access history',
                                                    'IP addresses and device information',
                                                    'Browser type and operating system',
                                                    'Activity logs and audit trails',
                                                ].map((item, index) => (
                                                    <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                        <Eye className="text-primary mt-0.5 mr-2 shrink-0" />
                                                        <span>{item}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* How We Use Information */}
                        <div className="mb-12 grid gap-8 md:grid-cols-2">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <Lock className="text-primary" />
                                        </div>
                                        <h2 className="text-xl font-semibold">How We Use Your Data</h2>
                                    </div>
                                    <ul className="flex flex-col gap-3">
                                        {[
                                            'Authenticate and authorize system access',
                                            'Process and store procurement documents',
                                            'Create immutable blockchain records',
                                            'Generate audit trails for transparency',
                                            'Send notifications about procurement activities',
                                            'Improve system performance and security',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                <Shield className="text-primary mt-0.5 mr-2 shrink-0" />
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
                                            <Globe className="text-primary" />
                                        </div>
                                        <h2 className="text-xl font-semibold">Data Sharing</h2>
                                    </div>
                                    <ul className="flex flex-col gap-3">
                                        {[
                                            'Shared only with authorized BAC personnel',
                                            'Disclosed when required by law or regulation',
                                            'Accessible to system administrators for maintenance',
                                            'Blockchain records visible to network participants',
                                            'Never sold to third parties for commercial purposes',
                                            'Protected by role-based access controls',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                <Shield />
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Blockchain & Data Security */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <Database />
                                        </div>
                                        <h2 className="text-xl font-semibold">Blockchain & Data Security</h2>
                                    </div>
                                    <p className="text-muted-foreground mb-4 text-sm">
                                        ProcuChain utilizes blockchain technology to ensure document integrity and create immutable audit trails. When
                                        documents are uploaded, cryptographic hashes are stored on the blockchain, providing tamper-proof verification
                                        while the actual document content remains in secure storage.
                                    </p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {[
                                            'End-to-end encryption for data transmission',
                                            'Cryptographic hashing for document verification',
                                            'Distributed ledger for tamper-proof records',
                                            'Regular security audits and updates',
                                            'Secure backup and disaster recovery',
                                            'Multi-factor authentication support',
                                        ].map((item, index) => (
                                            <div key={index} className="text-muted-foreground flex items-start text-sm">
                                                <Lock />
                                                <span>{item}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Your Rights & Cookies */}
                        <div className="mb-12 grid gap-8 md:grid-cols-2">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <UserCheck />
                                        </div>
                                        <h2 className="text-xl font-semibold">Your Rights</h2>
                                    </div>
                                    <ul className="flex flex-col gap-3">
                                        {[
                                            'Access your personal data stored in the system',
                                            'Request correction of inaccurate information',
                                            'View your activity history and audit logs',
                                            'Receive notifications about data breaches',
                                            'Request account deactivation (subject to retention policies)',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                <Shield />
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
                                            <Bell />
                                        </div>
                                        <h2 className="text-xl font-semibold">Cookies & Tracking</h2>
                                    </div>
                                    <p className="text-muted-foreground mb-4 text-sm">
                                        ProcuChain uses essential cookies and similar technologies to:
                                    </p>
                                    <ul className="flex flex-col gap-3">
                                        {[
                                            'Maintain your authenticated session',
                                            'Remember your preferences and settings',
                                            'Ensure platform security and prevent fraud',
                                            'Analyze system usage for improvements',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm">
                                                <Eye className="text-primary mt-0.5 mr-2 shrink-0" />
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Data Retention */}
                        <div className="mb-12">
                            <Card className="border">
                                <CardContent className="p-6">
                                    <div className="mx-auto max-w-3xl">
                                        <h2 className="mb-4 text-xl font-semibold">Data Retention</h2>
                                        <p className="text-muted-foreground mb-4 text-sm">
                                            Due to the nature of government procurement processes and legal requirements, data stored in ProcuChain is
                                            retained in accordance with applicable government records retention policies. Blockchain records are
                                            immutable and permanent by design to ensure long-term document verification capabilities.
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            Personal account information may be retained for the duration of your employment with the participating
                                            government agency, plus any additional period required by law.
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
                                        <h2 className="mb-4 text-xl font-semibold">Privacy Concerns?</h2>
                                        <p className="text-muted-foreground mb-4 text-sm">
                                            If you have any questions about this Privacy Policy or our data practices, please contact us.
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
