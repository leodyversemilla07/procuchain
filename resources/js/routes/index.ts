import { queryParams, type RouteDefinition, type RouteQueryOptions } from './../wayfinder';
/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
 * @route '/login'
 */
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
});

login.definition = {
    methods: ['get', 'head'],
    url: '/login',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
 * @route '/login'
 */
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
 * @route '/login'
 */
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:21
 * @route '/login'
 */
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:59
 * @route '/logout'
 */
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
});

logout.definition = {
    methods: ['post'],
    url: '/logout',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:59
 * @route '/logout'
 */
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
 * @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:59
 * @route '/logout'
 */
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
});

/**
 * @see routes/web.php:15
 * @route '/'
 */
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
});

home.definition = {
    methods: ['get', 'head'],
    url: '/',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/web.php:15
 * @route '/'
 */
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options);
};

/**
 * @see routes/web.php:15
 * @route '/'
 */
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
});
/**
 * @see routes/web.php:15
 * @route '/'
 */
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
});

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/about'
 */
export const about = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: about.url(options),
    method: 'get',
});

about.definition = {
    methods: ['get', 'head'],
    url: '/about',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/about'
 */
about.url = (options?: RouteQueryOptions) => {
    return about.definition.url + queryParams(options);
};

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/about'
 */
about.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: about.url(options),
    method: 'get',
});
/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/about'
 */
about.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: about.url(options),
    method: 'head',
});

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/team'
 */
export const team = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: team.url(options),
    method: 'get',
});

team.definition = {
    methods: ['get', 'head'],
    url: '/team',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/team'
 */
team.url = (options?: RouteQueryOptions) => {
    return team.definition.url + queryParams(options);
};

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/team'
 */
team.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: team.url(options),
    method: 'get',
});
/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/team'
 */
team.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: team.url(options),
    method: 'head',
});

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/contact'
 */
export const contact = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: contact.url(options),
    method: 'get',
});

contact.definition = {
    methods: ['get', 'head'],
    url: '/contact',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/contact'
 */
contact.url = (options?: RouteQueryOptions) => {
    return contact.definition.url + queryParams(options);
};

/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/contact'
 */
contact.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: contact.url(options),
    method: 'get',
});
/**
 * @see \Inertia\Controller::__invoke
 * @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
 * @route '/contact'
 */
contact.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: contact.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SearchController::search
 * @see app/Http/Controllers/SearchController.php:71
 * @route '/search'
 */
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
});

search.definition = {
    methods: ['get', 'head'],
    url: '/search',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SearchController::search
 * @see app/Http/Controllers/SearchController.php:71
 * @route '/search'
 */
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\SearchController::search
 * @see app/Http/Controllers/SearchController.php:71
 * @route '/search'
 */
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\SearchController::search
 * @see app/Http/Controllers/SearchController.php:71
 * @route '/search'
 */
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::publishProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:308
 * @route '/bac-secretariat/publish-procurement-initiation'
 */
export const publishProcurementInitiation = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishProcurementInitiation.url(options),
    method: 'post',
});

publishProcurementInitiation.definition = {
    methods: ['post'],
    url: '/bac-secretariat/publish-procurement-initiation',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::publishProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:308
 * @route '/bac-secretariat/publish-procurement-initiation'
 */
publishProcurementInitiation.url = (options?: RouteQueryOptions) => {
    return publishProcurementInitiation.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::publishProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:308
 * @route '/bac-secretariat/publish-procurement-initiation'
 */
publishProcurementInitiation.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishProcurementInitiation.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\NotificationController::notifications
 * @see app/Http/Controllers/NotificationController.php:56
 * @route '/notifications'
 */
export const notifications = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: notifications.url(options),
    method: 'get',
});

notifications.definition = {
    methods: ['get', 'head'],
    url: '/notifications',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\NotificationController::notifications
 * @see app/Http/Controllers/NotificationController.php:56
 * @route '/notifications'
 */
notifications.url = (options?: RouteQueryOptions) => {
    return notifications.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\NotificationController::notifications
 * @see app/Http/Controllers/NotificationController.php:56
 * @route '/notifications'
 */
notifications.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: notifications.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\NotificationController::notifications
 * @see app/Http/Controllers/NotificationController.php:56
 * @route '/notifications'
 */
notifications.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: notifications.url(options),
    method: 'head',
});
