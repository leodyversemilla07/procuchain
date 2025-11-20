import Footer from '@/components/footer';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { about, login } from '@/routes';
import { Head, Link } from '@inertiajs/react';
import { Database, FileText, Lock, Shield } from 'lucide-react';

export default function Home() {
    const features = [
        {
            icon: Shield,
            title: 'Blockchain Storage',
            description: 'Secure document storage with cryptographic integrity.',
        },
        {
            icon: FileText,
            title: 'Document Management',
            description: 'Comprehensive system for BAC offices.',
        },
        {
            icon: Lock,
            title: 'Real-Time Monitoring',
            description: 'Complete visibility into procurement processes.',
        },
        {
            icon: Database,
            title: 'Access Control',
            description: 'Role-based permissions and security.',
        },
    ];

    return (
        <>
            <Head title="ProcuChain - Blockchain Document Management" />

            <div className="flex min-h-screen flex-col bg-background">
                <Header />

                <main className="flex-1">
                    <div className="container mx-auto px-4 py-16 sm:px-12 lg:px-16 xl:px-20">
                        {/* Hero */}
                        <div className="mx-auto max-w-4xl text-center">
                            <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                                <span className="relative flex h-2 w-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                                </span>
                                Blockchain Powered
                            </div>

                            <h1 className="mb-6 text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl lg:text-7xl">
                                ProcuChain
                            </h1>

                            <p className="mb-4 text-xl font-semibold text-foreground sm:text-2xl md:text-3xl">
                                Secure, Transparent, Built for Government
                            </p>

                            <p className="mb-10 text-lg text-muted-foreground">
                                A blockchain-powered document management system for Bids and Awards Committee offices.
                            </p>

                            <div className="flex flex-col gap-4 sm:flex-row sm:justify-center">
                                <Button size="lg" className="w-full sm:w-auto" asChild>
                                    <Link href={login.url()}>Get Started</Link>
                                </Button>
                                <Button size="lg" variant="outline" className="w-full sm:w-auto" asChild>
                                    <Link href={about.url()}>Learn More</Link>
                                </Button>
                            </div>
                        </div>

                        {/* Features */}
                        <div className="mx-auto mt-24 grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            {features.map((feature, index) => {
                                const Icon = feature.icon;
                                return (
                                    <div
                                        key={index}
                                        className="rounded-lg border border-border bg-card p-6 text-center transition-colors hover:border-primary/50"
                                    >
                                        <div className="mb-4 flex justify-center">
                                            <div className="rounded-full bg-primary/10 p-3">
                                                <Icon className="h-6 w-6 text-primary" />
                                            </div>
                                        </div>
                                        <h3 className="mb-2 font-semibold">{feature.title}</h3>
                                        <p className="text-sm text-muted-foreground">{feature.description}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
