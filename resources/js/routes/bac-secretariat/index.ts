import { applyUrlDefaults, queryParams, type RouteDefinition, type RouteQueryOptions } from './../../wayfinder';
import bacResolutionUpload5837f4 from './bac-resolution-upload';
import bidEvaluationUpload35c653 from './bid-evaluation-upload';
import bidOpeningUploadE50ea6 from './bid-opening-upload';
import biddingDocumentsUploadB56b2d from './bidding-documents-upload';
import completionUploadFe4ee1 from './completion-upload';
import monitoringUploadA51491 from './monitoring-upload';
import noaUploadAd5f69 from './noa-upload';
import ntpUploadC87e2c from './ntp-upload';
import performanceBondContractPoUploadB413f3 from './performance-bond-contract-po-upload';
import postQualificationUpload3ff613 from './post-qualification-upload';
import preBidConferenceUpload750eec from './pre-bid-conference-upload';
import procurement from './procurement';
import procurements from './procurements';
import procurementsList from './procurements-list';
import supplementalBidBulletinUploadBd9e34 from './supplemental-bid-bulletin-upload';
/**
 * @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
});

dashboard.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/dashboard',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\BacSecretariatController::dashboard
 * @see app/Http/Controllers/BacSecretariatController.php:59
 * @route '/bac-secretariat/dashboard'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::preProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
export const preProcurementConferenceUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: preProcurementConferenceUpload.url(args, options),
    method: 'get',
});

preProcurementConferenceUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/pre-procurement-conference-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::preProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
preProcurementConferenceUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return preProcurementConferenceUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::preProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
preProcurementConferenceUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: preProcurementConferenceUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::preProcurementConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:97
 * @route '/bac-secretariat/pre-procurement-conference-upload/{id}'
 */
preProcurementConferenceUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: preProcurementConferenceUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::preBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
export const preBidConferenceUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: preBidConferenceUpload.url(args, options),
    method: 'get',
});

preBidConferenceUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/pre-bid-conference-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::preBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
preBidConferenceUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return preBidConferenceUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::preBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
preBidConferenceUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: preBidConferenceUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::preBidConferenceUpload
 * @see app/Http/Controllers/ProcurementController.php:111
 * @route '/bac-secretariat/pre-bid-conference-upload/{id}'
 */
preBidConferenceUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: preBidConferenceUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::biddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
export const biddingDocumentsUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: biddingDocumentsUpload.url(args, options),
    method: 'get',
});

biddingDocumentsUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/bidding-documents-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::biddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
biddingDocumentsUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return biddingDocumentsUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::biddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
biddingDocumentsUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: biddingDocumentsUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::biddingDocumentsUpload
 * @see app/Http/Controllers/ProcurementController.php:125
 * @route '/bac-secretariat/bidding-documents-upload/{id}'
 */
biddingDocumentsUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: biddingDocumentsUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::supplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
export const supplementalBidBulletinUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: supplementalBidBulletinUpload.url(args, options),
    method: 'get',
});

supplementalBidBulletinUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/supplemental-bid-bulletin-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::supplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
supplementalBidBulletinUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return supplementalBidBulletinUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::supplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
supplementalBidBulletinUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: supplementalBidBulletinUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::supplementalBidBulletinUpload
 * @see app/Http/Controllers/ProcurementController.php:139
 * @route '/bac-secretariat/supplemental-bid-bulletin-upload/{id}'
 */
supplementalBidBulletinUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: supplementalBidBulletinUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::bidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
export const bidOpeningUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: bidOpeningUpload.url(args, options),
    method: 'get',
});

bidOpeningUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/bid-opening-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::bidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
bidOpeningUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return bidOpeningUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::bidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
bidOpeningUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: bidOpeningUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::bidOpeningUpload
 * @see app/Http/Controllers/ProcurementController.php:153
 * @route '/bac-secretariat/bid-opening-upload/{id}'
 */
bidOpeningUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: bidOpeningUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::bidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
export const bidEvaluationUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: bidEvaluationUpload.url(args, options),
    method: 'get',
});

bidEvaluationUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/bid-evaluation-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::bidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
bidEvaluationUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return bidEvaluationUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::bidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
bidEvaluationUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: bidEvaluationUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::bidEvaluationUpload
 * @see app/Http/Controllers/ProcurementController.php:167
 * @route '/bac-secretariat/bid-evaluation-upload/{id}'
 */
bidEvaluationUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: bidEvaluationUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::postQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
export const postQualificationUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: postQualificationUpload.url(args, options),
    method: 'get',
});

postQualificationUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/post-qualification-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::postQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
postQualificationUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return postQualificationUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::postQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
postQualificationUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: postQualificationUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::postQualificationUpload
 * @see app/Http/Controllers/ProcurementController.php:181
 * @route '/bac-secretariat/post-qualification-upload/{id}'
 */
postQualificationUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: postQualificationUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::bacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
export const bacResolutionUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: bacResolutionUpload.url(args, options),
    method: 'get',
});

bacResolutionUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/bac-resolution-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::bacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
bacResolutionUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return bacResolutionUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::bacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
bacResolutionUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: bacResolutionUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::bacResolutionUpload
 * @see app/Http/Controllers/ProcurementController.php:195
 * @route '/bac-secretariat/bac-resolution-upload/{id}'
 */
bacResolutionUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: bacResolutionUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::noaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
export const noaUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: noaUpload.url(args, options),
    method: 'get',
});

noaUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/noa-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::noaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
noaUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return noaUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::noaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
noaUpload.get = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: noaUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::noaUpload
 * @see app/Http/Controllers/ProcurementController.php:209
 * @route '/bac-secretariat/noa-upload/{id}'
 */
noaUpload.head = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: noaUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::performanceBondContractPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
export const performanceBondContractPoUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: performanceBondContractPoUpload.url(args, options),
    method: 'get',
});

performanceBondContractPoUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/performance-bond-contract-po-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::performanceBondContractPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
performanceBondContractPoUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return performanceBondContractPoUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::performanceBondContractPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
performanceBondContractPoUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: performanceBondContractPoUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::performanceBondContractPoUpload
 * @see app/Http/Controllers/ProcurementController.php:223
 * @route '/bac-secretariat/performance-bond-contract-po-upload/{id}'
 */
performanceBondContractPoUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: performanceBondContractPoUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::ntpUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
export const ntpUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: ntpUpload.url(args, options),
    method: 'get',
});

ntpUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/ntp-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::ntpUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
ntpUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return ntpUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::ntpUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
ntpUpload.get = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ntpUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::ntpUpload
 * @see app/Http/Controllers/ProcurementController.php:237
 * @route '/bac-secretariat/ntp-upload/{id}'
 */
ntpUpload.head = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ntpUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::monitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
export const monitoringUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: monitoringUpload.url(args, options),
    method: 'get',
});

monitoringUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/monitoring-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::monitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
monitoringUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return monitoringUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::monitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
monitoringUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: monitoringUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::monitoringUpload
 * @see app/Http/Controllers/ProcurementController.php:251
 * @route '/bac-secretariat/monitoring-upload/{id}'
 */
monitoringUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: monitoringUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::completionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
export const completionUpload = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: completionUpload.url(args, options),
    method: 'get',
});

completionUpload.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/completion-upload/{id}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ProcurementController::completionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
completionUpload.url = (args: { id: string | number } | [id: string | number] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args };
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        id: args.id,
    };

    return completionUpload.definition.url.replace('{id}', parsedArgs.id.toString()).replace(/\/+$/, '') + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::completionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
completionUpload.get = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: completionUpload.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ProcurementController::completionUpload
 * @see app/Http/Controllers/ProcurementController.php:265
 * @route '/bac-secretariat/completion-upload/{id}'
 */
completionUpload.head = (
    args: { id: string | number } | [id: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: completionUpload.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ProcurementController::publishPreProcurementConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:373
 * @route '/bac-secretariat/publish-pre-procurement-conference-decision'
 */
export const publishPreProcurementConferenceDecision = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreProcurementConferenceDecision.url(options),
    method: 'post',
});

