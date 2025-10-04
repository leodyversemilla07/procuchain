import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
 * @see routes/file-uploads-ui-preview.php:37
 * @route '/bac-secretariat/bid-evaluation-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
})

simple.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/bid-evaluation-upload',
} satisfies RouteDefinition<["get","head"]>

/**
 * @see routes/file-uploads-ui-preview.php:37
 * @route '/bac-secretariat/bid-evaluation-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options)
}

/**
 * @see routes/file-uploads-ui-preview.php:37
 * @route '/bac-secretariat/bid-evaluation-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
})
/**
 * @see routes/file-uploads-ui-preview.php:37
 * @route '/bac-secretariat/bid-evaluation-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
})
const bidEvaluationUpload = {
    simple: Object.assign(simple, simple),
}

export default bidEvaluationUpload