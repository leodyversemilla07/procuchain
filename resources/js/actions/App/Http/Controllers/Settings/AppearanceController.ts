import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Settings\AppearanceController::edit
 * @see app/Http/Controllers/Settings/AppearanceController.php:14
 * @route '/settings/appearance'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
});

edit.definition = {
    methods: ['get', 'head'],
    url: '/settings/appearance',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Settings\AppearanceController::edit
 * @see app/Http/Controllers/Settings/AppearanceController.php:14
 * @route '/settings/appearance'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Settings\AppearanceController::edit
 * @see app/Http/Controllers/Settings/AppearanceController.php:14
 * @route '/settings/appearance'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Settings\AppearanceController::edit
 * @see app/Http/Controllers/Settings/AppearanceController.php:14
 * @route '/settings/appearance'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
});
const AppearanceController = { edit };

export default AppearanceController;
