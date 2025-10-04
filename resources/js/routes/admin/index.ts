import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import procurementsList from './procurements-list'
import procurements from './procurements'
import users48860f from './users'
import loginLogs564c52 from './login-logs'
import accounts from './accounts'
/**
* @see \App\Http\Controllers\AdminController::dashboard
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/admin/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::dashboard
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::dashboard
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::dashboard
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::users
 * @see app/Http/Controllers/AdminController.php:405
 * @route '/admin/users'
 */
export const users = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: users.url(options),
    method: 'get',
})

users.definition = {
    methods: ["get","head"],
    url: '/admin/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::users
 * @see app/Http/Controllers/AdminController.php:405
 * @route '/admin/users'
 */
users.url = (options?: RouteQueryOptions) => {
    return users.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::users
 * @see app/Http/Controllers/AdminController.php:405
 * @route '/admin/users'
 */
users.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: users.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::users
 * @see app/Http/Controllers/AdminController.php:405
 * @route '/admin/users'
 */
users.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: users.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::loginLogs
 * @see app/Http/Controllers/AdminController.php:621
 * @route '/admin/login-logs'
 */
export const loginLogs = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: loginLogs.url(options),
    method: 'get',
})

loginLogs.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::loginLogs
 * @see app/Http/Controllers/AdminController.php:621
 * @route '/admin/login-logs'
 */
loginLogs.url = (options?: RouteQueryOptions) => {
    return loginLogs.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::loginLogs
 * @see app/Http/Controllers/AdminController.php:621
 * @route '/admin/login-logs'
 */
loginLogs.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: loginLogs.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::loginLogs
 * @see app/Http/Controllers/AdminController.php:621
 * @route '/admin/login-logs'
 */
loginLogs.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: loginLogs.url(options),
    method: 'head',
})
const admin = {
    dashboard: Object.assign(dashboard, dashboard),
procurementsList: Object.assign(procurementsList, procurementsList),
procurements: Object.assign(procurements, procurements),
users: Object.assign(users, users48860f),
loginLogs: Object.assign(loginLogs, loginLogs564c52),
accounts: Object.assign(accounts, accounts),
}

export default admin