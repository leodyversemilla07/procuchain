import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\DocumentViewController::download
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
export const download = (args: { fileKey: string | number } | [fileKey: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/secure-file/{fileKey}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DocumentViewController::download
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
download.url = (args: { fileKey: string | number } | [fileKey: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { fileKey: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    fileKey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        fileKey: args.fileKey,
                }

    return download.definition.url
            .replace('{fileKey}', parsedArgs.fileKey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentViewController::download
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
download.get = (args: { fileKey: string | number } | [fileKey: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\DocumentViewController::download
 * @see app/Http/Controllers/DocumentViewController.php:32
 * @route '/secure-file/{fileKey}'
 */
download.head = (args: { fileKey: string | number } | [fileKey: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})
const file = {
    download: Object.assign(download, download),
}

export default file