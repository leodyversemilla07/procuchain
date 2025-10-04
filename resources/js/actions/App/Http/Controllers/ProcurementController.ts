import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ProcurementController::showProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
export const showProcurementInitiation = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showProcurementInitiation.url(options),
    method: 'get',
})

showProcurementInitiation.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/procurement/procurement-initiation',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
showProcurementInitiation.url = (options?: RouteQueryOptions) => {
    return showProcurementInitiation.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
showProcurementInitiation.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showProcurementInitiation.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:92
 * @route '/bac-secretariat/procurement/procurement-initiation'
 */
showProcurementInitiation.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showProcurementInitiation.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showPreProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
export const showPreProcurementConferenceUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPreProcurementConferenceUpload.url(args, options),
    method: 'get',
})

showPreProcurementConferenceUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/pre-procurement-conference-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showPreProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
showPreProcurementConferenceUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showPreProcurementConferenceUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showPreProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
showPreProcurementConferenceUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPreProcurementConferenceUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showPreProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
showPreProcurementConferenceUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showPreProcurementConferenceUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showPreBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
export const showPreBidConferenceUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPreBidConferenceUpload.url(args, options),
    method: 'get',
})

showPreBidConferenceUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/pre-bid-conference-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showPreBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
showPreBidConferenceUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showPreBidConferenceUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showPreBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
showPreBidConferenceUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPreBidConferenceUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showPreBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
showPreBidConferenceUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showPreBidConferenceUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showBiddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
export const showBiddingDocumentsUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBiddingDocumentsUpload.url(args, options),
    method: 'get',
})

showBiddingDocumentsUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/bidding-documents-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showBiddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
showBiddingDocumentsUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showBiddingDocumentsUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showBiddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
showBiddingDocumentsUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBiddingDocumentsUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showBiddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
showBiddingDocumentsUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showBiddingDocumentsUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showSupplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
export const showSupplementalBidBulletinUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showSupplementalBidBulletinUpload.url(args, options),
    method: 'get',
})

showSupplementalBidBulletinUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/supplemental-bid-bulletin-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showSupplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
showSupplementalBidBulletinUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showSupplementalBidBulletinUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showSupplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
showSupplementalBidBulletinUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showSupplementalBidBulletinUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showSupplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
showSupplementalBidBulletinUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showSupplementalBidBulletinUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showBidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
export const showBidOpeningUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBidOpeningUpload.url(args, options),
    method: 'get',
})

showBidOpeningUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/bid-opening-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showBidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
showBidOpeningUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showBidOpeningUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showBidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
showBidOpeningUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBidOpeningUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showBidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
showBidOpeningUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showBidOpeningUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showBidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
export const showBidEvaluationUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBidEvaluationUpload.url(args, options),
    method: 'get',
})

showBidEvaluationUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/bid-evaluation-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showBidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
showBidEvaluationUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showBidEvaluationUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showBidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
showBidEvaluationUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBidEvaluationUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showBidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
showBidEvaluationUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showBidEvaluationUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showPostQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
export const showPostQualificationUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPostQualificationUpload.url(args, options),
    method: 'get',
})

showPostQualificationUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/post-qualification-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showPostQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
showPostQualificationUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showPostQualificationUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showPostQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
showPostQualificationUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPostQualificationUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showPostQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
showPostQualificationUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showPostQualificationUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showBacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
export const showBacResolutionUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBacResolutionUpload.url(args, options),
    method: 'get',
})

showBacResolutionUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/bac-resolution-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showBacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
showBacResolutionUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showBacResolutionUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showBacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
showBacResolutionUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showBacResolutionUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showBacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
showBacResolutionUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showBacResolutionUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showNoaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
export const showNoaUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showNoaUpload.url(args, options),
    method: 'get',
})

showNoaUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/noa-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showNoaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
showNoaUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showNoaUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showNoaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
showNoaUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showNoaUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showNoaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
showNoaUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showNoaUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showPerformanceBondContactAndPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
export const showPerformanceBondContactAndPoUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPerformanceBondContactAndPoUpload.url(args, options),
    method: 'get',
})

showPerformanceBondContactAndPoUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/performance-bond-contract-po-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showPerformanceBondContactAndPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
showPerformanceBondContactAndPoUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showPerformanceBondContactAndPoUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showPerformanceBondContactAndPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
showPerformanceBondContactAndPoUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showPerformanceBondContactAndPoUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showPerformanceBondContactAndPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
showPerformanceBondContactAndPoUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showPerformanceBondContactAndPoUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showNTPUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
export const showNTPUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showNTPUpload.url(args, options),
    method: 'get',
})

showNTPUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/ntp-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showNTPUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
showNTPUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showNTPUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showNTPUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
showNTPUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showNTPUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showNTPUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
showNTPUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showNTPUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showMonitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
export const showMonitoringUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showMonitoringUpload.url(args, options),
    method: 'get',
})

showMonitoringUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/monitoring-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showMonitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
showMonitoringUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showMonitoringUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showMonitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
showMonitoringUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showMonitoringUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showMonitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
showMonitoringUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showMonitoringUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::showCompletionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
export const showCompletionUpload = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showCompletionUpload.url(args, options),
    method: 'get',
})

showCompletionUpload.definition = {
    methods: ["get","head"],
    url: '/bac-secretariat/completion-upload/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProcurementController::showCompletionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
showCompletionUpload.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return showCompletionUpload.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::showCompletionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
showCompletionUpload.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showCompletionUpload.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProcurementController::showCompletionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
showCompletionUpload.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showCompletionUpload.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProcurementController::publishProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:308
 * @route '/bac-secretariat/publish-procurement-initiation'
 */
export const publishProcurementInitiation = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishProcurementInitiation.url(options),
    method: 'post',
})

publishProcurementInitiation.definition = {
    methods: ["post"],
    url: '/bac-secretariat/publish-procurement-initiation',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::publishProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:308
 * @route '/bac-secretariat/publish-procurement-initiation'
 */
publishProcurementInitiation.url = (options?: RouteQueryOptions) => {
    return publishProcurementInitiation.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::publishProcurementInitiation
 * @see app/Http/Controllers/ProcurementController.php:308
 * @route '/bac-secretariat/publish-procurement-initiation'
 */
publishProcurementInitiation.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishProcurementInitiation.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::publishPreProcurementConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:373
 * @route '/bac-secretariat/publish-pre-procurement-conference-decision'
 */
export const publishPreProcurementConferenceDecision = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreProcurementConferenceDecision.url(options),
    method: 'post',
})

publishPreProcurementConferenceDecision.definition = {
    methods: ["post"],
    url: '/bac-secretariat/publish-pre-procurement-conference-decision',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::publishPreProcurementConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:373
 * @route '/bac-secretariat/publish-pre-procurement-conference-decision'
 */
publishPreProcurementConferenceDecision.url = (options?: RouteQueryOptions) => {
    return publishPreProcurementConferenceDecision.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::publishPreProcurementConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:373
 * @route '/bac-secretariat/publish-pre-procurement-conference-decision'
 */
publishPreProcurementConferenceDecision.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreProcurementConferenceDecision.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadPreProcurementConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:512
 * @route '/bac-secretariat/upload-pre-procurement-conference-documents'
 */
export const uploadPreProcurementConferenceDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreProcurementConferenceDocuments.url(options),
    method: 'post',
})

uploadPreProcurementConferenceDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-pre-procurement-conference-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadPreProcurementConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:512
 * @route '/bac-secretariat/upload-pre-procurement-conference-documents'
 */
uploadPreProcurementConferenceDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPreProcurementConferenceDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadPreProcurementConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:512
 * @route '/bac-secretariat/upload-pre-procurement-conference-documents'
 */
uploadPreProcurementConferenceDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreProcurementConferenceDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::publishPreBidConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:612
 * @route '/bac-secretariat/publish-pre-bid-conference-decision'
 */
export const publishPreBidConferenceDecision = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreBidConferenceDecision.url(options),
    method: 'post',
})

