import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see routes/file-uploads-ui-preview.php:61
 * @route '/bac-secretariat/performance-bond-contract-po-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});

simple.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/performance-bond-contract-po-upload',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/file-uploads-ui-preview.php:61
 * @route '/bac-secretariat/performance-bond-contract-po-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options);
};

/**
 * @see routes/file-uploads-ui-preview.php:61
 * @route '/bac-secretariat/performance-bond-contract-po-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});
/**
 * @see routes/file-uploads-ui-preview.php:61
 * @route '/bac-secretariat/performance-bond-contract-po-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
});
const performanceBondContractPoUpload = {
    simple: Object.assign(simple, simple),
};

export default performanceBondContractPoUpload;
