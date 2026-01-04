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
    | Batch Publishing (PublishMulti)
    |--------------------------------------------------------------------------
    |
    | Configuration for atomic batch publishing using MultiChain's publishmulti.
    | Publishes multiple items to one or more streams in a single transaction.
    |
    | Benefits:
    | - 60-70% latency reduction vs sequential publishes
    | - Atomic operation (all items succeed or fail together)
    | - Single blockchain transaction for multiple streams
    | - Synchronous with immediate confirmation
    |
    | Ideal for government systems requiring rapid feedback without queues.
    |
    */

    'batch_publishing' => [
        // Enable batch publishing (uses publishmulti API)
        'enabled' => env('BLOCKCHAIN_BATCH_ENABLED', true),

        // Max items per batch (limited by max-std-op-returns-count blockchain parameter)
        'max_items_per_batch' => env('BLOCKCHAIN_BATCH_MAX_ITEMS', 32),

        // Log performance metrics
        'log_performance' => env('BLOCKCHAIN_BATCH_LOG_PERFORMANCE', true),
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
        // Set to 0 to disable caching (not recommended for production)
        // Cache TTL for procurement list (increased from 2 to 5 minutes to reduce blockchain load)
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
    | IMPORTANT: On-Chain Storage Considerations
    | -------------------------------------------
    | Files are stored directly on the MultiChain blockchain as hex-encoded data
    | in the 'file.data' stream. This provides:
    | - Immutable storage with automatic replication across all nodes
    | - Zero external storage costs (no S3/cloud storage needed)
    | - Heroku-compatible persistent storage
    |
    | However, on-chain storage has constraints:
    | - Each transaction has size limits based on MultiChain configuration
    | - Large files increase blockchain size and sync times
    | - Recommended max: 2MB for optimal performance
    |
    */

    'upload' => [
        // Maximum file size in bytes (default 2MB for blockchain transaction limits)
        // This is the recommended limit for optimal blockchain performance
        // Higher values possible but may impact sync times and transaction costs
        'max_file_size' => env('BLOCKCHAIN_MAX_FILE_SIZE', 2097152), // 2MB

        // Absolute maximum file size (50MB) - enforced by BlockchainStorageService
        // Files larger than this will be rejected regardless of config
        'absolute_max_file_size' => 52428800, // 50MB

        // Allowed MIME types for document uploads
        'allowed_mime_types' => ['application/pdf'],

        // Maximum documents per upload batch
        'max_batch_size' => env('BLOCKCHAIN_MAX_BATCH_SIZE', 10),

        // Chunking configuration for large files
        // When enabled, files larger than chunk_threshold will be split into
        // multiple blockchain transactions and stored in the file.chunks stream
        'chunking' => [
            // Enable chunking for large files (REQUIRED for files > 1.5MB with 4MB tx limit)
            'enabled' => env('BLOCKCHAIN_CHUNKING_ENABLED', true),

            // Files larger than this will be chunked (accounting for hex encoding = 2x size)
            // With 4MB tx limit, raw file threshold should be ~1.5MB
            'chunk_threshold' => env('BLOCKCHAIN_CHUNK_THRESHOLD', 1572864), // 1.5MB

            // Size of each chunk when splitting large files (before hex encoding)
            // 1MB raw = 2MB hex, safely under 4MB limit with metadata overhead
            'chunk_size' => env('BLOCKCHAIN_CHUNK_SIZE', 1048576), // 1MB per chunk

            // Stream for storing file chunks
            'chunk_stream' => 'file.chunks',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Streams
    |--------------------------------------------------------------------------
    |
    | MultiChain stream names used for file storage.
    | These should match the streams created during blockchain setup.
    |
    */

    'streams' => [
        // Raw file binary data (hex-encoded)
        'file_data' => 'file.data',

        // File metadata and integrity tracking with SHA-256 hashes
        'file_metadata' => 'file.metadata',

        // Large file chunks (when chunking is enabled)
        'file_chunks' => 'file.chunks',
    ],

];
