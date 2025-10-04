import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\BacChairmanController::index
 * @see app/Http/Controllers/BacChairmanController.php:47
 * @route '/bac-chairman/dashboard'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/bac-chairman/dashboard',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\BacChairmanController::index
 * @see app/Http/Controllers/BacChairmanController.php:47
 * @route '/bac-chairman/dashboard'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\BacChairmanController::index
 * @see app/Http/Controllers/BacChairmanController.php:47
 * @route '/bac-chairman/dashboard'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\BacChairmanController::index
 * @see app/Http/Controllers/BacChairmanController.php:47
 * @route '/bac-chairman/dashboard'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});
const BacChairmanController = { index };

export default BacChairmanController;
