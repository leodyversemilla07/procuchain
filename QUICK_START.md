# Quick Start Guide: Semantic Search & Report Generation

## Prerequisites
- PHP 8.3+
- Composer installed
- Node.js & npm installed
- Laravel application running
- Database migrated and seeded

## Installation Steps

### 1. Install Dependencies (if not already done)
```bash
composer install
npm install
```

### 2. Verify Routes
Check that the new routes are registered:
```bash
php artisan route:list | grep reports
php artisan route:list | grep search
```

You should see:
- GET /reports
- POST /reports/generate
- POST /reports/export
- POST /search

### 3. Build Frontend Assets
```bash
npm run build
# OR for development with hot reload:
npm run dev
```

### 4. Run Tests
```bash
# Run all report-related tests
php artisan test --filter=Report

# Run semantic search tests
php artisan test --filter=SemanticSearch

# Run all new tests
php artisan test tests/Feature/SemanticSearchServiceTest.php
php artisan test tests/Feature/ReportGenerationServiceTest.php
php artisan test tests/Feature/ReportControllerTest.php
```

## Quick Usage

### Access the Report Page
1. Start your Laravel server: `php artisan serve`
2. Log in to the application
3. Navigate to: `http://localhost:8000/reports`

### Generate Your First Report

#### Monthly Report
1. Select "Month" as filter type
2. Choose a month (e.g., January)
3. Choose a year (e.g., 2025)
4. Click "Generate Report"

#### Quarterly Report
1. Select "Quarter" as filter type
2. Choose a quarter (Q1, Q2, Q3, or Q4)
3. Choose a year
4. Click "Generate Report"

#### Yearly Report
1. Select "Year" as filter type
2. Choose a year
3. Click "Generate Report"

### Export Reports
After generating a report:
1. Click "Export CSV" for spreadsheet format
2. Click "Export JSON" for structured data

### Search Procurements
1. Enter a search query in the "Search Query" field
2. Optionally add filters (status, stage, etc.)
3. Click "Generate Report"
4. Results will show matching procurements

## API Testing with curl

### Generate a Monthly Report
```bash
curl -X POST http://localhost:8000/reports/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{
    "filter_type": "month",
    "month": 1,
    "year": 2025
  }'
```

### Search for Procurements
```bash
curl -X POST http://localhost:8000/search \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{
    "query": "office"
  }'
```

## Troubleshooting

### "Page not found" Error
- Run `php artisan route:clear`
- Run `php artisan cache:clear`
- Verify routes with `php artisan route:list`

### Frontend Component Not Found
- Run `npm run build`
- Clear browser cache
- Check browser console for errors

### Tests Failing
- Run `composer install` to ensure all dependencies are installed
- Check that database is properly configured
- Run `php artisan config:clear`

### Report Generation Errors
- Check that blockchain node is running
- Verify `ProcurementDataService` is working
- Check application logs: `tail -f storage/logs/laravel.log`

### CSV Export Not Working
- Check browser download settings
- Verify sufficient permissions on storage directory
- Check for JavaScript errors in browser console

## Feature Checklist

After installation, verify these features work:

- [ ] Can access `/reports` page
- [ ] Can select different filter types
- [ ] Can generate monthly report
- [ ] Can generate quarterly report
- [ ] Can generate yearly report
- [ ] Can generate date range report
- [ ] Can search with text query
- [ ] Statistics cards display correctly
- [ ] Time series chart renders
- [ ] Distribution cards show data
- [ ] Can export to CSV
- [ ] Can export to JSON
- [ ] Loading states work correctly
- [ ] Error messages display properly

## Common Filter Combinations

### Last Month's Active Procurements
```json
{
  "filter_type": "month",
  "month": 12,
  "year": 2024,
  "status": "active"
}
```

### Q1 2025 Bidding Stage
```json
{
  "filter_type": "quarter",
  "quarter": 1,
  "year": 2025,
  "stage": "bidding"
}
```

### Year-to-Date Public Bidding
```json
{
  "filter_type": "year",
  "year": 2025,
  "mode": "public_bidding"
}
```

### Custom Date Range with Search
```json
{
  "filter_type": "date_range",
  "date_from": "2025-01-01",
  "date_to": "2025-06-30",
  "query": "office supplies"
}
```

## Performance Tips

1. **Use specific filters** to narrow results
2. **Avoid very wide date ranges** (e.g., 5+ years)
3. **Export large datasets as CSV** (more efficient than JSON)
4. **Close reports** when done to free browser memory

## Getting Help

1. **Documentation**: See `SEMANTIC_SEARCH_README.md` for detailed docs
2. **Implementation Details**: See `IMPLEMENTATION_SUMMARY.md`
3. **Test Examples**: Check test files in `tests/Feature/`
4. **Logs**: Check `storage/logs/laravel.log` for errors

## Next Steps

1. ✅ Install and test the feature
2. ✅ Generate sample reports
3. ✅ Test all filter types
4. ✅ Verify exports work
5. ✅ Run test suite
6. 🔄 Deploy to staging/production
7. 🔄 Train users on new features

## Support

For issues:
1. Check application logs
2. Verify all tests pass
3. Check browser console for frontend errors
4. Review the troubleshooting section above

---

**Ready to start?** Run the tests first, then access `/reports` in your browser!
