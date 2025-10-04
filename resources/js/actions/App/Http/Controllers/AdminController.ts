import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AdminController::index
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::index
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::index
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::index
 * @see app/Http/Controllers/AdminController.php:67
 * @route '/admin/dashboard'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
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
* @see \App\Http\Controllers\AdminController::storeUser
 * @see app/Http/Controllers/AdminController.php:445
 * @route '/admin/users'
 */
export const storeUser = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeUser.url(options),
    method: 'post',
})

storeUser.definition = {
    methods: ["post"],
    url: '/admin/users',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AdminController::storeUser
 * @see app/Http/Controllers/AdminController.php:445
 * @route '/admin/users'
 */
storeUser.url = (options?: RouteQueryOptions) => {
    return storeUser.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::storeUser
 * @see app/Http/Controllers/AdminController.php:445
 * @route '/admin/users'
 */
storeUser.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeUser.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AdminController::updateUser
 * @see app/Http/Controllers/AdminController.php:483
 * @route '/admin/users/{user}'
 */
export const updateUser = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateUser.url(args, options),
    method: 'put',
})

updateUser.definition = {
    methods: ["put"],
    url: '/admin/users/{user}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\AdminController::updateUser
 * @see app/Http/Controllers/AdminController.php:483
 * @route '/admin/users/{user}'
 */
updateUser.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return updateUser.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::updateUser
 * @see app/Http/Controllers/AdminController.php:483
 * @route '/admin/users/{user}'
 */
updateUser.put = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateUser.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\AdminController::destroyUser
 * @see app/Http/Controllers/AdminController.php:527
 * @route '/admin/users/{user}'
 */
export const destroyUser = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyUser.url(args, options),
    method: 'delete',
})

destroyUser.definition = {
    methods: ["delete"],
    url: '/admin/users/{user}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AdminController::destroyUser
 * @see app/Http/Controllers/AdminController.php:527
 * @route '/admin/users/{user}'
 */
destroyUser.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return destroyUser.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::destroyUser
 * @see app/Http/Controllers/AdminController.php:527
 * @route '/admin/users/{user}'
 */
destroyUser.delete = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyUser.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\AdminController::bulkDeleteUsers
 * @see app/Http/Controllers/AdminController.php:557
 * @route '/admin/users'
 */
export const bulkDeleteUsers = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: bulkDeleteUsers.url(options),
    method: 'delete',
})

bulkDeleteUsers.definition = {
    methods: ["delete"],
    url: '/admin/users',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AdminController::bulkDeleteUsers
 * @see app/Http/Controllers/AdminController.php:557
 * @route '/admin/users'
 */
bulkDeleteUsers.url = (options?: RouteQueryOptions) => {
    return bulkDeleteUsers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::bulkDeleteUsers
 * @see app/Http/Controllers/AdminController.php:557
 * @route '/admin/users'
 */
bulkDeleteUsers.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: bulkDeleteUsers.url(options),
    method: 'delete',
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

/**
* @see \App\Http\Controllers\AdminController::recentLogins
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
export const recentLogins = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recentLogins.url(options),
    method: 'get',
})

recentLogins.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs/recent',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::recentLogins
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
recentLogins.url = (options?: RouteQueryOptions) => {
    return recentLogins.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::recentLogins
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
recentLogins.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recentLogins.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::recentLogins
 * @see app/Http/Controllers/AdminController.php:651
 * @route '/admin/login-logs/recent'
 */
recentLogins.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: recentLogins.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::loginStatistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
export const loginStatistics = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: loginStatistics.url(options),
    method: 'get',
})

loginStatistics.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs/statistics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::loginStatistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
loginStatistics.url = (options?: RouteQueryOptions) => {
    return loginStatistics.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::loginStatistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
loginStatistics.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: loginStatistics.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::loginStatistics
 * @see app/Http/Controllers/AdminController.php:677
 * @route '/admin/login-logs/statistics'
 */
loginStatistics.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: loginStatistics.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::suspiciousActivities
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
export const suspiciousActivities = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suspiciousActivities.url(options),
    method: 'get',
})

