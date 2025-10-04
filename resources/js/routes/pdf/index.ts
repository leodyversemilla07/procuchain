import { applyUrlDefaults, queryParams, type RouteDefinition, type RouteQueryOptions } from './../../wayfinder';
/**
 * @see \App\Http\Controllers\DocumentViewController::viewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
export const viewer = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: viewer.url(args, options),
    method: 'get',
});

viewer.definition = {
    methods: ['get', 'head'],
    url: '/pdf-viewer/{fileKey}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DocumentViewController::viewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
viewer.url = (args: { fileKey: string | number } | [fileKey: string | number] | string | number, options?: RouteQueryOptions) => {
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

    return viewer.definition.url.replace('{fileKey}', parsedArgs.fileKey.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DocumentViewController::viewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
viewer.get = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: viewer.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DocumentViewController::viewer
 * @see app/Http/Controllers/DocumentViewController.php:253
 * @route '/pdf-viewer/{fileKey}'
 */
viewer.head = (
    args: { fileKey: string | number } | [fileKey: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: viewer.url(args, options),
    method: 'head',
});
const pdf = {
    viewer: Object.assign(viewer, viewer),
};

export default pdf;
