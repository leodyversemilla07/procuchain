import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Award, Clock, Download, FileText, Search, Shield, Users, } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

interface BidDocument {
    id: number;
    procurement_id: string;
    title: string;
    reference_number: string;
    publication_date: string;
    closing_date: string;
    category: string;
    status: string;
    signatory_details: string;
    hash: string;
    download_url: string;
}

export default function Bidding() {
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [isLoading, setIsLoading] = useState(true);

    // In a real application, this data would be fetched from an API
    const [bidDocuments] = useState<BidDocument[]>([
        {
            id: 1,
            procurement_id: "PROC-2023-001",
            title: "Supply and Delivery of Office Equipment",
            reference_number: "BID-2023-001",
            publication_date: "2023-05-15",
            closing_date: "2023-06-15",
            category: "Goods",
            status: "Open",
            signatory_details: "Jane Smith, BAC Chairperson",
            hash: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
            download_url: "#"
        },
        {
            id: 2,
            procurement_id: "PROC-2023-002",
            title: "Construction of New Admin Building",
            reference_number: "BID-2023-002",
            publication_date: "2023-06-01",
            closing_date: "2023-07-01",
            category: "Infrastructure",
            status: "Open",
            signatory_details: "John Doe, BAC Chairperson",
            hash: "q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2",
            download_url: "#"
        },
        {
            id: 3,
            procurement_id: "PROC-2023-003",
            title: "IT Consulting Services",
            reference_number: "BID-2023-003",
            publication_date: "2023-04-10",
            closing_date: "2023-05-10",
            category: "Services",
            status: "Closed",
            signatory_details: "Alice Johnson, BAC Chairperson",
            hash: "g3h4i5j6k7l8m9n0o1p2q3r4s5t6u7v8",
            download_url: "#"
        },
        {
            id: 4,
            procurement_id: "PROC-2023-004",
            title: "Supply of Medical Equipment",
            reference_number: "BID-2023-004",
            publication_date: "2023-06-20",
            closing_date: "2023-07-20",
            category: "Goods",
            status: "Open",
            signatory_details: "Robert Brown, BAC Chairperson",
            hash: "w9x0y1z2a3b4c5d6e7f8g9h0i1j2k3l4",
            download_url: "#"
        },
        {
            id: 5,
            procurement_id: "PROC-2023-005",
            title: "Rehabilitation of Public Park",
            reference_number: "BID-2023-005",
            publication_date: "2023-03-15",
            closing_date: "2023-04-15",
            category: "Infrastructure",
            status: "Closed",
            signatory_details: "Sarah Wilson, BAC Chairperson",
            hash: "m5n6o7p8q9r0s1t2u3v4w5x6y7z8a9b0",
            download_url: "#"
        }
    ]);

    useEffect(() => {
        // Simulate API loading
        const timer = setTimeout(() => {
            setIsLoading(false);
        }, 1000);

        // Clean up
        return () => {
            clearTimeout(timer);
        };
    }, []);

    // Helper function to check if a bid is closing soon (within 7 days)
    const isClosingSoon = (closingDate: string): boolean => {
        const today = new Date();
        const closing = new Date(closingDate);
        const differenceInTime = closing.getTime() - today.getTime();
        const differenceInDays = differenceInTime / (1000 * 3600 * 24);
        return differenceInDays > 0 && differenceInDays <= 7;
    };

    // Filter documents based on search term and category
    const filteredDocuments = bidDocuments.filter(doc =>
        (doc.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
            doc.reference_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
            doc.procurement_id.toLowerCase().includes(searchTerm.toLowerCase())) &&
        (categoryFilter === 'all' || doc.category === categoryFilter)
    );

    // Get unique categories for filter dropdown
    const categories = ['all', ...new Set(bidDocuments.map(doc => doc.category))];

    return (
        <>
            <Head title="Bid Invitations">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className={`min-h-screen flex flex-col overflow-x-hidden bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative`}>
                <Header />

                <main className="flex-grow pt-24 pb-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section - Improved */}
                        <div className="mb-16 bg-white dark:bg-gray-800/50 rounded-xl shadow-sm overflow-hidden">
                            <div className="flex flex-col md:flex-row">
                                {/* Content Side */}
                                <div className="p-8 md:p-10 md:w-3/5">
                                    <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                        <Award className="w-5 h-5" />
                                    </div>
                                    <h1 className="text-4xl md:text-5xl font-bold mb-4 leading-tight">
                                        <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                            Bid Invitation Documents
                                        </span>
                                    </h1>
                                    <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-2xl">
                                        Browse all available bid invitations published for ongoing procurement activities,
                                        ensuring transparent and equitable access to bidding opportunities.
                                    </p>
                                    <div className="flex flex-wrap gap-4 mb-6">
                                        <div className="flex items-center text-gray-700 dark:text-gray-300">
                                            <Shield className="w-4 h-4 mr-2 text-teal-500" />
                                            <span>Secure Documents</span>
                                        </div>
                                        <div className="flex items-center text-gray-700 dark:text-gray-300">
                                            <Users className="w-4 h-4 mr-2 text-teal-500" />
                                            <span>Equal Opportunity</span>
                                        </div>
                                        <div className="flex items-center text-gray-700 dark:text-gray-300">
                                            <Clock className="w-4 h-4 mr-2 text-teal-500" />
                                            <span>Timely Notifications</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Visual Side */}
                                <div className="md:w-2/5 bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-8 flex items-center justify-center">
                                    <div className="relative">
                                        <div className="absolute inset-0 bg-teal-500/10 dark:bg-teal-500/5 rounded-full animate-pulse"></div>
                                        <div className="relative flex items-center justify-center p-6 rounded-full bg-white dark:bg-gray-800 shadow-md">
                                            <Award className="w-16 h-16 text-teal-600 dark:text-teal-400" />
                                        </div>
                                        <div className="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/3">
                                            <div className="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full shadow-sm">
                                                <FileText className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                            </div>
                                        </div>
                                        <div className="absolute bottom-0 left-0 transform -translate-x-1/3 translate-y-1/4">
                                            <div className="bg-green-100 dark:bg-green-900/30 p-3 rounded-full shadow-sm">
                                                <Search className="w-6 h-6 text-green-600 dark:text-green-400" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Search and Filter Section */}
                        <div className="mb-8">
                            <div className="flex flex-col md:flex-row gap-4 items-stretch md:items-center">
                                <div className="relative flex-grow">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search className="h-5 w-5 text-gray-400" />
                                    </div>
                                    <Input
                                        type="text"
                                        className="pl-10"
                                        placeholder="Search by title, reference number, or procurement ID..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                </div>

                                <div className="w-full md:w-48">
                                    <Select
                                        value={categoryFilter}
                                        onValueChange={(value) => setCategoryFilter(value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map(category => (
                                                <SelectItem key={category} value={category}>
                                                    {category === 'all' ? 'All Categories' : category}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Bid Invitation Documents Table */}
                        <Card className="mb-16">
                            <CardContent className="p-0 overflow-hidden">
                                {isLoading ? (
                                    <div className="flex justify-center items-center py-12">
                                        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-teal-500"></div>
                                    </div>
                                ) : filteredDocuments.length > 0 ? (
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader className="border-b border-gray-200 dark:border-gray-700">
                                                <TableRow>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Procurement ID</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Publication Date</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Closing Date</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Signatory</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</TableHead>
                                                    <TableHead className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody className="divide-y divide-gray-200 dark:divide-gray-700">
                                                {filteredDocuments.map((document) => (
                                                    <TableRow
                                                        key={document.id}
                                                        className={`hover:bg-gray-50/50 dark:hover:bg-gray-800/50 ${document.status === 'Open' && isClosingSoon(document.closing_date)
                                                            ? 'bg-amber-50 dark:bg-amber-900/20'
                                                            : ''
                                                            }`}
                                                    >
                                                        <TableCell className="whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                            {document.procurement_id}
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                            {document.title}
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                            {document.reference_number}
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                            {document.publication_date}
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                            {document.closing_date}
                                                            {document.status === 'Open' && isClosingSoon(document.closing_date) && (
                                                                <Badge variant="outline" className="ml-2 bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border-amber-200 dark:border-amber-800">
                                                                    <Clock className="h-3 w-3 mr-1" />
                                                                    Closing Soon
                                                                </Badge>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                            {document.signatory_details}
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap">
                                                            <Badge variant={document.status === 'Open' ? 'default' : 'destructive'}>
                                                                {document.status}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                asChild
                                                                title={`Document hash: ${document.hash}`}
                                                            >
                                                                <a href={document.download_url} className="flex items-center gap-1">
                                                                    <Download className="h-4 w-4" />
                                                                    Download
                                                                </a>
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ) : (
                                    <div className="text-center py-12">
                                        <FileText className="mx-auto h-12 w-12 text-gray-400" />
                                        <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-white">No bid invitations found</h3>
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Try adjusting your search or filter to find what you're looking for.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Information Section About Bid Invitations */}
                        <Card className="mb-16">
                            <CardHeader>
                                <CardTitle>About Bid Invitations</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    Bid invitation documents are published as part of Stage 3 of the procurement process. These documents invite
                                    qualified suppliers or contractors to submit bids for specific goods, services, or infrastructure projects.
                                </p>
                                <p className="text-gray-600 dark:text-gray-300">
                                    Each bid invitation includes important details such as technical specifications, submission requirements,
                                    deadlines, and evaluation criteria. Interested bidders should download and carefully review these documents
                                    before preparing their submissions.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