suspiciousActivities.definition = {
    methods: ["get","head"],
    url: '/admin/login-logs/suspicious',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::suspiciousActivities
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
suspiciousActivities.url = (options?: RouteQueryOptions) => {
    return suspiciousActivities.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::suspiciousActivities
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
suspiciousActivities.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: suspiciousActivities.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::suspiciousActivities
 * @see app/Http/Controllers/AdminController.php:702
 * @route '/admin/login-logs/suspicious'
 */
suspiciousActivities.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: suspiciousActivities.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::lockedAccounts
 * @see app/Http/Controllers/AdminController.php:727
 * @route '/admin/accounts/locked'
 */
export const lockedAccounts = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: lockedAccounts.url(options),
    method: 'get',
})

lockedAccounts.definition = {
    methods: ["get","head"],
    url: '/admin/accounts/locked',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AdminController::lockedAccounts
 * @see app/Http/Controllers/AdminController.php:727
 * @route '/admin/accounts/locked'
 */
lockedAccounts.url = (options?: RouteQueryOptions) => {
    return lockedAccounts.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::lockedAccounts
 * @see app/Http/Controllers/AdminController.php:727
 * @route '/admin/accounts/locked'
 */
lockedAccounts.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: lockedAccounts.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AdminController::lockedAccounts
 * @see app/Http/Controllers/AdminController.php:727
 * @route '/admin/accounts/locked'
 */
lockedAccounts.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: lockedAccounts.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AdminController::unlockAccount
 * @see app/Http/Controllers/AdminController.php:772
 * @route '/admin/accounts/{user}/unlock'
 */
export const unlockAccount = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unlockAccount.url(args, options),
    method: 'post',
})

unlockAccount.definition = {
    methods: ["post"],
    url: '/admin/accounts/{user}/unlock',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AdminController::unlockAccount
 * @see app/Http/Controllers/AdminController.php:772
 * @route '/admin/accounts/{user}/unlock'
 */
unlockAccount.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return unlockAccount.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::unlockAccount
 * @see app/Http/Controllers/AdminController.php:772
 * @route '/admin/accounts/{user}/unlock'
 */
unlockAccount.post = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unlockAccount.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AdminController::lockAccount
 * @see app/Http/Controllers/AdminController.php:831
 * @route '/admin/accounts/{user}/lock'
 */
export const lockAccount = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: lockAccount.url(args, options),
    method: 'post',
})

lockAccount.definition = {
    methods: ["post"],
    url: '/admin/accounts/{user}/lock',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AdminController::lockAccount
 * @see app/Http/Controllers/AdminController.php:831
 * @route '/admin/accounts/{user}/lock'
 */
lockAccount.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return lockAccount.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::lockAccount
 * @see app/Http/Controllers/AdminController.php:831
 * @route '/admin/accounts/{user}/lock'
 */
lockAccount.post = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: lockAccount.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AdminController::resetFailedAttempts
 * @see app/Http/Controllers/AdminController.php:886
 * @route '/admin/accounts/{user}/reset-attempts'
 */
export const resetFailedAttempts = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resetFailedAttempts.url(args, options),
    method: 'post',
})

resetFailedAttempts.definition = {
    methods: ["post"],
    url: '/admin/accounts/{user}/reset-attempts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AdminController::resetFailedAttempts
 * @see app/Http/Controllers/AdminController.php:886
 * @route '/admin/accounts/{user}/reset-attempts'
 */
resetFailedAttempts.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return resetFailedAttempts.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdminController::resetFailedAttempts
 * @see app/Http/Controllers/AdminController.php:886
 * @route '/admin/accounts/{user}/reset-attempts'
 */
resetFailedAttempts.post = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resetFailedAttempts.url(args, options),
    method: 'post',
})
const AdminController = { index, users, storeUser, updateUser, destroyUser, bulkDeleteUsers, loginLogs, recentLogins, loginStatistics, suspiciousActivities, lockedAccounts, unlockAccount, lockAccount, resetFailedAttempts }

export default AdminController