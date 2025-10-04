import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see routes/file-uploads-ui-preview.php:67
 * @route '/bac-secretariat/ntp-upload'
 */
export const simple = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});

simple.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/ntp-upload',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/file-uploads-ui-preview.php:67
 * @route '/bac-secretariat/ntp-upload'
 */
simple.url = (options?: RouteQueryOptions) => {
    return simple.definition.url + queryParams(options);
};

/**
 * @see routes/file-uploads-ui-preview.php:67
 * @route '/bac-secretariat/ntp-upload'
 */
simple.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: simple.url(options),
    method: 'get',
});
/**
 * @see routes/file-uploads-ui-preview.php:67
 * @route '/bac-secretariat/ntp-upload'
 */
simple.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: simple.url(options),
    method: 'head',
});
const ntpUpload = {
    simple: Object.assign(simple, simple),
};

export default ntpUpload;
