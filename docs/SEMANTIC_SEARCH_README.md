# Search and Reporting Feature Notes

This file is kept for backward compatibility with the original feature rollout, but it now documents the current implementation accurately.

## Important Clarification

The current implementation is not semantic retrieval in the machine-learning sense.

What it actually does:

- filtered keyword search across procurement data
- reporting by month, quarter, year, or date range
- summary statistics and distributions
- CSV and JSON export

What it does not currently do:

- embeddings
- vector indexes
- hybrid retrieval
- LLM-generated ranking

## Current Backend Pieces

- `App\Http\Controllers\ReportController`
- `App\Services\ReportGenerationService`
- `App\Services\SemanticSearchService`
- `App\Services\ProcurementDataService`

## Current Frontend Entry Point

- `resources/js/pages/reports/index.tsx`

## Current Endpoints

- `GET /reports`
- `POST /reports/generate`
- `POST /reports/export`
- `POST /search`

## Supported Filters

- `query`
- `filter_type`
- `month`
- `quarter`
- `year`
- `date_from`
- `date_to`
- `status`
- `stage`
- `mode`
- `category`

## Typical Usage

1. Open the reports page.
2. Choose a time filter or date range.
3. Add optional keyword/status/stage/mode/category filters.
4. Generate the report.
5. Export as CSV or JSON if needed.

## Verification Commands

```bash
php artisan test --compact --filter=Report
php artisan test --compact --filter=SemanticSearch
```

## Canonical References

For current architecture and onboarding, prefer:

- [Quick Start](QUICK_START.md)
- [Architecture](ARCHITECTURE.md)
- [Reporting and Search Architecture](REPORTING_ARCHITECTURE.md)
- [Testing Guide](TESTING.md)
