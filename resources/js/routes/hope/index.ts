import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import procurementsList from './procurements-list'
import procurements from './procurements'
/**
* @see \App\Http\Controllers\HopeController::dashboard
 * @see app/Http/Controllers/HopeController.php:47
 * @route '/hope/dashboard'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/hope/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\HopeController::dashboard
 * @see app/Http/Controllers/HopeController.php:47
 * @route '/hope/dashboard'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\HopeController::dashboard
 * @see app/Http/Controllers/HopeController.php:47
 * @route '/hope/dashboard'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\HopeController::dashboard
 * @see app/Http/Controllers/HopeController.php:47
 * @route '/hope/dashboard'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})
const hope = {
    dashboard: Object.assign(dashboard, dashboard),
procurementsList: Object.assign(procurementsList, procurementsList),
procurements: Object.assign(procurements, procurements),
}

export default hope