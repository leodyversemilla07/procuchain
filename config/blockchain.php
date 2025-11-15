<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blockchain Health Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for circuit breaker and health check mechanisms.
    | These settings help protect the application when blockchain node is down.
    |
    */

    'health_check' => [
        // Number of consecutive failures before opening circuit breaker
        'failure_threshold' => env('BLOCKCHAIN_FAILURE_THRESHOLD', 5),

        // Seconds to wait before attempting recovery (Issue #20 fix)
        'recovery_time' => env('BLOCKCHAIN_RECOVERY_TIME', 300), // 5 minutes

        // Cache TTL for health check results in seconds
        'health_check_ttl' => env('BLOCKCHAIN_HEALTH_CHECK_TTL', 60), // 1 minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Pagination
    |--------------------------------------------------------------------------
    |
    | Default page sizes when fetching large datasets from blockchain streams.
    | These values balance performance with memory usage.
    |
    */

    'pagination' => [
        // Status items per page when fetching from status stream
        'status_page_size' => env('BLOCKCHAIN_STATUS_PAGE_SIZE', 1000),

        // Document items per page when fetching from documents stream
        'document_page_size' => env('BLOCKCHAIN_DOCUMENT_PAGE_SIZE', 10000),

        // Event items per page when fetching from events stream
        'event_page_size' => env('BLOCKCHAIN_EVENT_PAGE_SIZE', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for blockchain operations to prevent abuse.
    |
    */

    'rate_limiting' => [
        // Maximum blockchain write operations per minute per user
        'writes_per_minute' => env('BLOCKCHAIN_WRITES_PER_MINUTE', 10),

        // Maximum blockchain read operations per minute per user
        'reads_per_minute' => env('BLOCKCHAIN_READS_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Control what information is logged for blockchain operations.
    | In production, consider reducing verbosity for sensitive data.
    |
    */

    'logging' => [
        // Log full PR numbers or just prefixes (Issue #17 fix)
        'log_full_pr_numbers' => env('BLOCKCHAIN_LOG_FULL_PR_NUMBERS', false),

        // Log user details or just IDs
        'log_user_details' => env('BLOCKCHAIN_LOG_USER_DETAILS', false),

        // Log full document metadata or summaries
        'log_document_details' => env('BLOCKCHAIN_LOG_DOCUMENT_DETAILS', false),

        // PR number prefix length when masking (e.g., "PR-2025-..." instead of full)
        'pr_number_prefix_length' => env('BLOCKCHAIN_PR_PREFIX_LENGTH', 11),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching blockchain data to improve performance.
    |
    */

    'cache' => [
        // TTL for procurement list cache (seconds)
        'procurement_list_ttl' => env('BLOCKCHAIN_CACHE_PROCUREMENT_LIST', 300), // 5 minutes

        // TTL for individual procurement details (seconds)
        'procurement_details_ttl' => env('BLOCKCHAIN_CACHE_PROCUREMENT_DETAILS', 600), // 10 minutes

        // TTL for user data cache (seconds)
        'user_cache_ttl' => env('BLOCKCHAIN_CACHE_USER_TTL', 1800), // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Limits
    |--------------------------------------------------------------------------
    |
    | Limits for document uploads to blockchain.
    |
    */

    'upload' => [
        // Maximum file size in bytes (default 2MB for blockchain transaction limits)
        'max_file_size' => env('BLOCKCHAIN_MAX_FILE_SIZE', 2097152), // 2MB

        // Allowed MIME types
        'allowed_mime_types' => ['application/pdf'],

        // Maximum documents per upload batch
        'max_batch_size' => env('BLOCKCHAIN_MAX_BATCH_SIZE', 10),
    ],

];
