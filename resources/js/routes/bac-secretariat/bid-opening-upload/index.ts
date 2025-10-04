import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see routes/file-uploads-ui-preview.php:31
 * @route '/bac-secretariat/bid-opening-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});

simple.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/bid-opening-upload',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/file-uploads-ui-preview.php:31
 * @route '/bac-secretariat/bid-opening-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options);
};

/**
 * @see routes/file-uploads-ui-preview.php:31
 * @route '/bac-secretariat/bid-opening-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});
/**
 * @see routes/file-uploads-ui-preview.php:31
 * @route '/bac-secretariat/bid-opening-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
});
const bidOpeningUpload = {
    simple: Object.assign(simple, simple),
};

export default bidOpeningUpload;
