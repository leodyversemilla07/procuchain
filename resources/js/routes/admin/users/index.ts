import { applyUrlDefaults, queryParams, type RouteDefinition, type RouteQueryOptions } from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\AdminController::store
 * @see app/Http/Controllers/AdminController.php:444
 * @route '/admin/users'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/users',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\AdminController::store
 * @see app/Http/Controllers/AdminController.php:444
 * @route '/admin/users'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::store
 * @see app/Http/Controllers/AdminController.php:444
 * @route '/admin/users'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\AdminController::update
 * @see app/Http/Controllers/AdminController.php:482
 * @route '/admin/users/{user}'
 */
export const update = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

update.definition = {
    methods: ['put'],
    url: '/admin/users/{user}',
} satisfies RouteDefinition<['put']>;

/**
 * @see \App\Http\Controllers\AdminController::update
 * @see app/Http/Controllers/AdminController.php:482
 * @route '/admin/users/{user}'
 */
update.url = (args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url.replace('{user}', parsedArgs.user.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::update
 * @see app/Http/Controllers/AdminController.php:482
 * @route '/admin/users/{user}'
 */
update.put = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
});

/**
 * @see \App\Http\Controllers\AdminController::destroy
 * @see app/Http/Controllers/AdminController.php:526
 * @route '/admin/users/{user}'
 */
export const destroy = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/admin/users/{user}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\AdminController::destroy
 * @see app/Http/Controllers/AdminController.php:526
 * @route '/admin/users/{user}'
 */
destroy.url = (args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url.replace('{user}', parsedArgs.user.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::destroy
 * @see app/Http/Controllers/AdminController.php:526
 * @route '/admin/users/{user}'
 */
destroy.delete = (
    args: { user: number | { id: number } } | [user: number | { id: number }] | number | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\AdminController::bulkDelete
 * @see app/Http/Controllers/AdminController.php:556
 * @route '/admin/users'
 */
export const bulkDelete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: bulkDelete.url(options),
    method: 'delete',
});

bulkDelete.definition = {
    methods: ['delete'],
    url: '/admin/users',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\AdminController::bulkDelete
 * @see app/Http/Controllers/AdminController.php:556
 * @route '/admin/users'
 */
bulkDelete.url = (options?: RouteQueryOptions) => {
    return bulkDelete.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\AdminController::bulkDelete
 * @see app/Http/Controllers/AdminController.php:556
 * @route '/admin/users'
 */
bulkDelete.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: bulkDelete.url(options),
    method: 'delete',
});
const users = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    bulkDelete: Object.assign(bulkDelete, bulkDelete),
};

export default users;
