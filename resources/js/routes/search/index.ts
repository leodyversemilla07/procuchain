import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../wayfinder';
/**
 * @see \App\Http\Controllers\SearchController::suggestions
 * @see app/Http/Controllers/SearchController.php:246
 * @route '/search/suggestions'
 */
export const suggestions = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suggestions.url(options),
    method: 'get',
});

suggestions.definition = {
    methods: ['get', 'head'],
    url: '/search/suggestions',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SearchController::suggestions
 * @see app/Http/Controllers/SearchController.php:246
 * @route '/search/suggestions'
 */
suggestions.url = (options?: RouteQueryOptions) => {
    return suggestions.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\SearchController::suggestions
 * @see app/Http/Controllers/SearchController.php:246
 * @route '/search/suggestions'
 */
suggestions.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suggestions.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\SearchController::suggestions
 * @see app/Http/Controllers/SearchController.php:246
 * @route '/search/suggestions'
 */
suggestions.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: suggestions.url(options),
    method: 'head',
});
const search = {
    suggestions: Object.assign(suggestions, suggestions),
};

export default search;
