import { Head, Link } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { FileText, Package, AlertTriangle, SearchX } from 'lucide-react'; // Import icons

interface SearchResult {
    id: string | number;
    title: string;
    description: string;
    link?: string;
    type: 'Page' | 'Procurement'; // Add type property
}

interface SearchIndexProps {
    query: string;
    results: SearchResult[];
    searchError?: string | null;
}

export default function SearchIndex({ query, results, searchError }: SearchIndexProps) {
    return (
        <>
            <Head title={`Search Results: ${query}`} />
            <div className="min-h-screen flex flex-col bg-gradient-to-br from-gray-50 to-blue-50 dark:from-gray-950 dark:to-gray-900 dark:text-white">
                <Header />

                <main className="flex-grow pt-[76px]">
                    <div className="container max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
                        <h1 className="text-3xl md:text-4xl font-bold mb-8 text-center">
                            Search results for: <span className="text-teal-600 dark:text-teal-400">"{query}"</span>
                        </h1>

                        {searchError && (
                            <div className="bg-red-100 dark:bg-red-900/30 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 rounded-md shadow mb-8" role="alert">
                                <div className="flex items-center">
                                    <AlertTriangle className="h-6 w-6 mr-3 text-red-500" />
                                    <div>
                                        <p className="font-bold">Error</p>
                                        <p>{searchError}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {!searchError && results.length > 0 ? (
                            <div className="bg-white dark:bg-gray-800/50 rounded-lg shadow-md overflow-hidden">
                                <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {results.map((result) => (
                                        <li key={result.id} className="p-4 sm:p-6 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors duration-150">
                                            <div className="flex items-start space-x-4">
                                                <div className="flex-shrink-0 mt-1">
                                                    {result.type === 'Procurement' ? (
                                                        <Package className="w-6 h-6 text-blue-500" />
                                                    ) : (
                                                        <FileText className="w-6 h-6 text-teal-500" />
                                                    )}
                                                </div>
                                                <div className="flex-grow">
                                                    <h2 className="text-lg md:text-xl font-semibold mb-1">
                                                        {result.link ? (
                                                            <Link
                                                                href={result.link}
                                                                className="text-gray-900 dark:text-white hover:text-teal-600 dark:hover:text-teal-400 hover:underline focus:outline-none focus:ring-2 focus:ring-teal-500 rounded"
                                                            >
                                                                {result.title}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-gray-900 dark:text-white">{result.title}</span>
                                                        )}
                                                    </h2>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{result.description}</p>
                                                    <span className={`inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-medium ${result.type === 'Procurement' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200'}`}>
                                                        {result.type}
                                                    </span>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : !searchError && (
                            <div className="text-center py-16 px-6 bg-white dark:bg-gray-800/50 rounded-lg shadow-md">
                                <SearchX className="w-16 h-16 text-gray-400 dark:text-gray-500 mx-auto mb-4" />
                                <p className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No results found</p>
                                <p className="text-gray-500 dark:text-gray-400">We couldn't find anything matching "{query}". Try searching for something else.</p>
                            </div>
                        )}
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
