import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
 * @see routes/file-uploads-ui-preview.php:79
 * @route '/bac-secretariat/completion-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
})

simple.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/completion-upload',
} satisfies RouteDefinition<["get","head"]>

/**
 * @see routes/file-uploads-ui-preview.php:79
 * @route '/bac-secretariat/completion-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options)
}

/**
 * @see routes/file-uploads-ui-preview.php:79
 * @route '/bac-secretariat/completion-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
})
/**
 * @see routes/file-uploads-ui-preview.php:79
 * @route '/bac-secretariat/completion-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
})
const completionUpload = {
    simple: Object.assign(simple, simple),
}

export default completionUpload