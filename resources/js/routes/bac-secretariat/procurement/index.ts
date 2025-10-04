import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\ProcurementController::procurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
export const procurementInitiation = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: procurementInitiation.url(options),
    method: 'get',
});

procurementInitiation.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/procurement/procurement-initiation',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::procurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
procurementInitiation.url = (options?: RouteQueryOptions) => {
    return procurementInitiation.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::procurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
procurementInitiation.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: procurementInitiation.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::procurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
procurementInitiation.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: procurementInitiation.url(options),
    method: 'head',
});
const procurement = {
    procurementInitiation: Object.assign(procurementInitiation, procurementInitiation),
};

export default procurement;
