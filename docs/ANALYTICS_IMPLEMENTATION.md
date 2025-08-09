# Analytics Implementation Documentation

## Overview

This document describes the comprehensive analytics implementation for the ProcuChain system. The analytics provide insights into procurement processes, document management, user activity, and blockchain operations.

## Architecture

The analytics system follows a layered architecture:

1. **Backend Layer** (PHP/Laravel)
   - `AnalyticsService`: Core business logic for data aggregation
   - `AnalyticsController`: HTTP endpoints for dashboard and data
   - Database queries with optimized joins and aggregations

2. **Frontend Layer** (TypeScript/React)
   - TypeScript types for type safety
   - Custom React hooks for data fetching
   - Recharts for data visualization
   - Inertia.js for SSR and seamless navigation

3. **API Layer**
   - Web routes (not API routes) following Inertia.js conventions
   - Role-based access control
   - Caching and performance optimization

## Backend Components

### AnalyticsService (`app/Services/AnalyticsService.php`)

Provides methods for:
- `getProcurementAnalytics()`: Procurement pipeline and performance metrics
- `getDocumentAnalytics()`: Document upload, validation, and processing stats
- `getUserActivityAnalytics()`: User engagement and role-based activity
- `getBlockchainAnalytics()`: Smart contract and transaction metrics
- `getComprehensiveAnalytics()`: Combined dashboard overview

**Key Features:**
- Role-based data filtering
- Time range filtering (7 days, 30 days, 90 days, 1 year)
- Performance optimized queries
- Caching for frequently accessed data

### AnalyticsController (`app/Http/Controllers/AnalyticsController.php`)

Endpoints:
- `GET /analytics` - Main dashboard with initial data (SSR)
- `GET /analytics/procurement-data` - Procurement analytics (AJAX)
- `GET /analytics/documents-data` - Document analytics (AJAX)
- `GET /analytics/user-activity-data` - User activity analytics (AJAX)
- `GET /analytics/blockchain-data` - Blockchain analytics (AJAX)
- `POST /analytics/export` - Export analytics data
- `GET /analytics/download/{filename}` - Download exported files

**Role-based Access:**
- BAC Secretariat: Full procurement and document analytics
- BAC Chairman: Executive overview and approval metrics
- HOPE: Budget and high-level overview
- Admin: Full system analytics including user activity

## Frontend Components

### TypeScript Types (`resources/js/types/analytics.ts`)

Comprehensive type definitions for:
- `ProcurementAnalytics`: Pipeline stages, performance metrics
- `DocumentAnalytics`: Upload stats, validation metrics
- `UserActivityAnalytics`: Login patterns, role activity
- `BlockchainAnalytics`: Transaction metrics, smart contract data
- `AnalyticsFilters`: Time range and filtering options

### React Hooks (`resources/js/hooks/useAnalytics.ts`)

Custom hooks for data fetching:
- `useAnalytics<T>()`: Generic analytics hook with caching
- `useProcurementAnalytics()`: Specialized for procurement data
- `useDocumentAnalytics()`: Specialized for document data
- `useUserActivityAnalytics()`: Specialized for user activity
- `useBlockchainAnalytics()`: Specialized for blockchain data

**Features:**
- Automatic refresh capabilities
- Error handling with toast notifications
- Loading states
- Request cancellation for performance
- TypeScript generics for type safety

### Dashboard Component (`resources/js/pages/Analytics/Dashboard.tsx`)

Main analytics dashboard featuring:
- **Modern App Layout**: Uses `AppLayout` component with sidebar navigation and breadcrumbs
- **Overview Cards**: Key metrics at a glance
- **Procurement Pipeline**: Visual representation of procurement stages
- **Document Processing**: Upload and validation statistics
- **User Activity**: Login patterns and role-based activity
- **Blockchain Metrics**: Smart contract and transaction data
- **Export Functionality**: Download analytics as CSV/Excel

**UI Components:**
- Recharts for data visualization (Bar, Line, Pie charts)
- Responsive grid layout with proper spacing
- Loading skeletons
- Error handling
- Real-time data updates
- Integrated with app-layout sidebar navigation system

## Routes (`routes/analytics.php`)

```php
// Main Dashboard
Route::get('/analytics', [AnalyticsController::class, 'dashboard'])
    ->name('analytics.dashboard');

// Data Endpoints
Route::prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/procurement-data', [AnalyticsController::class, 'procurementAnalytics']);
    Route::get('/documents-data', [AnalyticsController::class, 'documentAnalytics']);
    Route::get('/user-activity-data', [AnalyticsController::class, 'userActivityAnalytics']);
    Route::get('/blockchain-data', [AnalyticsController::class, 'blockchainAnalytics']);
    Route::post('/export', [AnalyticsController::class, 'exportData']);
    Route::get('/download/{filename}', [AnalyticsController::class, 'downloadExport']);
});

// Role-specific routes
Route::middleware(['role:bac_secretariat'])->group(function () {
    Route::get('/analytics/secretariat', [AnalyticsController::class, 'dashboard']);
});
// ... other role-specific routes
```

## Key Features

### 1. Role-Based Analytics
- **BAC Secretariat**: Procurement process analytics, document management
- **BAC Chairman**: Executive dashboard, approval workflows
- **HOPE**: Budget oversight, high-level metrics
- **Admin**: Complete system analytics, user management

