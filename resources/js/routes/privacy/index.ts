import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../wayfinder';
/**
 * @see routes/web.php:222
 * @route '/privacy.pdf'
 */
export const policy = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: policy.url(options),
    method: 'get',
});

policy.definition = {
    methods: ['get', 'head'],
    url: '/privacy.pdf',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/web.php:222
 * @route '/privacy.pdf'
 */
policy.url = (options?: RouteQueryOptions) => {
    return policy.definition.url + queryParams(options);
};

/**
 * @see routes/web.php:222
 * @route '/privacy.pdf'
 */
policy.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: policy.url(options),
    method: 'get',
});
/**
 * @see routes/web.php:222
 * @route '/privacy.pdf'
 */
policy.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: policy.url(options),
    method: 'head',
});
const privacy = {
    policy: Object.assign(policy, policy),
};

export default privacy;