publishPreProcurementConferenceDecision.definition = {
    methods: ['post'],
    url: '/bac-secretariat/publish-pre-procurement-conference-decision',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::publishPreProcurementConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:373
 * @route '/bac-secretariat/publish-pre-procurement-conference-decision'
 */
publishPreProcurementConferenceDecision.url = (options?: RouteQueryOptions) => {
    return publishPreProcurementConferenceDecision.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::publishPreProcurementConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:373
 * @route '/bac-secretariat/publish-pre-procurement-conference-decision'
 */
publishPreProcurementConferenceDecision.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreProcurementConferenceDecision.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPreProcurementConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:512
 * @route '/bac-secretariat/upload-pre-procurement-conference-documents'
 */
export const uploadPreProcurementConferenceDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreProcurementConferenceDocuments.url(options),
    method: 'post',
});

uploadPreProcurementConferenceDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-pre-procurement-conference-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPreProcurementConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:512
 * @route '/bac-secretariat/upload-pre-procurement-conference-documents'
 */
uploadPreProcurementConferenceDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPreProcurementConferenceDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPreProcurementConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:512
 * @route '/bac-secretariat/upload-pre-procurement-conference-documents'
 */
uploadPreProcurementConferenceDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreProcurementConferenceDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::publishPreBidConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:612
 * @route '/bac-secretariat/publish-pre-bid-conference-decision'
 */
export const publishPreBidConferenceDecision = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreBidConferenceDecision.url(options),
    method: 'post',
});

publishPreBidConferenceDecision.definition = {
    methods: ['post'],
    url: '/bac-secretariat/publish-pre-bid-conference-decision',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::publishPreBidConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:612
 * @route '/bac-secretariat/publish-pre-bid-conference-decision'
 */
publishPreBidConferenceDecision.url = (options?: RouteQueryOptions) => {
    return publishPreBidConferenceDecision.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::publishPreBidConferenceDecision
 * @see app/Http/Controllers/ProcurementController.php:612
 * @route '/bac-secretariat/publish-pre-bid-conference-decision'
 */
publishPreBidConferenceDecision.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishPreBidConferenceDecision.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPreBidConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:703
 * @route '/bac-secretariat/upload-pre-bid-conference-documents'
 */
export const uploadPreBidConferenceDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreBidConferenceDocuments.url(options),
    method: 'post',
});

uploadPreBidConferenceDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-pre-bid-conference-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPreBidConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:703
 * @route '/bac-secretariat/upload-pre-bid-conference-documents'
 */
uploadPreBidConferenceDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPreBidConferenceDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPreBidConferenceDocuments
 * @see app/Http/Controllers/ProcurementController.php:703
 * @route '/bac-secretariat/upload-pre-bid-conference-documents'
 */
uploadPreBidConferenceDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPreBidConferenceDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::publishSupplementalBidBulletinDecision
 * @see app/Http/Controllers/ProcurementController.php:803
 * @route '/bac-secretariat/publish-supplemental-bid-bulletin-decision'
 */
export const publishSupplementalBidBulletinDecision = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishSupplementalBidBulletinDecision.url(options),
    method: 'post',
});

publishSupplementalBidBulletinDecision.definition = {
    methods: ['post'],
    url: '/bac-secretariat/publish-supplemental-bid-bulletin-decision',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::publishSupplementalBidBulletinDecision
 * @see app/Http/Controllers/ProcurementController.php:803
 * @route '/bac-secretariat/publish-supplemental-bid-bulletin-decision'
 */
publishSupplementalBidBulletinDecision.url = (options?: RouteQueryOptions) => {
    return publishSupplementalBidBulletinDecision.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::publishSupplementalBidBulletinDecision
 * @see app/Http/Controllers/ProcurementController.php:803
 * @route '/bac-secretariat/publish-supplemental-bid-bulletin-decision'
 */
publishSupplementalBidBulletinDecision.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publishSupplementalBidBulletinDecision.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadSupplementalBidBulletinDocuments
 * @see app/Http/Controllers/ProcurementController.php:895
 * @route '/bac-secretariat/upload-supplemental-bid-bulletin-documents'
 */
export const uploadSupplementalBidBulletinDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSupplementalBidBulletinDocuments.url(options),
    method: 'post',
});

uploadSupplementalBidBulletinDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-supplemental-bid-bulletin-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadSupplementalBidBulletinDocuments
 * @see app/Http/Controllers/ProcurementController.php:895
 * @route '/bac-secretariat/upload-supplemental-bid-bulletin-documents'
 */
