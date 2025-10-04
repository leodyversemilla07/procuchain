import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
 * @see routes/file-uploads-ui-preview.php:55
 * @route '/bac-secretariat/noa-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
})

simple.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/noa-upload',
} satisfies RouteDefinition<["get","head"]>

/**
 * @see routes/file-uploads-ui-preview.php:55
 * @route '/bac-secretariat/noa-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options)
}

/**
 * @see routes/file-uploads-ui-preview.php:55
 * @route '/bac-secretariat/noa-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
})
/**
 * @see routes/file-uploads-ui-preview.php:55
 * @route '/bac-secretariat/noa-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
})
const noaUpload = {
    simple: Object.assign(simple, simple),
}

export default noaUpload