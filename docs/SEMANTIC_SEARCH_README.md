# Semantic Search & Report Generation Feature

## Overview

This feature provides advanced procurement data search and report generation capabilities with flexible filtering options including month, year, and quarter-based filtering.

## Components

### Backend Services

#### 1. SemanticSearchService (`app/Services/SemanticSearchService.php`)

Provides semantic search functionality for procurement data with advanced filtering.

**Features:**
- Text-based search across procurement titles, IDs, and descriptions
- Filter by status, stage, mode, and category
- Date range filtering
- Statistical aggregation (by status, stage, mode, category)
- ABC amount totals

**Usage:**
```php
$searchService = app(SemanticSearchService::class);

$results = $searchService->search('office supplies', [
    'status' => 'active',
    'stage' => 'bidding',
    'date_from' => '2025-01-01',
    'date_to' => '2025-12-31',
]);

$statistics = $searchService->calculateStatistics($results['results']);
```

#### 2. ReportGenerationService (`app/Services/ReportGenerationService.php`)

Generates comprehensive reports with time-based filtering (month, year, quarter).

**Features:**
- Month-based filtering
- Year-based filtering
- Quarter-based filtering (Q1-Q4)
- Custom date range filtering
- Time series data generation
- CSV and JSON export formats

**Usage:**
```php
$reportService = app(ReportGenerationService::class);

// Generate monthly report
$report = $reportService->generateReport([
    'filter_type' => 'month',
    'month' => 1,
    'year' => 2025,
    'status' => 'active',
]);

// Generate quarterly report
$report = $reportService->generateReport([
    'filter_type' => 'quarter',
    'quarter' => 1,
    'year' => 2025,
]);

// Export to CSV
$csv = $reportService->exportReport($report, 'csv');
```

### Frontend

#### Report Index Page (`resources/js/pages/reports/index.tsx`)

Interactive React page for generating and visualizing reports.

**Features:**
- Filter selection (month, year, quarter, date range)
- Real-time search
- Visual statistics (cards, charts)
- Time series visualization
- Distribution charts (by status, mode)
- Export to CSV/JSON

### API Endpoints

All endpoints require authentication.

#### GET `/reports`
Display the report generation page.

#### POST `/reports/generate`
Generate a report with specified filters.

**Request Body:**
```json
{
    "filter_type": "month",
    "month": 1,
    "year": 2025,
    "query": "optional search term",
    "status": "active",
    "stage": "bidding",
    "mode": "public_bidding",
    "category": "goods"
}
```

**Response:**
```json
{
    "success": true,
    "report_generated_at": "2025-01-15T10:30:00Z",
    "parameters": {...},
    "summary": {
        "total_count": 25,
        "by_status": {"active": 20, "completed": 5},
        "by_stage": {...},
        "by_mode": {...},
        "by_category": {...},
        "total_abc_amount": 5000000
    },
    "time_series": [...],
    "data": [...]
}
```

#### POST `/reports/export`
Export report in specified format.

**Request Body:**
```json
{
    "filter_type": "year",
    "year": 2025,
    "format": "csv"
}
```

**Response:** CSV or JSON file download

#### POST `/search`
Perform semantic search.

**Request Body:**
```json
{
    "query": "office supplies",
    "status": "active",
    "stage": "bidding"
}
```

**Response:**
```json
{
    "success": true,
    "query": "office supplies",
    "filters": {...},
    "total": 5,
    "results": [...]
}
```

## Filter Types

### 1. Month Filter
Filter procurements by specific month and year.

**Parameters:**
- `filter_type`: "month"
- `month`: 1-12 (integer)
- `year`: 2000-2100 (integer)

### 2. Quarter Filter
Filter procurements by fiscal quarter.

**Parameters:**
- `filter_type`: "quarter"
- `quarter`: 1-4 (integer)
  - Q1: January - March
  - Q2: April - June
  - Q3: July - September
  - Q4: October - December
- `year`: 2000-2100 (integer)

### 3. Year Filter
Filter procurements by entire year.

**Parameters:**
- `filter_type`: "year"
- `year`: 2000-2100 (integer)

### 4. Date Range Filter
Custom date range filtering.

**Parameters:**
- `filter_type`: "date_range"
- `date_from`: ISO date string (YYYY-MM-DD)
- `date_to`: ISO date string (YYYY-MM-DD)

## Report Output

### Summary Statistics
- Total procurement count
- Total ABC amount
- Counts by status
- Counts by stage
- Counts by mode
- Counts by category

### Time Series Data
- Daily breakdown (for month view)
- Monthly breakdown (for year/quarter view)
- Trend visualization

### Export Formats
1. **JSON**: Full report data including metadata
2. **CSV**: Tabular format with procurement details

## Testing

Run tests for the new feature:

```bash
php artisan test --filter=SemanticSearchServiceTest
php artisan test --filter=ReportGenerationServiceTest
php artisan test --filter=ReportControllerTest
```

## Usage Examples

### Example 1: Generate Monthly Report
1. Navigate to `/reports`
2. Select "Month" as filter type
3. Choose month and year
4. Click "Generate Report"
5. View statistics and charts
6. Export to CSV if needed

### Example 2: Search for Procurements
1. Navigate to `/reports`
2. Enter search query
3. Select additional filters (status, stage, etc.)
4. Click "Generate Report"
5. Results will be filtered by search term and filters

### Example 3: Quarterly Analysis
1. Navigate to `/reports`
2. Select "Quarter" as filter type
3. Choose quarter (Q1-Q4) and year
4. Click "Generate Report"
5. View quarterly trends and distributions

## Security

- All endpoints require authentication
- Rate limiting applies to prevent abuse
- Input validation on all parameters
- CSRF protection enabled

## Performance Considerations

- Reports are generated on-demand (not cached)
- Large date ranges may take longer to process
- Consider using specific filters to narrow results
- CSV exports stream data for memory efficiency

## Future Enhancements

Potential improvements for future iterations:

1. **Advanced Analytics**
   - Predictive analytics
   - Trend forecasting
   - Anomaly detection

2. **Scheduled Reports**
   - Automated report generation
   - Email delivery
   - Report scheduling

3. **Advanced Visualizations**
   - Pie charts
   - Heat maps
   - Geographic distribution

4. **Export Formats**
   - PDF reports
   - Excel (XLSX) export
   - Custom templates

5. **Report Caching**
   - Cache frequently accessed reports
   - Invalidation strategies
   - Performance optimization

## Troubleshooting

### Issue: Report generation fails
- Check authentication status
- Verify date parameters are valid
- Ensure blockchain node is accessible
- Check application logs

### Issue: Empty results
- Verify date range includes procurement data
- Check filter combinations
- Ensure procurements exist in the system

### Issue: Export fails
- Check browser download settings
- Verify sufficient data exists
- Check server disk space for large exports

## Support

For issues or questions:
1. Check application logs (`storage/logs/laravel.log`)
2. Verify filter parameters
3. Test with broader date ranges
4. Contact system administrator

## References

- [Laravel Documentation](https://laravel.com/docs)
- [Inertia.js Documentation](https://inertiajs.com)
- [Carbon Date Library](https://carbon.nesbot.com)
- [Recharts Documentation](https://recharts.org)
