import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::edit
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:16
 * @route '/settings/email-notification'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/email-notification',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::edit
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:16
 * @route '/settings/email-notification'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::edit
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:16
 * @route '/settings/email-notification'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::edit
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:16
 * @route '/settings/email-notification'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::update
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:28
 * @route '/settings/email-notification'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/email-notification',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::update
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:28
 * @route '/settings/email-notification'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\EmailNotificationController::update
 * @see app/Http/Controllers/Settings/EmailNotificationController.php:28
 * @route '/settings/email-notification'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})
const EmailNotificationController = { edit, update }

export default EmailNotificationController