uploadSupplementalBidBulletinDocuments.url = (options?: RouteQueryOptions) => {
    return uploadSupplementalBidBulletinDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadSupplementalBidBulletinDocuments
 * @see app/Http/Controllers/ProcurementController.php:895
 * @route '/bac-secretariat/upload-supplemental-bid-bulletin-documents'
 */
uploadSupplementalBidBulletinDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSupplementalBidBulletinDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBiddingDocuments
 * @see app/Http/Controllers/ProcurementController.php:996
 * @route '/bac-secretariat/upload-bidding-documents'
 */
export const uploadBiddingDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBiddingDocuments.url(options),
    method: 'post',
});

uploadBiddingDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-bidding-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBiddingDocuments
 * @see app/Http/Controllers/ProcurementController.php:996
 * @route '/bac-secretariat/upload-bidding-documents'
 */
uploadBiddingDocuments.url = (options?: RouteQueryOptions) => {
    return uploadBiddingDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBiddingDocuments
 * @see app/Http/Controllers/ProcurementController.php:996
 * @route '/bac-secretariat/upload-bidding-documents'
 */
uploadBiddingDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBiddingDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBidOpeningDocuments
 * @see app/Http/Controllers/ProcurementController.php:1089
 * @route '/bac-secretariat/upload-bid-opening-documents'
 */
export const uploadBidOpeningDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidOpeningDocuments.url(options),
    method: 'post',
});

uploadBidOpeningDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-bid-opening-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBidOpeningDocuments
 * @see app/Http/Controllers/ProcurementController.php:1089
 * @route '/bac-secretariat/upload-bid-opening-documents'
 */
uploadBidOpeningDocuments.url = (options?: RouteQueryOptions) => {
    return uploadBidOpeningDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBidOpeningDocuments
 * @see app/Http/Controllers/ProcurementController.php:1089
 * @route '/bac-secretariat/upload-bid-opening-documents'
 */
uploadBidOpeningDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidOpeningDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBidEvaluationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1206
 * @route '/bac-secretariat/upload-bid-evaluation-documents'
 */
export const uploadBidEvaluationDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidEvaluationDocuments.url(options),
    method: 'post',
});

uploadBidEvaluationDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-bid-evaluation-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBidEvaluationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1206
 * @route '/bac-secretariat/upload-bid-evaluation-documents'
 */
uploadBidEvaluationDocuments.url = (options?: RouteQueryOptions) => {
    return uploadBidEvaluationDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBidEvaluationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1206
 * @route '/bac-secretariat/upload-bid-evaluation-documents'
 */
uploadBidEvaluationDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBidEvaluationDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPostQualificationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1307
 * @route '/bac-secretariat/upload-post-qualification-documents'
 */
export const uploadPostQualificationDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPostQualificationDocuments.url(options),
    method: 'post',
});

uploadPostQualificationDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-post-qualification-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPostQualificationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1307
 * @route '/bac-secretariat/upload-post-qualification-documents'
 */
uploadPostQualificationDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPostQualificationDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPostQualificationDocuments
 * @see app/Http/Controllers/ProcurementController.php:1307
 * @route '/bac-secretariat/upload-post-qualification-documents'
 */
uploadPostQualificationDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPostQualificationDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBacResolutionDocument
 * @see app/Http/Controllers/ProcurementController.php:1450
 * @route '/bac-secretariat/upload-bac-resolution-document'
 */
export const uploadBacResolutionDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBacResolutionDocument.url(options),
    method: 'post',
});

uploadBacResolutionDocument.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-bac-resolution-document',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBacResolutionDocument
 * @see app/Http/Controllers/ProcurementController.php:1450
 * @route '/bac-secretariat/upload-bac-resolution-document'
 */
uploadBacResolutionDocument.url = (options?: RouteQueryOptions) => {
    return uploadBacResolutionDocument.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadBacResolutionDocument
 * @see app/Http/Controllers/ProcurementController.php:1450
 * @route '/bac-secretariat/upload-bac-resolution-document'
 */
uploadBacResolutionDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadBacResolutionDocument.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadNoaDocument
 * @see app/Http/Controllers/ProcurementController.php:1536
 * @route '/bac-secretariat/upload-noa-document'
 */
export const uploadNoaDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNoaDocument.url(options),
    method: 'post',
});

uploadNoaDocument.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-noa-document',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadNoaDocument
 * @see app/Http/Controllers/ProcurementController.php:1536
 * @route '/bac-secretariat/upload-noa-document'
 */
