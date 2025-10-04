import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})
const BacSecretariatController = { dashboard }

export default BacSecretariatController