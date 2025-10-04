import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AdminController::recent
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
export const recent = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recent.url(options),
    method: 'get',
})

recent.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs/recent',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::recent
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
recent.url = (options?: RouteQueryOptions) => {
    return recent.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::recent
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
recent.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recent.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::recent
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
recent.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: recent.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::statistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
export const statistics = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: statistics.url(options),
    method: 'get',
})

statistics.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs/statistics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::statistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
statistics.url = (options?: RouteQueryOptions) => {
    return statistics.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::statistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
statistics.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: statistics.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::statistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
statistics.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: statistics.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::suspicious
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
export const suspicious = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suspicious.url(options),
    method: 'get',
})

suspicious.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs/suspicious',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::suspicious
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
suspicious.url = (options?: RouteQueryOptions) => {
    return suspicious.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::suspicious
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
suspicious.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suspicious.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::suspicious
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
suspicious.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: suspicious.url(options),
    method: 'head',
})
const loginLogs = {
    recent: Object.assign(recent, recent),
statistics: Object.assign(statistics, statistics),
suspicious: Object.assign(suspicious, suspicious),
}

export default loginLogs