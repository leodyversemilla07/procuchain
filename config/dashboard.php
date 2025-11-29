<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard Stream Query Limits
    |--------------------------------------------------------------------------
    |
    | These values control how many items are fetched from blockchain streams
    | when building dashboard data. Adjust these based on performance needs.
    |
    */

    'stream_limits' => [
        // Maximum number of status stream items to fetch for procurement data
        // Reduced from 10000 to 1000 for better performance
        'status_items' => env('DASHBOARD_STATUS_LIMIT', 1000),

        // Maximum number of document stream items to fetch
        // Reduced from 2000 to 500 for better performance
        'document_items' => env('DASHBOARD_DOCUMENT_LIMIT', 500),

        // Number of recent activity events to fetch
        'recent_activities' => env('DASHBOARD_ACTIVITIES_LIMIT', 50),

        // Offset for recent activities (negative means from end)
        'recent_activities_offset' => env('DASHBOARD_ACTIVITIES_OFFSET', -50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Display Limits
    |--------------------------------------------------------------------------
    |
    | Control how many items are displayed in various dashboard sections
    |
    */

    'display_limits' => [
        // Number of recent procurements to show on dashboard
        'recent_procurements' => 5,

        // Number of recent activities to display
        'recent_activities_display' => 8,

        // Number of priority actions to show (BAC Secretariat)
        'priority_actions' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache durations in minutes for different dashboard data types
    |
    */

    'cache_ttl' => [
        // Cache procurements data for 5 minutes
        'procurements' => env('DASHBOARD_CACHE_PROCUREMENTS', 5),

        // Cache statistics for 5 minutes
        'stats' => env('DASHBOARD_CACHE_STATS', 5),

        // Cache recent activities for 2 minutes (more frequently changing)
        'activities' => env('DASHBOARD_CACHE_ACTIVITIES', 2),

        // Cache priority actions for 2 minutes
        'priority_actions' => env('DASHBOARD_CACHE_PRIORITY', 2),

        // Cache procurement distribution for 5 minutes
        'distribution' => env('DASHBOARD_CACHE_DISTRIBUTION', 5),

        // Cache user activity analytics for 10 minutes (Admin only)
        'user_analytics' => env('DASHBOARD_CACHE_USER_ANALYTICS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Procurement Stage Definitions
    |--------------------------------------------------------------------------
    |
    | Stages considered as "completed biddings" for statistics
    |
    */

    'completed_bidding_stages' => [
        'Notice Of Award',
        'Performance Bond',
        'Contract And PO',
        'Notice To Proceed',
        'Monitoring',
        'Completed',
    ],

];
