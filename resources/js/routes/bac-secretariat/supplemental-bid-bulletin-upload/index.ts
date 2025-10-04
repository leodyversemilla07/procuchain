import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see routes/file-uploads-ui-preview.php:19
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});

simple.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/supplemental-bid-bulletin-upload',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/file-uploads-ui-preview.php:19
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options);
};

/**
 * @see routes/file-uploads-ui-preview.php:19
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});
/**
 * @see routes/file-uploads-ui-preview.php:19
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
});
const supplementalBidBulletinUpload = {
    simple: Object.assign(simple, simple),
};

export default supplementalBidBulletinUpload;