uploadNoaDocument.url = (options?: RouteQueryOptions) => {
    return uploadNoaDocument.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadNoaDocument
 * @see app/Http/Controllers/ProcurementController.php:1536
 * @route '/bac-secretariat/upload-noa-document'
 */
uploadNoaDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNoaDocument.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPerformanceBondContractPoDocuments
 * @see app/Http/Controllers/ProcurementController.php:1636
 * @route '/bac-secretariat/upload-performance-bond-contract-po-documents'
 */
export const uploadPerformanceBondContractPoDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPerformanceBondContractPoDocuments.url(options),
    method: 'post',
});

uploadPerformanceBondContractPoDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-performance-bond-contract-po-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPerformanceBondContractPoDocuments
 * @see app/Http/Controllers/ProcurementController.php:1636
 * @route '/bac-secretariat/upload-performance-bond-contract-po-documents'
 */
uploadPerformanceBondContractPoDocuments.url = (options?: RouteQueryOptions) => {
    return uploadPerformanceBondContractPoDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadPerformanceBondContractPoDocuments
 * @see app/Http/Controllers/ProcurementController.php:1636
 * @route '/bac-secretariat/upload-performance-bond-contract-po-documents'
 */
uploadPerformanceBondContractPoDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPerformanceBondContractPoDocuments.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadNtpDocument
 * @see app/Http/Controllers/ProcurementController.php:1800
 * @route '/bac-secretariat/upload-ntp-document'
 */
export const uploadNtpDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNtpDocument.url(options),
    method: 'post',
});

uploadNtpDocument.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-ntp-document',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadNtpDocument
 * @see app/Http/Controllers/ProcurementController.php:1800
 * @route '/bac-secretariat/upload-ntp-document'
 */
uploadNtpDocument.url = (options?: RouteQueryOptions) => {
    return uploadNtpDocument.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadNtpDocument
 * @see app/Http/Controllers/ProcurementController.php:1800
 * @route '/bac-secretariat/upload-ntp-document'
 */
uploadNtpDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadNtpDocument.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadMonitoringDocument
 * @see app/Http/Controllers/ProcurementController.php:1898
 * @route '/bac-secretariat/upload-monitoring-document'
 */
export const uploadMonitoringDocument = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadMonitoringDocument.url(options),
    method: 'post',
});

uploadMonitoringDocument.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-monitoring-document',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadMonitoringDocument
 * @see app/Http/Controllers/ProcurementController.php:1898
 * @route '/bac-secretariat/upload-monitoring-document'
 */
uploadMonitoringDocument.url = (options?: RouteQueryOptions) => {
    return uploadMonitoringDocument.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadMonitoringDocument
 * @see app/Http/Controllers/ProcurementController.php:1898
 * @route '/bac-secretariat/upload-monitoring-document'
 */
uploadMonitoringDocument.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadMonitoringDocument.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\ProcurementController::uploadCompletionDocuments
 * @see app/Http/Controllers/ProcurementController.php:1987
 * @route '/bac-secretariat/upload-completion-documents'
 */
export const uploadCompletionDocuments = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadCompletionDocuments.url(options),
    method: 'post',
});

uploadCompletionDocuments.definition = {
    methods: ['post'],
    url: '/bac-secretariat/upload-completion-documents',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\ProcurementController::uploadCompletionDocuments
 * @see app/Http/Controllers/ProcurementController.php:1987
 * @route '/bac-secretariat/upload-completion-documents'
 */
uploadCompletionDocuments.url = (options?: RouteQueryOptions) => {
    return uploadCompletionDocuments.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ProcurementController::uploadCompletionDocuments
 * @see app/Http/Controllers/ProcurementController.php:1987
 * @route '/bac-secretariat/upload-completion-documents'
 */
uploadCompletionDocuments.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadCompletionDocuments.url(options),
    method: 'post',
});

/**
 * @see routes/file-uploads-ui-preview.php:7
 * @route '/bac-secretariat/preprocurement'
 */
export const preprocurement = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preprocurement.url(options),
    method: 'get',
});