### 2. Time Range Filtering
- Last 7 days
- Last 30 days (default)
- Last 90 days
- Last year
- Custom date ranges

### 3. Performance Optimization
- Database query optimization with proper indexing
- Caching for frequently accessed data
- Lazy loading for chart components
- Request debouncing and cancellation

### 4. Data Visualization
- **Bar Charts**: Procurement stages, document types
- **Line Charts**: Trends over time, user activity
- **Pie Charts**: Distribution of roles, status breakdown
- **Area Charts**: Cumulative metrics
- **Progress Indicators**: Completion rates, success metrics

### 5. Export Functionality
- CSV export for all analytics data
- Excel export with multiple sheets
- PDF reports for executive summaries
- Scheduled report generation

## Security Considerations

1. **Authentication**: All analytics routes require authentication
2. **Authorization**: Role-based access control for sensitive data
3. **Data Filtering**: Users only see data relevant to their role
4. **Input Validation**: All filter parameters are validated
5. **Rate Limiting**: API endpoints have rate limiting applied

## Performance Considerations

1. **Database Optimization**:
   - Proper indexing on frequently queried columns
   - Query optimization with EXPLAIN analysis
   - Use of database views for complex queries

2. **Caching Strategy**:
   - Redis caching for frequently accessed data
   - Cache invalidation on data updates
   - Different cache TTLs based on data volatility

3. **Frontend Optimization**:
   - Component lazy loading
   - Request deduplication
   - Efficient re-rendering with React.memo
   - Virtualization for large datasets

## Usage Examples

### Accessing Analytics Dashboard
```typescript
// Navigate to analytics
window.location.href = '/analytics';

// Or use Inertia.js
import { router } from '@inertiajs/react';
router.visit('/analytics');
```

### Using Analytics Hooks
```typescript
import { useProcurementAnalytics } from '@/hooks/useAnalytics';

function ProcurementDashboard() {
    const { data, loading, error, refresh } = useProcurementAnalytics(
        { time_range: '30_days' },
        { autoRefresh: true, refreshInterval: 60000 }
    );

    if (loading) return <Skeleton />;
    if (error) return <ErrorMessage message={error} />;

    return <ProcurementCharts data={data} />;
}
```

### Exporting Data
```typescript
import { useAnalyticsExport } from '@/hooks/useAnalytics';

function ExportButton() {
    const { exportData, downloading } = useAnalyticsExport();

    const handleExport = async () => {
        await exportData({
            type: 'procurement',
            format: 'csv',
            filters: { time_range: '30_days' }
        });
    };

    return (
        <Button onClick={handleExport} disabled={downloading}>
            {downloading ? 'Exporting...' : 'Export Data'}
        </Button>
    );
}
```

## Testing

### Backend Testing
```php
// Test analytics service
public function test_procurement_analytics_returns_correct_data()
{
    $analytics = app(AnalyticsService::class);
    $data = $analytics->getProcurementAnalytics(['time_range' => '30_days']);
    
    $this->assertArrayHasKey('total_procurements', $data);
    $this->assertArrayHasKey('pipeline_stages', $data);
}

// Test analytics controller
public function test_analytics_dashboard_requires_authentication()
{
    $response = $this->get('/analytics');
    $response->assertRedirect('/login');
}
```

### Frontend Testing
```typescript
// Test analytics hook
import { renderHook } from '@testing-library/react';
import { useProcurementAnalytics } from '@/hooks/useAnalytics';

test('useProcurementAnalytics returns data', async () => {
    const { result, waitForNextUpdate } = renderHook(() =>
        useProcurementAnalytics()
    );

    expect(result.current.loading).toBe(true);
    
    await waitForNextUpdate();
    
    expect(result.current.loading).toBe(false);
    expect(result.current.data).toBeDefined();
});
```

## Deployment Considerations

1. **Environment Variables**:
   ```env
   ANALYTICS_CACHE_TTL=3600
   ANALYTICS_EXPORT_MAX_SIZE=50000
   ANALYTICS_RATE_LIMIT=100
   ```

2. **Database Migrations**:
   - Ensure all necessary indexes are created
   - Consider partitioning for large datasets

3. **Cache Configuration**:
   - Configure Redis for production caching
   - Set appropriate memory limits

4. **Monitoring**:
   - Add application performance monitoring
   - Set up alerts for slow queries
   - Monitor cache hit rates

## Future Enhancements

1. **Real-time Analytics**: WebSocket integration for live updates
2. **Advanced Filtering**: More granular filtering options
3. **Predictive Analytics**: Machine learning for forecasting
4. **Custom Dashboards**: User-configurable dashboard layouts
5. **Mobile App**: Dedicated mobile analytics interface
6. **API Integration**: REST API for third-party integrations

## Support and Maintenance

For support or questions regarding the analytics implementation:

1. **Documentation**: Refer to this document and inline code comments
2. **Testing**: Run the test suite before making changes
3. **Performance**: Monitor query performance and cache effectiveness
4. **Updates**: Keep dependencies updated and monitor for security issues

---

**Last Updated**: December 2024
**Version**: 1.0.0
**Maintainer**: ProcuChain Development Team
