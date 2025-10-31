# ProcuChain Monitoring & Observability Guide

This guide covers all monitoring, logging, and observability features in ProcuChain.

## Table of Contents

1. [Error Tracking with Sentry](#error-tracking-with-sentry)
2. [Application Logging](#application-logging)
3. [Performance Monitoring](#performance-monitoring)
4. [Audit Trails](#audit-trails)
5. [Health Checks](#health-checks)
6. [Alerts and Notifications](#alerts-and-notifications)
7. [Troubleshooting](#troubleshooting)

---

## Error Tracking with Sentry

### Overview
ProcuChain uses Sentry for real-time error tracking, performance monitoring, and release tracking.

### Configuration

The application is already configured with Sentry. Environment variables in `.env`:

```bash
SENTRY_LARAVEL_DSN=https://3ad9bddf9f1a4fa125d3cdde6fb4c82f@o4510284781060097.ingest.us.sentry.io/4510284789383168
SENTRY_TRACES_SAMPLE_RATE=1.0  # 100% = monitor all transactions
SENTRY_ENABLE_LOGS=false        # Set to true to capture Laravel logs
SENTRY_SEND_DEFAULT_PII=false   # Set to true to include user data
```

### Testing Sentry Integration

```bash
# Send a test event to Sentry
php artisan sentry:test

# Expected output:
# Sending test event...
# Test event sent with ID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

### What Sentry Captures

**Automatically Captured:**
- ✅ Unhandled exceptions
- ✅ Failed jobs
- ✅ HTTP request errors
- ✅ Database query errors
- ✅ Blockchain operation failures
- ✅ Performance metrics (with traces_sample_rate > 0)

**Context Information:**
- Request method, URL, headers
- User agent and IP address (if send_default_pii enabled)
- Authenticated user information
- Stack traces with file and line numbers
- Environment (local, staging, production)
- Laravel version and PHP version

### Manual Error Reporting

You can manually capture exceptions in your code:

```php
use Sentry\Laravel\Facades\Sentry;

try {
    // Your code
} catch (\Exception $e) {
    // Log to Laravel log
    Log::error('Custom error message', ['context' => $data]);
    
    // Also send to Sentry with additional context
    Sentry::captureException($e);
    
    // Or with custom context
    Sentry::withScope(function ($scope) use ($e, $data) {
        $scope->setContext('custom_data', $data);
        $scope->setTag('feature', 'blockchain');
        Sentry::captureException($e);
    });
}
```

### Breadcrumbs

Sentry automatically tracks breadcrumbs (user actions leading to errors):

```php
// Add custom breadcrumb
Sentry::addBreadcrumb(new \Sentry\Breadcrumb(
    \Sentry\Breadcrumb::LEVEL_INFO,
    \Sentry\Breadcrumb::TYPE_USER,
    'procurement.published',
    'Published procurement to blockchain',
    ['procurement_id' => $procurementId]
));
```

### Performance Monitoring

With `SENTRY_TRACES_SAMPLE_RATE=1.0`, Sentry captures:

- **HTTP Request Duration**: How long requests take
- **Database Queries**: Slow queries and N+1 problems
- **External API Calls**: S3, MultiChain, Resend API
- **Cache Operations**: Redis/Database cache performance
- **Queue Job Duration**: Background job performance

**View in Sentry Dashboard:**
1. Go to Performance → Transactions
2. Filter by operation: `http.server` for web requests
3. Sort by P95 duration to find slowest endpoints

### Release Tracking

Track errors by deployment version:

```bash
# Set release version in .env
SENTRY_RELEASE=procuchain@1.0.0

# Or use git commit hash
SENTRY_RELEASE=$(git rev-parse --short HEAD)
```

### Filtering Errors

**Ignore Specific Exceptions:**

Edit `config/sentry.php`:

```php
'ignore_exceptions' => [
    Illuminate\Auth\AuthenticationException::class,
    Illuminate\Validation\ValidationException::class,
],
```

**Ignore Specific URLs:**

```php
'ignore_transactions' => [
    '/up',              // Health check
    '/horizon/*',       // Horizon dashboard
    '/_debugbar/*',     // Debug bar
],
```

### Best Practices

1. **Review Sentry Daily**
   - Check for new issues
   - Triage and assign critical errors
   - Mark resolved issues

2. **Set Up Alerts**
   - Configure email/Slack alerts for critical errors
   - Set threshold alerts (e.g., >10 errors in 5 minutes)
   - Use issue assignment rules

3. **Use Tags for Organization**
   ```php
   Sentry::configureScope(function ($scope) {
       $scope->setTag('feature', 'blockchain');
       $scope->setTag('stage', 'procurement-initiation');
   });
   ```

4. **Production vs Development**
   - Use different DSNs for staging and production
   - Lower sample rate in production if needed (0.1 = 10%)
   - Enable send_default_pii only if privacy-compliant

5. **Performance Budget**
   - Monitor P95 response times
   - Investigate queries over 100ms
   - Track slow blockchain operations

---

## Application Logging

### Laravel Logs

**Location:** `storage/logs/laravel.log`

**Log Levels:**
- `emergency`: System is unusable
- `alert`: Immediate action required
- `critical`: Critical conditions
- `error`: Error conditions
- `warning`: Warning conditions
- `notice`: Normal but significant
- `info`: Informational messages
- `debug`: Debug-level messages

**Configuration:** `config/logging.php`

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'sentry'],
    ],
    'sentry' => [
        'driver' => 'sentry',
        'level' => 'error', // Only send errors and above to Sentry
    ],
],
```

### Real-Time Log Viewing

**Laravel Pail:**
```bash
php artisan pail

# Filter by level
php artisan pail --filter=error

# Filter by user
php artisan pail --user=1
```

### Structured Logging

Always include context:

```php
Log::info('Procurement published to blockchain', [
    'procurement_id' => $procurement->id,
    'blockchain_txid' => $txid,
    'stage' => $procurement->stage,
    'document_count' => $procurement->document_count,
    'execution_time' => $executionTime,
]);
```

### Log Rotation

Laravel automatically rotates logs daily. To change:

```bash
# In .env
LOG_CHANNEL=daily
LOG_LEVEL=debug
LOG_DAYS=14  # Keep 14 days of logs
```

---

## Performance Monitoring

### Database Query Monitoring

**Enable Query Logging (Development):**
```php
DB::listen(function ($query) {
    if ($query->time > 100) { // Log queries over 100ms
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time . 'ms',
        ]);
    }
});
```

**Monitor with Sentry:**
- Sentry automatically tracks database queries
- View in Performance → Database tab
- Identifies N+1 queries and slow queries

### Blockchain Performance

**Monitor MultiChain Operations:**
```php
Log::info('Blockchain operation started', [
    'operation' => 'publish',
    'stream' => 'procurement.documents',
]);

$startTime = microtime(true);
$txid = $this->multichainService->publish(...);
$duration = (microtime(true) - $startTime) * 1000;

Log::info('Blockchain operation completed', [
    'operation' => 'publish',
    'stream' => 'procurement.documents',
    'txid' => $txid,
    'duration_ms' => $duration,
]);
```

### Cache Performance

**Monitor Cache Hit Rates:**
```php
// Track cache hits and misses
$cacheKey = 'dashboard.analytics';

if (Cache::has($cacheKey)) {
    Log::debug('Cache hit', ['key' => $cacheKey]);
} else {
    Log::debug('Cache miss', ['key' => $cacheKey]);
}
```

---

## Audit Trails

### User Login Logs

**Table:** `user_login_logs`

**Tracks:**
- Login attempts (successful and failed)
- IP address and geolocation
- Device type, browser, platform
- Login/logout timestamps

**View in Admin Dashboard:**
- Navigate to Admin → Login Logs
- Filter by user, date range, IP address
- View suspicious login attempts

**Query Examples:**
```php
// Recent logins
$recentLogins = UserLoginLog::with('user')
    ->where('successful', true)
    ->orderBy('login_at', 'desc')
    ->limit(100)
    ->get();

// Failed login attempts
$failedAttempts = UserLoginLog::where('successful', false)
    ->where('login_at', '>', now()->subHours(24))
    ->count();

// Logins by IP
$loginsByIp = UserLoginLog::where('ip_address', $ipAddress)
    ->orderBy('login_at', 'desc')
    ->get();
```

### Document View Tracking

**Table:** `document_views`

**Tracks:**
- Who viewed which document
- When they viewed it
- How long they viewed it
- IP address and user agent

**Analytics:**
```php
// Most viewed documents
$mostViewed = DocumentView::select('file_key', DB::raw('count(*) as view_count'))
    ->groupBy('file_key')
    ->orderBy('view_count', 'desc')
    ->limit(10)
    ->get();

// User document access
$userViews = DocumentView::where('user_id', $userId)
    ->with('user')
    ->orderBy('viewed_at', 'desc')
    ->get();
```

### Blockchain Event Logs

**Stream:** `procurement.events`

**Tracks:**
- All blockchain operations
- Publication events
- Status changes
- Correction events

**Query Blockchain Events:**
```php
$events = $multichainService->listStreamKeyItems(
    'procurement.events',
    $procurementId,
    true,  // verbose
    100    // limit
);
```

---

## Health Checks

### Application Health

**Endpoint:** `/up`

Returns 200 OK if application is healthy.

**Custom Health Checks:**
```php
// In routes/web.php or custom health check endpoint
Route::get('/health', function () {
    $checks = [];
    
    // Database
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error';
    }
    
    // MultiChain
    try {
        $multichain = app(MultichainService::class);
        $multichain->getInfo();
        $checks['blockchain'] = 'ok';
    } catch (\Exception $e) {
        $checks['blockchain'] = 'error';
    }
    
    // Storage
    try {
        Storage::disk('s3')->exists('health-check.txt');
        $checks['storage'] = 'ok';
    } catch (\Exception $e) {
        $checks['storage'] = 'error';
    }
    
    $healthy = !in_array('error', $checks);
    
    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
});
```

### MultiChain Health

```bash
# Check MultiChain connection
php artisan multichain:setup --check
```

### Queue Health

**Monitor Failed Jobs:**
```php
// In a scheduled command
$failedJobs = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subHour())
    ->count();

if ($failedJobs > 10) {
    Log::alert('High number of failed jobs', [
        'count' => $failedJobs,
    ]);
}
```

---

## Alerts and Notifications

### Sentry Alerts

**Configure in Sentry Dashboard:**
1. Go to Project Settings → Alerts
2. Create alert rules:
   - Error rate exceeds threshold
   - New issue appears
   - Issue state changes
   - Performance degradation

**Alert Channels:**
- Email
- Slack
- Discord
- PagerDuty
- Custom webhooks

### Laravel Notifications

**Critical System Alerts:**
```php
// Send to admin users
$admins = User::role('admin')->get();

Notification::send($admins, new SystemAlertNotification(
    'Blockchain Connection Failed',
    'Unable to connect to MultiChain node',
    'critical'
));
```

---

## Troubleshooting

### Sentry Not Capturing Errors

1. **Check DSN Configuration:**
   ```bash
   php artisan config:clear
   php artisan sentry:test
   ```

2. **Verify Integration:**
   ```bash
   # Check bootstrap/app.php
   grep -n "Sentry" bootstrap/app.php
   ```

3. **Check Exception Handler:**
   - Ensure `Integration::handles($exceptions)` is in `bootstrap/app.php`

4. **Network Issues:**
   - Verify firewall allows outbound HTTPS to sentry.io
   - Check `SENTRY_LARAVEL_DSN` is correct

### High Sentry Event Volume

**Reduce Sample Rate:**
```bash
# In .env
SENTRY_TRACES_SAMPLE_RATE=0.1  # Only 10% of transactions
```

**Ignore Noisy Exceptions:**
```php
// In config/sentry.php
'ignore_exceptions' => [
    Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
],
```

### Performance Issues

1. **Identify Slow Queries:**
   - Check Sentry Performance tab
   - Look for N+1 queries
   - Add eager loading

2. **Optimize Cache:**
   - Check cache hit rates
   - Increase cache TTL for static data
   - Use Redis for frequently accessed data

3. **Monitor Blockchain:**
   - Check MultiChain node health
   - Verify network latency
   - Ensure sufficient confirmations

### Log Disk Space

**Automatic Cleanup:**
```bash
# Schedule in app/Console/Kernel.php
$schedule->command('cache:cleanup --hours=24')->daily();
```

**Manual Cleanup:**
```bash
# Delete old logs
find storage/logs -name "*.log" -mtime +30 -delete

# Or keep only recent logs
php artisan log:clear --keep=7
```

---

## Monitoring Checklist

### Daily Tasks
- [ ] Review Sentry issues
- [ ] Check failed jobs queue
- [ ] Verify blockchain health
- [ ] Monitor login logs for suspicious activity

### Weekly Tasks
- [ ] Review performance metrics
- [ ] Check disk space usage
- [ ] Analyze slow queries
- [ ] Review error trends

### Monthly Tasks
- [ ] Update monitoring thresholds
- [ ] Review and update alert rules
- [ ] Archive old logs
- [ ] Performance optimization review

---

## Additional Resources

- [Sentry Laravel Documentation](https://docs.sentry.io/platforms/php/guides/laravel/)
- [Laravel Logging Documentation](https://laravel.com/docs/12.x/logging)
- [Laravel Monitoring Best Practices](https://laravel.com/docs/12.x/monitoring)
- [MultiChain Monitoring Guide](https://www.multichain.com/developers/monitoring/)

---

**Last Updated:** October 31, 2025
