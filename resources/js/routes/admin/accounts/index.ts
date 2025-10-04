import { applyUrlDefaults, queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\AdminController::locked
 * @see app/Http/Controllers/AdminController.php:726
 * @route '/admin/accounts/locked'
 */
export const locked = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: locked.url(options),
    method: 'get',
});

locked.definition = {
    methods: ['get', 'head'],
    url: '/admin/accounts/locked',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\AdminController::locked
 * @see app/Http/Controllers/AdminController.php:726
 * @route '/admin/accounts/locked'
 */
locked.url = (options?: RouteQueryOptions) => {
    return locked.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::locked
 * @see app/Http/Controllers/AdminController.php:726
 * @route '/admin/accounts/locked'
 */
locked.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: locked.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\AdminController::locked
 * @see app/Http/Controllers/AdminController.php:726
 * @route '/admin/accounts/locked'
 */
locked.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: locked.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\AdminController::unlock
 * @see app/Http/Controllers/AdminController.php:771
 * @route '/admin/accounts/{user}/unlock'
 */
export const unlock = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: unlock.url(args, options),
    method: 'post',
});

unlock.definition = {
    methods: ['post'],
    url: '/admin/accounts/{user}/unlock',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\AdminController::unlock
 * @see app/Http/Controllers/AdminController.php:771
 * @route '/admin/accounts/{user}/unlock'
 */
unlock.url = (args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return unlock.definition.url.replace('{user}', parsedArgs.user.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::unlock
 * @see app/Http/Controllers/AdminController.php:771
 * @route '/admin/accounts/{user}/unlock'
 */
unlock.post = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: unlock.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\AdminController::lock
 * @see app/Http/Controllers/AdminController.php:830
 * @route '/admin/accounts/{user}/lock'
 */
export const lock = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: lock.url(args, options),
    method: 'post',
});

lock.definition = {
    methods: ['post'],
    url: '/admin/accounts/{user}/lock',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\AdminController::lock
 * @see app/Http/Controllers/AdminController.php:830
 * @route '/admin/accounts/{user}/lock'
 */
lock.url = (args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return lock.definition.url.replace('{user}', parsedArgs.user.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::lock
 * @see app/Http/Controllers/AdminController.php:830
 * @route '/admin/accounts/{user}/lock'
 */
lock.post = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: lock.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\AdminController::resetAttempts
 * @see app/Http/Controllers/AdminController.php:885
 * @route '/admin/accounts/{user}/reset-attempts'
 */
export const resetAttempts = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: resetAttempts.url(args, options),
    method: 'post',
});

resetAttempts.definition = {
    methods: ['post'],
    url: '/admin/accounts/{user}/reset-attempts',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\AdminController::resetAttempts
 * @see app/Http/Controllers/AdminController.php:885
 * @route '/admin/accounts/{user}/reset-attempts'
 */
resetAttempts.url = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        user: typeof args.user === 'object' ? args.user.id : args.user,
    };

    return resetAttempts.definition.url.replace('{user}', parsedArgs.user.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::resetAttempts
 * @see app/Http/Controllers/AdminController.php:885
 * @route '/admin/accounts/{user}/reset-attempts'
 */
resetAttempts.post = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: resetAttempts.url(args, options),
    method: 'post',
});
const accounts = {
    locked: Object.assign(locked, locked),
    unlock: Object.assign(unlock, unlock),
    lock: Object.assign(lock, lock),
    resetAttempts: Object.assign(resetAttempts, resetAttempts),
};

export default accounts;
