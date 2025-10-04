import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ViewProcurementsController::index
 * @see app/Http/Controllers/ViewProcurementsController.php:87
 * @route '/admin/procurements-list'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/procurements-list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ViewProcurementsController::index
 * @see app/Http/Controllers/ViewProcurementsController.php:87
 * @route '/admin/procurements-list'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ViewProcurementsController::index
 * @see app/Http/Controllers/ViewProcurementsController.php:87
 * @route '/admin/procurements-list'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ViewProcurementsController::index
 * @see app/Http/Controllers/ViewProcurementsController.php:87
 * @route '/admin/procurements-list'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const procurementsList = {
    index: Object.assign(index, index),
}

export default procurementsList