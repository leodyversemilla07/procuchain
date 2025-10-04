import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
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
const pushNotification = {
    edit: Object.assign(edit, edit),
}

export default pushNotification