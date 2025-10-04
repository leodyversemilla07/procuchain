import { applyUrlDefaults, queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\DocumentViewController::downloadFile
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
export const downloadFile = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: downloadFile.url(args, options),
    method: 'get',
});

downloadFile.definition = {
    methods: ['get', 'head'],
    url: '/secure-file/{fileKey}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DocumentViewController::downloadFile
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
downloadFile.url = (args: { fileKey: string | number } | [fileKey: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { fileKey: args };
    }

    if (Array.isArray(args)) {
        args = {
            fileKey: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        fileKey: args.fileKey,
    };

    return downloadFile.definition.url.replace('{fileKey}', parsedArgs.fileKey.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DocumentViewController::downloadFile
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
downloadFile.get = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: downloadFile.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DocumentViewController::downloadFile
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
downloadFile.head = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: downloadFile.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DocumentViewController::showPdfViewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
export const showPdfViewer = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: showPdfViewer.url(args, options),
    method: 'get',
});

showPdfViewer.definition = {
    methods: ['get', 'head'],
    url: '/pdf-viewer/{fileKey}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DocumentViewController::showPdfViewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
showPdfViewer.url = (args: { fileKey: string | number } | [fileKey: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { fileKey: args };
    }

    if (Array.isArray(args)) {
        args = {
            fileKey: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        fileKey: args.fileKey,
    };

    return showPdfViewer.definition.url.replace('{fileKey}', parsedArgs.fileKey.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DocumentViewController::showPdfViewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
showPdfViewer.get = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: showPdfViewer.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DocumentViewController::showPdfViewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
showPdfViewer.head = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: showPdfViewer.url(args, options),
    method: 'head',
});
const DocumentViewController = { downloadFile, showPdfViewer };

export default DocumentViewController;
