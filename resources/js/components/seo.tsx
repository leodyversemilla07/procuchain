import { Head } from '@inertiajs/react';

interface SEOProps {
    title: string;
    description: string;
    keywords?: string;
    image?: string;
    type?: 'website' | 'article';
    structuredData?: object;
}

export default function SEO({ title, description, keywords = '', image = '/logo.png', type = 'website', structuredData }: SEOProps) {
    const fullTitle = title.includes('ProcuChain') ? title : `${title} - ProcuChain`;
    const currentUrl = typeof window !== 'undefined' ? window.location.href : '';

    return (
        <Head title={title}>
            {/* Basic Meta Tags */}
            <meta name="description" content={description} />
            {keywords && <meta name="keywords" content={keywords} />}

            {/* Open Graph / Facebook */}
            <meta property="og:type" content={type} />
            <meta property="og:url" content={currentUrl} />
            <meta property="og:title" content={fullTitle} />
            <meta property="og:description" content={description} />
            <meta property="og:image" content={image} />

            {/* Twitter */}
            <meta property="twitter:card" content="summary_large_image" />
            <meta property="twitter:url" content={currentUrl} />
            <meta property="twitter:title" content={fullTitle} />
            <meta property="twitter:description" content={description} />
            <meta property="twitter:image" content={image} />

            {/* Structured Data */}
            {structuredData && <script type="application/ld+json">{JSON.stringify(structuredData)}</script>}
        </Head>
    );
}