publishPreBidConferenceDecision.definition = {
    methods: ["post"],
    url: '/bac-secretariat/publish-pre-bid-conference-decision',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::publishPreBidConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:612
 * @route '/bac-secretariat/publish-pre-bid-conference-decision'
 */
publishPreBidConferenceDecision.url = (options?: RouteQueryOptions) => {
    return publishPreBidConferenceDecision.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::publishPreBidConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:612
 * @route '/bac-secretariat/publish-pre-bid-conference-decision'
 */
publishPreBidConferenceDecision.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreBidConferenceDecision.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadPreBidConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:703
 * @route '/bac-secretariat/upload-pre-bid-conference-documents'
 */
export const uploadPreBidConferenceDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreBidConferenceDocuments.url(options),
    method: 'post',
})

uploadPreBidConferenceDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-pre-bid-conference-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadPreBidConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:703
 * @route '/bac-secretariat/upload-pre-bid-conference-documents'
 */
uploadPreBidConferenceDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPreBidConferenceDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadPreBidConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:703
 * @route '/bac-secretariat/upload-pre-bid-conference-documents'
 */
uploadPreBidConferenceDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreBidConferenceDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::publishSupplementalBidBulletinDecision
 * @see app/Http/Controllers/ProcurementController.php:803
 * @route '/bac-secretariat/publish-supplemental-bid-bulletin-decision'
 */
export const publishSupplementalBidBulletinDecision = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishSupplementalBidBulletinDecision.url(options),
    method: 'post',
})

publishSupplementalBidBulletinDecision.definition = {
    methods: ["post"],
    url: '/bac-secretariat/publish-supplemental-bid-bulletin-decision',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::publishSupplementalBidBulletinDecision
 * @see app/Http/Controllers/ProcurementController.php:803
 * @route '/bac-secretariat/publish-supplemental-bid-bulletin-decision'
 */
publishSupplementalBidBulletinDecision.url = (options?: RouteQueryOptions) => {
    return publishSupplementalBidBulletinDecision.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::publishSupplementalBidBulletinDecision
 * @see app/Http/Controllers/ProcurementController.php:803
 * @route '/bac-secretariat/publish-supplemental-bid-bulletin-decision'
 */
publishSupplementalBidBulletinDecision.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishSupplementalBidBulletinDecision.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadSupplementalBidBulletinDocuments
 * @see app/Http/Controllers/ProcurementController.php:895
 * @route '/bac-secretariat/upload-supplemental-bid-bulletin-documents'
 */
export const uploadSupplementalBidBulletinDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSupplementalBidBulletinDocuments.url(options),
    method: 'post',
})

uploadSupplementalBidBulletinDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-supplemental-bid-bulletin-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadSupplementalBidBulletinDocuments
 * @see app/Http/Controllers/ProcurementController.php:895
 * @route '/bac-secretariat/upload-supplemental-bid-bulletin-documents'
 */
uploadSupplementalBidBulletinDocuments.url = (options?: RouteQueryOptions) => {
    return uploadSupplementalBidBulletinDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadSupplementalBidBulletinDocuments
 * @see app/Http/Controllers/ProcurementController.php:895
 * @route '/bac-secretariat/upload-supplemental-bid-bulletin-documents'
 */
uploadSupplementalBidBulletinDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSupplementalBidBulletinDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadBiddingDocuments
 * @see app/Http/Controllers/ProcurementController.php:996
 * @route '/bac-secretariat/upload-bidding-documents'
 */
export const uploadBiddingDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBiddingDocuments.url(options),
    method: 'post',
})

uploadBiddingDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-bidding-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadBiddingDocuments
 * @see app/Http/Controllers/ProcurementController.php:996
 * @route '/bac-secretariat/upload-bidding-documents'
 */
uploadBiddingDocuments.url = (options?: RouteQueryOptions) => {
    return uploadBiddingDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadBiddingDocuments
 * @see app/Http/Controllers/ProcurementController.php:996
 * @route '/bac-secretariat/upload-bidding-documents'
 */
uploadBiddingDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBiddingDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadBidOpeningDocuments
 * @see app/Http/Controllers/ProcurementController.php:1089
 * @route '/bac-secretariat/upload-bid-opening-documents'
 */
export const uploadBidOpeningDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidOpeningDocuments.url(options),
    method: 'post',
})

uploadBidOpeningDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-bid-opening-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadBidOpeningDocuments
 * @see app/Http/Controllers/ProcurementController.php:1089
 * @route '/bac-secretariat/upload-bid-opening-documents'
 */
uploadBidOpeningDocuments.url = (options?: RouteQueryOptions) => {
    return uploadBidOpeningDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadBidOpeningDocuments
 * @see app/Http/Controllers/ProcurementController.php:1089
 * @route '/bac-secretariat/upload-bid-opening-documents'
 */
uploadBidOpeningDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidOpeningDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadBidEvaluationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1206
 * @route '/bac-secretariat/upload-bid-evaluation-documents'
 */
export const uploadBidEvaluationDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidEvaluationDocuments.url(options),
    method: 'post',
})

uploadBidEvaluationDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-bid-evaluation-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadBidEvaluationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1206
 * @route '/bac-secretariat/upload-bid-evaluation-documents'
 */
uploadBidEvaluationDocuments.url = (options?: RouteQueryOptions) => {
    return uploadBidEvaluationDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadBidEvaluationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1206
 * @route '/bac-secretariat/upload-bid-evaluation-documents'
 */
uploadBidEvaluationDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidEvaluationDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadPostQualificationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1307
 * @route '/bac-secretariat/upload-post-qualification-documents'
 */
export const uploadPostQualificationDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPostQualificationDocuments.url(options),
    method: 'post',
})

uploadPostQualificationDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-post-qualification-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadPostQualificationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1307
 * @route '/bac-secretariat/upload-post-qualification-documents'
 */
uploadPostQualificationDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPostQualificationDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadPostQualificationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1307
 * @route '/bac-secretariat/upload-post-qualification-documents'
 */
uploadPostQualificationDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPostQualificationDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadBacResolutionDocument
 * @see app/Http/Controllers/ProcurementController.php:1450
 * @route '/bac-secretariat/upload-bac-resolution-document'
 */
export const uploadBacResolutionDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBacResolutionDocument.url(options),
    method: 'post',
})

uploadBacResolutionDocument.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-bac-resolution-document',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadBacResolutionDocument
 * @see app/Http/Controllers/ProcurementController.php:1450
 * @route '/bac-secretariat/upload-bac-resolution-document'
 */
uploadBacResolutionDocument.url = (options?: RouteQueryOptions) => {
    return uploadBacResolutionDocument.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadBacResolutionDocument
 * @see app/Http/Controllers/ProcurementController.php:1450
 * @route '/bac-secretariat/upload-bac-resolution-document'
 */
uploadBacResolutionDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBacResolutionDocument.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadNoaDocument
 * @see app/Http/Controllers/ProcurementController.php:1536
 * @route '/bac-secretariat/upload-noa-document'
 */
export const uploadNoaDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNoaDocument.url(options),
    method: 'post',
})

uploadNoaDocument.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-noa-document',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadNoaDocument
 * @see app/Http/Controllers/ProcurementController.php:1536
 * @route '/bac-secretariat/upload-noa-document'
 */
uploadNoaDocument.url = (options?: RouteQueryOptions) => {
    return uploadNoaDocument.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadNoaDocument
 * @see app/Http/Controllers/ProcurementController.php:1536
 * @route '/bac-secretariat/upload-noa-document'
 */
uploadNoaDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNoaDocument.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadPerformanceBondContractAndPoDocuments
 * @see app/Http/Controllers/ProcurementController.php:1636
 * @route '/bac-secretariat/upload-performance-bond-contract-po-documents'
 */
export const uploadPerformanceBondContractAndPoDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPerformanceBondContractAndPoDocuments.url(options),
    method: 'post',
})

uploadPerformanceBondContractAndPoDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-performance-bond-contract-po-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadPerformanceBondContractAndPoDocuments
 * @see app/Http/Controllers/ProcurementController.php:1636
 * @route '/bac-secretariat/upload-performance-bond-contract-po-documents'
 */
uploadPerformanceBondContractAndPoDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPerformanceBondContractAndPoDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadPerformanceBondContractAndPoDocuments
 * @see app/Http/Controllers/ProcurementController.php:1636
 * @route '/bac-secretariat/upload-performance-bond-contract-po-documents'
 */
uploadPerformanceBondContractAndPoDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPerformanceBondContractAndPoDocuments.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadNTPDocument
 * @see app/Http/Controllers/ProcurementController.php:1800
 * @route '/bac-secretariat/upload-ntp-document'
 */
export const uploadNTPDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNTPDocument.url(options),
    method: 'post',
})

uploadNTPDocument.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-ntp-document',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadNTPDocument
 * @see app/Http/Controllers/ProcurementController.php:1800
 * @route '/bac-secretariat/upload-ntp-document'
 */
uploadNTPDocument.url = (options?: RouteQueryOptions) => {
    return uploadNTPDocument.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadNTPDocument
 * @see app/Http/Controllers/ProcurementController.php:1800
 * @route '/bac-secretariat/upload-ntp-document'
 */
uploadNTPDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNTPDocument.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadMonitoringDocument
 * @see app/Http/Controllers/ProcurementController.php:1898
 * @route '/bac-secretariat/upload-monitoring-document'
 */
export const uploadMonitoringDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadMonitoringDocument.url(options),
    method: 'post',
})

uploadMonitoringDocument.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-monitoring-document',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadMonitoringDocument
 * @see app/Http/Controllers/ProcurementController.php:1898
 * @route '/bac-secretariat/upload-monitoring-document'
 */
uploadMonitoringDocument.url = (options?: RouteQueryOptions) => {
    return uploadMonitoringDocument.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadMonitoringDocument
 * @see app/Http/Controllers/ProcurementController.php:1898
 * @route '/bac-secretariat/upload-monitoring-document'
 */
uploadMonitoringDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadMonitoringDocument.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProcurementController::uploadCompletionDocuments
 * @see app/Http/Controllers/ProcurementController.php:1987
 * @route '/bac-secretariat/upload-completion-documents'
 */
export const uploadCompletionDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadCompletionDocuments.url(options),
    method: 'post',
})

uploadCompletionDocuments.definition = {
    methods: ["post"],
    url: '/bac-secretariat/upload-completion-documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProcurementController::uploadCompletionDocuments
 * @see app/Http/Controllers/ProcurementController.php:1987
 * @route '/bac-secretariat/upload-completion-documents'
 */
uploadCompletionDocuments.url = (options?: RouteQueryOptions) => {
    return uploadCompletionDocuments.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProcurementController::uploadCompletionDocuments
 * @see app/Http/Controllers/ProcurementController.php:1987
 * @route '/bac-secretariat/upload-completion-documents'
 */
uploadCompletionDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadCompletionDocuments.url(options),
    method: 'post',
})
const ProcurementController = { showProcurementInitiation, showPreProcurementConferenceUpload, showPreBidConferenceUpload, showBiddingDocumentsUpload, showSupplementalBidBulletinUpload, showBidOpeningUpload, showBidEvaluationUpload, showPostQualificationUpload, showBacResolutionUpload, showNoaUpload, showPerformanceBondContactAndPoUpload, showNTPUpload, showMonitoringUpload, showCompletionUpload, publishProcurementInitiation, publishPreProcurementConferenceDecision, uploadPreProcurementConferenceDocuments, publishPreBidConferenceDecision, uploadPreBidConferenceDocuments, publishSupplementalBidBulletinDecision, uploadSupplementalBidBulletinDocuments, uploadBiddingDocuments, uploadBidOpeningDocuments, uploadBidEvaluationDocuments, uploadPostQualificationDocuments, uploadBacResolutionDocument, uploadNoaDocument, uploadPerformanceBondContractAndPoDocuments, uploadNTPDocument, uploadMonitoringDocument, uploadCompletionDocuments }

export default ProcurementController