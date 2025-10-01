import Footer from '@/components/footer';
import Header from '@/components/header';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, FileText, Package, SearchX } from 'lucide-react'; // Import icons

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
            <div className="flex min-h-screen flex-col bg-gradient-to-br from-gray-50 to-blue-50 dark:from-gray-950 dark:to-gray-900 dark:text-white">
                <Header />

                <main className="flex-grow pt-[76px]">
                    <div className="container mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
                        <h1 className="mb-8 text-center text-3xl font-bold md:text-4xl">
                            Search results for: <span className="text-teal-600 dark:text-teal-400">"{query}"</span>
                        </h1>

                        {searchError && (
                            <div
                                className="mb-8 rounded-md border-l-4 border-red-500 bg-red-100 p-4 text-red-700 shadow dark:bg-red-900/30 dark:text-red-300"
                                role="alert"
                            >
                                <div className="flex items-center">
                                    <AlertTriangle className="mr-3 h-6 w-6 text-red-500" />
                                    <div>
                                        <p className="font-bold">Error</p>
                                        <p>{searchError}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {!searchError && results.length > 0 ? (
                            <div className="overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800/50">
                                <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {results.map((result) => (
                                        <li
                                            key={result.id}
                                            className="p-4 transition-colors duration-150 hover:bg-gray-50 sm:p-6 dark:hover:bg-gray-700/30"
                                        >
                                            <div className="flex items-start space-x-4">
                                                <div className="mt-1 flex-shrink-0">
                                                    {result.type === 'Procurement' ? (
                                                        <Package className="h-6 w-6 text-blue-500" />
                                                    ) : (
                                                        <FileText className="h-6 w-6 text-teal-500" />
                                                    )}
                                                </div>
                                                <div className="flex-grow">
                                                    <h2 className="mb-1 text-lg font-semibold md:text-xl">
                                                        {result.link ? (
                                                            <Link
                                                                href={result.link}
                                                                className="rounded text-gray-900 hover:text-teal-600 hover:underline focus:ring-2 focus:ring-teal-500 focus:outline-none dark:text-white dark:hover:text-teal-400"
                                                            >
                                                                {result.title}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-gray-900 dark:text-white">{result.title}</span>
                                                        )}
                                                    </h2>
                                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{result.description}</p>
                                                    <span
                                                        className={`mt-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium ${result.type === 'Procurement' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200'}`}
                                                    >
                                                        {result.type}
                                                    </span>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : (
                            !searchError && (
                                <div className="rounded-lg bg-white px-6 py-16 text-center shadow-md dark:bg-gray-800/50">
                                    <SearchX className="mx-auto mb-4 h-16 w-16 text-gray-400 dark:text-gray-500" />
                                    <p className="mb-2 text-xl font-medium text-gray-700 dark:text-gray-300">No results found</p>
                                    <p className="text-gray-500 dark:text-gray-400">
                                        We couldn't find anything matching "{query}". Try searching for something else.
                                    </p>
                                </div>
                            )
                        )}
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
