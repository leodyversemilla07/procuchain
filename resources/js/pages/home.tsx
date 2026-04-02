import Footer from '@/components/footer';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { about, home, login } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as bacChairmanDashboard } from '@/routes/bac-chairman';
import { dashboard as bacSecretariatDashboard } from '@/routes/bac-secretariat';
import { dashboard as hopeDashboard } from '@/routes/hope';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Database, FileText, Lock, Shield } from 'lucide-react';

const getDashboardRouteByRole = (role: string): string => {
    const routes: Record<string, () => string> = {
        hope: hopeDashboard.url,
        bac_secretariat: bacSecretariatDashboard.url,
        bac_chairman: bacChairmanDashboard.url,
        admin: adminDashboard.url,
    };
    return (routes[role] || home.url)();
};

export default function Home() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
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

            <div className="bg-background flex min-h-screen flex-col">
                <Header />

                <main className="flex-1">
                    <div className="container mx-auto px-4 py-16 sm:px-12 lg:px-16 xl:px-20">
                        {/* Hero */}
                        <div className="mx-auto max-w-4xl text-center">
                            <div className="border-primary/20 bg-primary/10 text-primary mb-8 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium">
                                <span className="relative flex h-2 w-2">
                                    <span className="bg-primary absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"></span>
                                    <span className="bg-primary relative inline-flex h-2 w-2 rounded-full"></span>
                                </span>
                                Blockchain Powered
                            </div>

                            <h1 className="mb-6 text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl lg:text-7xl">ProcuChain</h1>

                            <p className="text-foreground mb-4 text-xl font-semibold sm:text-2xl md:text-3xl">
                                Secure, Transparent, Built for Government
                            </p>

                            <p className="text-muted-foreground mb-10 text-lg">
                                A blockchain-powered document management system for Bids and Awards Committee offices.
                            </p>

                            <div className="flex flex-col gap-4 sm:flex-row sm:justify-center">
                                <Button size="lg" className="w-full sm:w-auto" asChild>
                                    <Link href={auth.user ? getDashboardRouteByRole(auth.role || auth.user.role) : login.url()}>
                                        Get Started
                                    </Link>
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
                                        className="border-border bg-card hover:border-primary/50 rounded-lg border p-6 text-center transition-colors"
                                    >
                                        <div className="mb-4 flex justify-center">
                                            <div className="bg-primary/10 rounded-full p-3">
                                                <Icon className="text-primary h-6 w-6" />
                                            </div>
                                        </div>
                                        <h3 className="mb-2 font-semibold">{feature.title}</h3>
                                        <p className="text-muted-foreground text-sm">{feature.description}</p>
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
