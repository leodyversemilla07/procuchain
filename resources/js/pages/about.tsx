import Footer from '@/components/footer';
import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head } from '@inertiajs/react';
import { AlertTriangle, BarChart2, CheckCircle, Database, Server, XCircle } from 'lucide-react';

export default function About() {
    return (
        <>
            <Head title="About">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta
                    name="description"
                    content="Learn about ProcuChain - an innovative blockchain-based system designed to bring transparency and efficiency to government procurement processes."
                />
                <meta name="keywords" content="about procuchain, blockchain procurement, government transparency, procurement innovation, MultiChain, Laravel, React" />
                
                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="About ProcuChain - Blockchain Procurement System" />
                <meta property="og:description" content="Learn about ProcuChain - an innovative blockchain-based system designed to bring transparency and efficiency to government procurement processes." />
                <meta property="og:image" content="/logo.png" />
                
                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="About ProcuChain - Blockchain Procurement System" />
                <meta property="twitter:description" content="Learn about ProcuChain - an innovative blockchain-based system designed to bring transparency and efficiency to government procurement processes." />
                <meta property="twitter:image" content="/logo.png" />
            </Head>
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <Header />

                <main className="flex-grow pt-16 pb-8 sm:pt-20 sm:pb-12 md:pt-24 md:pb-16 lg:pt-32 lg:pb-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
                        {/* Hero Section */}
                        <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
                            <h1 className="mb-3 text-center text-2xl font-medium sm:mb-4 sm:text-3xl md:mb-6 md:text-4xl">About ProcuChain</h1>
                            <p className="text-muted-foreground mx-auto max-w-xl px-2 text-center text-sm sm:px-4 sm:text-base md:px-0">
                                A blockchain-powered solution revolutionizing government procurement through transparency and efficiency.
                            </p>
                        </div>

                        {/* Overview Section */}
                        <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6 md:p-8">
                                    <div className="mx-auto max-w-3xl">
                                        <h2 className="mb-3 text-lg font-medium sm:mb-4 sm:text-xl md:text-2xl">Project Overview</h2>
                                        <p className="text-muted-foreground mb-3 text-sm sm:mb-4 sm:text-base">
                                            ProcuChain is a capstone project developed by Information Technology student at Mindoro State University -
                                            Bongabong Campus. It leverages blockchain technology to address challenges in government procurement
                                            processes.
                                        </p>
                                        <p className="text-muted-foreground text-sm sm:text-base">
                                            Our system creates an immutable record of procurement documents and activities, ensuring transparency,
                                            preventing fraud, and establishing a verifiable audit trail that can be trusted by all stakeholders.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Problem & Solution */}
                        <div className="mb-8 grid grid-cols-1 gap-4 sm:mb-12 sm:grid-cols-2 sm:gap-6 md:mb-16 lg:mb-20">
                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6 md:p-8">
                                    <div className="mb-3 flex items-center gap-2 sm:mb-4 sm:gap-3">
                                        <div className="bg-destructive/10 rounded-lg p-2">
                                            <AlertTriangle className="text-destructive h-4 w-4 sm:h-5 sm:w-5" />
                                        </div>
                                        <h2 className="text-lg font-medium sm:text-xl md:text-2xl">The Problem</h2>
                                    </div>
                                    <ul className="space-y-2 sm:space-y-3">
                                        {[
                                            'Lack of transparency in government procurement processes',
                                            'Vulnerability to document tampering and fraud',
                                            'Inefficient document tracking and verification',
                                            'Limited public access to procurement information',
                                            'Challenges in establishing accountability',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm sm:text-base">
                                                <XCircle className="text-destructive mt-1 mr-2 h-3.5 w-3.5 flex-shrink-0 sm:mr-3 sm:h-4 sm:w-4" />
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>

                            <Card className="bg-muted border-0">
                                <CardContent className="p-4 sm:p-6 md:p-8">
                                    <div className="mb-3 flex items-center gap-2 sm:mb-4 sm:gap-3">
                                        <div className="bg-primary/10 rounded-lg p-2">
                                            <CheckCircle className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                        </div>
                                        <h2 className="text-lg font-medium sm:text-xl md:text-2xl">Our Solution</h2>
                                    </div>
                                    <ul className="space-y-2 sm:space-y-3">
                                        {[
                                            'Blockchain-based document verification and storage',
                                            'Immutable audit trail for all procurement activities',
                                            'Secure, role-based access control system',
                                            'Transparent tracking of procurement stages',
                                            'Digital verification of document authenticity',
                                        ].map((item, index) => (
                                            <li key={index} className="text-muted-foreground flex items-start text-sm sm:text-base">
                                                <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:mr-3 sm:h-4 sm:w-4" />
                                                <span>{item}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Technologies Used */}
                        <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
                            <h2 className="mb-4 text-center text-lg font-medium sm:mb-6 sm:text-xl md:mb-8 md:text-2xl">Technologies Used</h2>

                            <Tabs defaultValue="blockchain" className="mx-auto w-full max-w-4xl">
                                <TabsList className="mb-4 grid w-full grid-cols-3 sm:mb-6">
                                    <TabsTrigger value="blockchain" className="text-xs sm:text-sm">
                                        Blockchain
                                    </TabsTrigger>
                                    <TabsTrigger value="frontend" className="text-xs sm:text-sm">
                                        Frontend
                                    </TabsTrigger>
                                    <TabsTrigger value="backend" className="text-xs sm:text-sm">
                                        Backend
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="blockchain">
                                    <Card className="bg-muted border-0">
                                        <CardContent className="p-4 sm:p-6 md:p-8">
                                            <div className="flex flex-col gap-4 sm:gap-6 md:flex-row md:gap-8">
                                                <div className="flex justify-center">
                                                    <div className="bg-card border-border flex h-16 w-16 items-center justify-center rounded-lg border p-3 sm:h-20 sm:w-20 sm:p-4 md:h-24 md:w-24">
                                                        <Database className="text-primary h-8 w-8 sm:h-10 sm:w-10 md:h-12 md:w-12" />
                                                    </div>
                                                </div>
                                                <div className="flex-1">
                                                    <h3 className="mb-2 text-base font-medium sm:mb-3 sm:text-lg md:text-xl">
                                                        Blockchain Infrastructure
                                                    </h3>
                                                    <p className="text-muted-foreground mb-3 text-sm sm:mb-4 sm:text-base">
                                                        ProcuChain utilizes MultiChain, a permission-based blockchain platform optimized for rapid
                                                        development and deployment. This enterprise-focused blockchain solution provides the security
                                                        and immutability needed for government procurement processes while allowing fine-grained
                                                        permission control.
                                                    </p>
                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                                        <div className="flex items-start">
                                                            <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                            <div>
                                                                <h4 className="text-xs font-medium sm:text-sm">Document Hashing</h4>
                                                                <p className="text-muted-foreground text-xs">Secure cryptographic verification</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-start">
                                                            <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                            <div>
                                                                <h4 className="text-xs font-medium sm:text-sm">Immutable Ledger</h4>
                                                                <p className="text-muted-foreground text-xs">Tamper-proof record keeping</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <TabsContent value="frontend">
                                    <Card className="bg-muted border-0">
                                        <CardContent className="p-4 sm:p-6 md:p-8">
                                            <div className="flex flex-col gap-4 sm:gap-6 md:flex-row md:gap-8">
                                                <div className="flex justify-center">
                                                    <div className="bg-card border-border flex h-16 w-16 items-center justify-center rounded-lg border p-3 sm:h-20 sm:w-20 sm:p-4 md:h-24 md:w-24">
                                                        <BarChart2 className="text-primary h-8 w-8 sm:h-10 sm:w-10 md:h-12 md:w-12" />
                                                    </div>
                                                </div>
                                                <div className="flex-1">
                                                    <h3 className="mb-2 text-base font-medium sm:mb-3 sm:text-lg md:text-xl">
                                                        User Interface & Experience
                                                    </h3>
                                                    <p className="text-muted-foreground mb-3 text-sm sm:mb-4 sm:text-base">
                                                        The frontend is built using React with TypeScript for type safety, and Tailwind CSS for
                                                        responsive, modern UI components. Inertia.js bridges the gap between the Laravel backend and
                                                        React frontend, creating a seamless single-page application experience.
                                                    </p>
                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                                        <div className="flex items-start">
                                                            <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                            <div>
                                                                <h4 className="text-xs font-medium sm:text-sm">React & TypeScript</h4>
                                                                <p className="text-muted-foreground text-xs">Component-based architecture</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-start">
                                                            <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                            <div>
                                                                <h4 className="text-xs font-medium sm:text-sm">Tailwind CSS</h4>
                                                                <p className="text-muted-foreground text-xs">Utility-first styling approach</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <TabsContent value="backend">
                                    <Card className="bg-muted border-0">
                                        <CardContent className="p-4 sm:p-6 md:p-8">
                                            <div className="flex flex-col gap-4 sm:gap-6 md:flex-row md:gap-8">
                                                <div className="flex justify-center">
                                                    <div className="bg-card border-border flex h-16 w-16 items-center justify-center rounded-lg border p-3 sm:h-20 sm:w-20 sm:p-4 md:h-24 md:w-24">
                                                        <Server className="text-primary h-8 w-8 sm:h-10 sm:w-10 md:h-12 md:w-12" />
                                                    </div>
                                                </div>
                                                <div className="flex-1">
                                                    <h3 className="mb-2 text-base font-medium sm:mb-3 sm:text-lg md:text-xl">
                                                        Server & Database Architecture
                                                    </h3>
                                                    <p className="text-muted-foreground mb-3 text-sm sm:mb-4 sm:text-base">
                                                        Laravel powers the backend, providing robust API development, authentication, and security
                                                        features. The application uses a hybrid storage approach, with sensitive data in a MySQL
                                                        database and document verification data stored on the blockchain.
                                                    </p>
                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                                        <div className="flex items-start">
                                                            <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                            <div>
                                                                <h4 className="text-xs font-medium sm:text-sm">Laravel PHP Framework</h4>
                                                                <p className="text-muted-foreground text-xs">Secure application framework</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-start">
                                                            <CheckCircle className="text-primary mt-1 mr-2 h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                            <div>
                                                                <h4 className="text-xs font-medium sm:text-sm">MySQL Database</h4>
                                                                <p className="text-muted-foreground text-xs">Relational data storage</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
