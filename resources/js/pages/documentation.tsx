import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, Book, ExternalLink, Code, Server, Search, Grid, List, Download } from 'lucide-react';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

export default function Documentation() {
    const [searchQuery, setSearchQuery] = useState('');
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

    const docCategories = [
        {
            id: 'architecture',
            name: 'System Architecture',
            description: 'Diagrams and explanations of the system structure and data flow',
            icon: <Server className="w-6 h-6 text-teal-500" />
        },
        {
            id: 'technical',
            name: 'Technical Documentation',
            description: 'Detailed technical specifications and code documentation',
            icon: <Code className="w-6 h-6 text-teal-500" />
        },
        {
            id: 'user',
            name: 'User Guides',
            description: 'Instructions and workflows for using the system',
            icon: <Book className="w-6 h-6 text-teal-500" />
        },
        {
            id: 'research',
            name: 'Research Papers',
            description: 'Academic papers and findings related to the project',
            icon: <FileText className="w-6 h-6 text-teal-500" />
        },
    ];

    const documents = [
        {
            title: 'System Architecture Overview',
            description: 'High-level description of ProcuChain system architecture including blockchain integration',
            category: 'architecture',
            format: 'PDF',
            size: '2.4 MB',
            date: '2025-04-15',
            url: '/docs/system_architecture.pdf',
            featured: true
        },
        {
            title: 'Database Entity Relationship Diagram',
            description: 'Complete ERD showing the database structure and relationships',
            category: 'architecture',
            format: 'PDF',
            size: '1.8 MB',
            date: '2025-04-10',
            url: '/docs/database_erd.pdf'
        },
        {
            title: 'API Documentation',
            description: 'Documentation for all RESTful API endpoints with examples',
            category: 'technical',
            format: 'HTML',
            size: '3.2 MB',
            date: '2025-04-12',
            url: '/docs/api_documentation.html'
        },
        {
            title: 'Blockchain Integration Guide',
            description: 'Technical guide for the MultiChain integration and document hashing process',
            category: 'technical',
            format: 'PDF',
            size: '4.1 MB',
            date: '2025-04-08',
            url: '/docs/blockchain_integration.pdf',
            featured: true
        },
        {
            title: 'User Manual - Administrators',
            description: 'Comprehensive guide for system administrators',
            category: 'user',
            format: 'PDF',
            size: '5.6 MB',
            date: '2025-04-14',
            url: '/docs/admin_manual.pdf'
        },
        {
            title: 'User Manual - Procurement Staff',
            description: 'Guide for procurement officers using ProcuChain',
            category: 'user',
            format: 'PDF',
            size: '4.8 MB',
            date: '2025-04-14',
            url: '/docs/procurement_manual.pdf'
        },
        {
            title: 'Implementation of Blockchain in Government Procurement',
            description: 'Research paper on the implementation and benefits of blockchain in government procurement',
            category: 'research',
            format: 'PDF',
            size: '2.1 MB',
            date: '2025-03-30',
            url: '/docs/research_paper.pdf',
            featured: true
        },
        {
            title: 'Security Assessment Report',
            description: 'Security audit findings and recommendations',
            category: 'technical',
            format: 'PDF',
            size: '3.3 MB',
            date: '2025-04-05',
            url: '/docs/security_assessment.pdf'
        },
        {
            title: 'Deployment Guide',
            description: 'Step-by-step guide for deploying ProcuChain in a production environment',
            category: 'technical',
            format: 'PDF',
            size: '2.7 MB',
            date: '2025-04-11',
            url: '/docs/deployment_guide.pdf'
        },
        {
            title: 'Technology Stack Overview',
            description: 'Overview of all technologies used in ProcuChain',
            category: 'architecture',
            format: 'PDF',
            size: '1.5 MB',
            date: '2025-04-02',
            url: '/docs/tech_stack.pdf'
        },
        {
            title: 'User Flow Diagrams',
            description: 'Visual representations of user journeys through the system',
            category: 'architecture',
            format: 'PDF',
            size: '3.9 MB',
            date: '2025-04-03',
            url: '/docs/user_flows.pdf'
        },
        {
            title: 'Transparency in Government Procurement: A Case Study',
            description: 'Academic case study on transparency improvements using blockchain',
            category: 'research',
            format: 'PDF',
            size: '4.2 MB',
            date: '2025-03-15',
            url: '/docs/transparency_case_study.pdf'
        }
    ];

    // Filter documents based on search query
    const filteredDocuments = documents.filter(doc => {
        if (!searchQuery) return true;
        return (
            doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            doc.description.toLowerCase().includes(searchQuery.toLowerCase())
        );
    });

    // Calculate documents by category
    const docsByCategory = docCategories.reduce((acc, category) => {
        acc[category.id] = filteredDocuments.filter(doc => doc.category === category.id);
        return acc;
    }, {} as Record<string, typeof documents>);

    return (
        <>
            <Head title="Documentation">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="Technical documentation, architecture diagrams, and user guides for ProcuChain - a blockchain-powered procurement system." />
            </Head>
            <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                <Header />

                <main className="flex-grow pt-24 pb-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="mb-12 text-center">
                            <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                <FileText className="w-6 h-6" />
                            </div>
                            <h1 className="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                                <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                    Project Documentation
                                </span>
                            </h1>
                            <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                                Access comprehensive technical documentation, architecture diagrams, research papers, 
                                and user guides for the ProcuChain project
                            </p>
                            <div className="flex justify-center">
                                <div className="relative max-w-md w-full">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <Input
                                        type="search"
                                        placeholder="Search documentation..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-9 w-full bg-white dark:bg-gray-800"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Architecture Diagram Section */}
                        <Card className="mb-12">
                            <CardHeader className="border-b border-gray-100 dark:border-gray-800">
                                <CardTitle className="text-2xl">System Architecture Overview</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-6">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                                    <div className="col-span-2">
                                        <div className="bg-gray-100 dark:bg-gray-800 rounded-lg p-6 h-full flex items-center justify-center">
                                            <div className="relative w-full max-w-2xl">
                                                <div className="aspect-w-16 aspect-h-9">
                                                    <img 
                                                        src="/images/architecture-diagram.png" 
                                                        alt="ProcuChain System Architecture"
                                                        className="rounded-lg object-cover"
                                                        onError={(e) => {
                                                            e.currentTarget.src = "https://via.placeholder.com/800x450?text=Architecture+Diagram";
                                                        }}
                                                    />
                                                </div>
                                                <div className="absolute bottom-0 right-0 bg-teal-600 text-white text-xs px-2 py-1 rounded-tl-lg">
                                                    Fig 1: System Architecture
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-bold mb-3">Key Components</h3>
                                        <ul className="space-y-4">
                                            <li className="flex">
                                                <div className="flex-shrink-0 w-8 h-8 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-3">
                                                    <span className="font-semibold">1</span>
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold">Frontend Interface</h4>
                                                    <p className="text-sm text-gray-600 dark:text-gray-300">React/TypeScript + Tailwind CSS</p>
                                                </div>
                                            </li>
                                            <li className="flex">
                                                <div className="flex-shrink-0 w-8 h-8 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-3">
                                                    <span className="font-semibold">2</span>
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold">Backend API</h4>
                                                    <p className="text-sm text-gray-600 dark:text-gray-300">Laravel PHP Framework</p>
                                                </div>
                                            </li>
                                            <li className="flex">
                                                <div className="flex-shrink-0 w-8 h-8 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-3">
                                                    <span className="font-semibold">3</span>
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold">Blockchain Layer</h4>
                                                    <p className="text-sm text-gray-600 dark:text-gray-300">MultiChain Integration</p>
                                                </div>
                                            </li>
                                            <li className="flex">
                                                <div className="flex-shrink-0 w-8 h-8 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center text-teal-600 dark:text-teal-400 mr-3">
                                                    <span className="font-semibold">4</span>
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold">Database Storage</h4>
                                                    <p className="text-sm text-gray-600 dark:text-gray-300">MySQL Database</p>
                                                </div>
                                            </li>
                                        </ul>
                                        
                                        <Button asChild className="mt-6 bg-teal-600 hover:bg-teal-700 text-white w-full">
                                            <a href="/docs/system_architecture.pdf">
                                                <Download className="mr-2 h-4 w-4" />
                                                Download Full Architecture Document
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Document Repository Section */}
                        <div className="mb-12">
                            <div className="mb-6">
                                <h2 className="text-2xl font-bold mb-3">Document Repository</h2>
                                <p className="text-gray-600 dark:text-gray-300">
                                    Browse our comprehensive collection of technical documents, user guides, and research papers.
                                </p>
                            </div>

                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                                <div className="flex space-x-2">
                                    <Button 
                                        variant={viewMode === 'grid' ? "default" : "outline"} 
                                        size="sm" 
                                        onClick={() => setViewMode('grid')}
                                        className={viewMode === 'grid' ? "bg-teal-600 hover:bg-teal-700 text-white" : ""}
                                    >
                                        <Grid className="w-4 h-4" />
                                    </Button>
                                    <Button 
                                        variant={viewMode === 'list' ? "default" : "outline"} 
                                        size="sm" 
                                        onClick={() => setViewMode('list')}
                                        className={viewMode === 'list' ? "bg-teal-600 hover:bg-teal-700 text-white" : ""}
                                    >
                                        <List className="w-4 h-4" />
                                    </Button>
                                </div>
                                
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    {filteredDocuments.length} document{filteredDocuments.length !== 1 ? 's' : ''} found
                                </div>
                            </div>

                            <Tabs defaultValue="all" className="w-full">
                                <TabsList className="mb-6">
                                    <TabsTrigger value="all">All Documents</TabsTrigger>
                                    {docCategories.map(category => (
                                        <TabsTrigger key={category.id} value={category.id}>
                                            {category.name}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                                
                                <TabsContent value="all">
                                    <div className={viewMode === 'grid' 
                                        ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6'
                                        : 'flex flex-col space-y-4'
                                    }>
                                        {filteredDocuments.length > 0 ? (
                                            filteredDocuments.map((doc, idx) => (
                                                viewMode === 'grid' 
                                                    ? <DocumentCard key={idx} document={doc} /> 
                                                    : <DocumentListItem key={idx} document={doc} />
                                            ))
                                        ) : (
                                            <div className="col-span-full py-12 text-center">
                                                <FileText className="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                                                <p className="text-gray-500 dark:text-gray-400">No documents found matching your search.</p>
                                                <Button 
                                                    variant="link" 
                                                    onClick={() => setSearchQuery('')}
                                                    className="mt-2 text-teal-600 dark:text-teal-400"
                                                >
                                                    Clear search
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </TabsContent>
                                
                                {docCategories.map(category => (
                                    <TabsContent key={category.id} value={category.id}>
                                        <div className="mb-6">
                                            <div className="flex items-center space-x-3 mb-2">
                                                <div className="p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300">
                                                    {category.icon}
                                                </div>
                                                <h3 className="text-xl font-bold">{category.name}</h3>
                                            </div>
                                            <p className="text-gray-600 dark:text-gray-300">{category.description}</p>
                                        </div>
                                        <div className={viewMode === 'grid' 
                                            ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6'
                                            : 'flex flex-col space-y-4'
                                        }>
                                            {docsByCategory[category.id]?.length > 0 ? (
                                                docsByCategory[category.id].map((doc, idx) => (
                                                    viewMode === 'grid' 
                                                        ? <DocumentCard key={idx} document={doc} /> 
                                                        : <DocumentListItem key={idx} document={doc} />
                                                ))
                                            ) : (
                                                <div className="col-span-full py-12 text-center">
                                                    <p className="text-gray-500 dark:text-gray-400">No documents found in this category.</p>
                                                </div>
                                            )}
                                        </div>
                                    </TabsContent>
                                ))}
                            </Tabs>
                        </div>

                        {/* Additional Resources */}
                        <div className="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-sm">
                            <h2 className="text-2xl font-bold mb-6">Additional Resources</h2>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <ResourceCard 
                                    title="External References"
                                    description="Links to standards, specifications, and external documentation that informed our development process."
                                    button="Browse References"
                                    url="/docs/references.pdf"
                                    icon={<ExternalLink className="w-8 h-8 text-blue-500" />}
                                />
                                
                                <ResourceCard 
                                    title="Video Tutorials"
                                    description="Step-by-step video guides for using various features of the ProcuChain system."
                                    button="Watch Tutorials"
                                    url="/tutorials"
                                    icon={<Code className="w-8 h-8 text-purple-500" />}
                                />
                                
                                <ResourceCard 
                                    title="Development Blog"
                                    description="Stay updated with the latest project developments, improvements, and future plans."
                                    button="Read Blog"
                                    url="/blog"
                                    icon={<Book className="w-8 h-8 text-teal-500" />}
                                />
                            </div>
                            
                            <div className="mt-12 pt-6 border-t border-gray-100 dark:border-gray-700">
                                <h3 className="font-semibold mb-4">Need Custom Documentation?</h3>
                                <p className="text-gray-600 dark:text-gray-300 mb-4">
                                    If you're looking for specific documentation or have questions about implementation, 
                                    our team is ready to help create customized documentation for your needs.
                                </p>
                                <Button asChild className="bg-teal-600 hover:bg-teal-700 text-white">
                                    <a href={route('contact')}>
                                        Contact for Custom Documentation
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}

interface DocumentProps {
    document: {
        title: string;
        description: string;
        category: string;
        format: string;
        size: string;
        date: string;
        url: string;
        featured?: boolean;
    };
}

function DocumentCard({ document }: DocumentProps) {
    const getFormatIcon = (format: string) => {
        switch (format.toLowerCase()) {
            case 'pdf':
                return <FileText className="h-5 w-5 text-red-500" />;
            case 'html':
                return <Code className="h-5 w-5 text-blue-500" />;
            default:
                return <FileText className="h-5 w-5 text-gray-500" />;
        }
    };

    return (
        <div className={`bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border ${document.featured ? 'border-teal-200 dark:border-teal-900' : 'border-gray-100 dark:border-gray-700'} hover:shadow-md transition-shadow`}>
            {document.featured && (
                <div className="mb-3 inline-block py-1 px-2 rounded-md bg-teal-50 dark:bg-teal-900/30 border border-teal-200 dark:border-teal-800 text-teal-700 dark:text-teal-300 text-xs font-medium">
                    Featured
                </div>
            )}
            <h3 className="text-lg font-semibold mb-2">{document.title}</h3>
            <p className="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-2">{document.description}</p>
            <div className="flex items-center justify-between mt-auto">
                <div className="flex items-center">
                    {getFormatIcon(document.format)}
                    <span className="text-xs text-gray-500 dark:text-gray-400 ml-2">{document.format} · {document.size}</span>
                </div>
                <Button asChild size="sm" className="bg-teal-600 hover:bg-teal-700 text-white">
                    <a href={document.url} target="_blank">
                        <Download className="mr-2 h-3 w-3" />
                        Download
                    </a>
                </Button>
            </div>
        </div>
    );
}

function DocumentListItem({ document }: DocumentProps) {
    const getFormatIcon = (format: string) => {
        switch (format.toLowerCase()) {
            case 'pdf':
                return <FileText className="h-5 w-5 text-red-500" />;
            case 'html':
                return <Code className="h-5 w-5 text-blue-500" />;
            default:
                return <FileText className="h-5 w-5 text-gray-500" />;
        }
    };

    return (
        <div className={`flex items-center bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border ${document.featured ? 'border-teal-200 dark:border-teal-900' : 'border-gray-100 dark:border-gray-700'} hover:shadow-md transition-shadow`}>
            <div className="mr-4 flex-shrink-0">
                {getFormatIcon(document.format)}
            </div>
            <div className="flex-grow">
                <div className="flex items-center">
                    <h3 className="text-base font-semibold">{document.title}</h3>
                    {document.featured && (
                        <div className="ml-3 py-0.5 px-1.5 rounded-md bg-teal-50 dark:bg-teal-900/30 border border-teal-200 dark:border-teal-800 text-teal-700 dark:text-teal-300 text-xs font-medium">
                            Featured
                        </div>
                    )}
                </div>
                <p className="text-gray-600 dark:text-gray-300 text-sm line-clamp-1">{document.description}</p>
                <div className="flex items-center mt-1 text-xs text-gray-500 dark:text-gray-400">
                    <span>{document.format}</span>
                    <span className="mx-2">·</span>
                    <span>{document.size}</span>
                    <span className="mx-2">·</span>
                    <span>Updated: {new Date(document.date).toLocaleDateString()}</span>
                </div>
            </div>
            <Button asChild size="sm" variant="outline" className="flex-shrink-0 ml-4">
                <a href={document.url} target="_blank">
                    <Download className="mr-2 h-3 w-3" />
                    Download
                </a>
            </Button>
        </div>
    );
}

interface ResourceCardProps {
    title: string;
    description: string;
    button: string;
    url: string;
    icon: React.ReactNode;
}

function ResourceCard({ title, description, button, url, icon }: ResourceCardProps) {
    return (
        <div className="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6 hover:shadow-md transition-shadow">
            <div className="flex justify-center mb-4">
                {icon}
            </div>
            <h3 className="text-lg font-bold mb-2 text-center">{title}</h3>
            <p className="text-gray-600 dark:text-gray-300 text-sm mb-4 text-center">
                {description}
            </p>
            <div className="flex justify-center">
                <Button asChild variant="outline" size="sm">
                    <a href={url}>
                        {button}
                        <ExternalLink className="ml-2 h-3 w-3" />
                    </a>
                </Button>
            </div>
        </div>
    );
}