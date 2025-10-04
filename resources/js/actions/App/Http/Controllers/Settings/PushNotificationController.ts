import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\PushNotificationController::edit
 * @see app/Http/Controllers/Settings/PushNotificationController.php:16
 * @route '/settings/push-notification'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/push-notification',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::edit
 * @see app/Http/Controllers/Settings/PushNotificationController.php:16
 * @route '/settings/push-notification'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::edit
 * @see app/Http/Controllers/Settings/PushNotificationController.php:16
 * @route '/settings/push-notification'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\PushNotificationController::edit
 * @see app/Http/Controllers/Settings/PushNotificationController.php:16
 * @route '/settings/push-notification'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::index
 * @see app/Http/Controllers/Settings/PushNotificationController.php:121
 * @route '/settings/push/subscriptions'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/push/subscriptions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::index
 * @see app/Http/Controllers/Settings/PushNotificationController.php:121
 * @route '/settings/push/subscriptions'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::index
 * @see app/Http/Controllers/Settings/PushNotificationController.php:121
 * @route '/settings/push/subscriptions'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\PushNotificationController::index
 * @see app/Http/Controllers/Settings/PushNotificationController.php:121
 * @route '/settings/push/subscriptions'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::store
 * @see app/Http/Controllers/Settings/PushNotificationController.php:26
 * @route '/settings/push/subscribe'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/push/subscribe',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::store
 * @see app/Http/Controllers/Settings/PushNotificationController.php:26
 * @route '/settings/push/subscribe'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::store
 * @see app/Http/Controllers/Settings/PushNotificationController.php:26
 * @route '/settings/push/subscribe'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::destroy
 * @see app/Http/Controllers/Settings/PushNotificationController.php:78
 * @route '/settings/push/unsubscribe'
 */
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/push/unsubscribe',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::destroy
 * @see app/Http/Controllers/Settings/PushNotificationController.php:78
 * @route '/settings/push/unsubscribe'
 */
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PushNotificationController::destroy
 * @see app/Http/Controllers/Settings/PushNotificationController.php:78
 * @route '/settings/push/unsubscribe'
 */
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})
const PushNotificationController = { edit, index, store, destroy }

export default PushNotificationController