preprocurement.definition = {
    methods: ['get', 'head'],
    url: '/bac-secretariat/preprocurement',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see routes/file-uploads-ui-preview.php:7
 * @route '/bac-secretariat/preprocurement'
 */
preprocurement.url = (options?: RouteQueryOptions) => {
    return preprocurement.definition.url + queryParams(options);
};

/**
 * @see routes/file-uploads-ui-preview.php:7
 * @route '/bac-secretariat/preprocurement'
 */
preprocurement.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preprocurement.url(options),
    method: 'get',
});
/**
 * @see routes/file-uploads-ui-preview.php:7
 * @route '/bac-secretariat/preprocurement'
 */
preprocurement.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preprocurement.url(options),
    method: 'head',
});
const bacSecretariat = {
    dashboard: Object.assign(dashboard, dashboard),
    procurementsList: Object.assign(procurementsList, procurementsList),
    procurements: Object.assign(procurements, procurements),
    procurement: Object.assign(procurement, procurement),
    preProcurementConferenceUpload: Object.assign(preProcurementConferenceUpload, preProcurementConferenceUpload),
    preBidConferenceUpload: Object.assign(preBidConferenceUpload, preBidConferenceUpload750eec),
    biddingDocumentsUpload: Object.assign(biddingDocumentsUpload, biddingDocumentsUploadB56b2d),
    supplementalBidBulletinUpload: Object.assign(supplementalBidBulletinUpload, supplementalBidBulletinUploadBd9e34),
    bidOpeningUpload: Object.assign(bidOpeningUpload, bidOpeningUploadE50ea6),
    bidEvaluationUpload: Object.assign(bidEvaluationUpload, bidEvaluationUpload35c653),
    postQualificationUpload: Object.assign(postQualificationUpload, postQualificationUpload3ff613),
    bacResolutionUpload: Object.assign(bacResolutionUpload, bacResolutionUpload5837f4),
    noaUpload: Object.assign(noaUpload, noaUploadAd5f69),
    performanceBondContractPoUpload: Object.assign(performanceBondContractPoUpload, performanceBondContractPoUploadB413f3),
    ntpUpload: Object.assign(ntpUpload, ntpUploadC87e2c),
    monitoringUpload: Object.assign(monitoringUpload, monitoringUploadA51491),
    completionUpload: Object.assign(completionUpload, completionUploadFe4ee1),
    publishPreProcurementConferenceDecision: Object.assign(publishPreProcurementConferenceDecision, publishPreProcurementConferenceDecision),
    uploadPreProcurementConferenceDocuments: Object.assign(uploadPreProcurementConferenceDocuments, uploadPreProcurementConferenceDocuments),
    publishPreBidConferenceDecision: Object.assign(publishPreBidConferenceDecision, publishPreBidConferenceDecision),
    uploadPreBidConferenceDocuments: Object.assign(uploadPreBidConferenceDocuments, uploadPreBidConferenceDocuments),
    publishSupplementalBidBulletinDecision: Object.assign(publishSupplementalBidBulletinDecision, publishSupplementalBidBulletinDecision),
    uploadSupplementalBidBulletinDocuments: Object.assign(uploadSupplementalBidBulletinDocuments, uploadSupplementalBidBulletinDocuments),
    uploadBiddingDocuments: Object.assign(uploadBiddingDocuments, uploadBiddingDocuments),
    uploadBidOpeningDocuments: Object.assign(uploadBidOpeningDocuments, uploadBidOpeningDocuments),
    uploadBidEvaluationDocuments: Object.assign(uploadBidEvaluationDocuments, uploadBidEvaluationDocuments),
    uploadPostQualificationDocuments: Object.assign(uploadPostQualificationDocuments, uploadPostQualificationDocuments),
    uploadBacResolutionDocument: Object.assign(uploadBacResolutionDocument, uploadBacResolutionDocument),
    uploadNoaDocument: Object.assign(uploadNoaDocument, uploadNoaDocument),
    uploadPerformanceBondContractPoDocuments: Object.assign(uploadPerformanceBondContractPoDocuments, uploadPerformanceBondContractPoDocuments),
    uploadNtpDocument: Object.assign(uploadNtpDocument, uploadNtpDocument),
    uploadMonitoringDocument: Object.assign(uploadMonitoringDocument, uploadMonitoringDocument),
    uploadCompletionDocuments: Object.assign(uploadCompletionDocuments, uploadCompletionDocuments),
    preprocurement: Object.assign(preprocurement, preprocurement),
};

export default bacSecretariat;
