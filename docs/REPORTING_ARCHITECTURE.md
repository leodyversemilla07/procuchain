# Architecture Diagram: Semantic Search & Report Generation

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                       │
│                  (resources/js/pages/reports)                │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Report Index Page (index.tsx)                       │  │
│  │  - Filter Selection                                  │  │
│  │  - Search Input                                      │  │
│  │  - Statistics Cards                                  │  │
│  │  - Time Series Charts                                │  │
│  │  - Export Buttons                                    │  │
│  └────────────────┬─────────────────────────────────────┘  │
└───────────────────┼─────────────────────────────────────────┘
                    │
                    │ HTTP Requests
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                    API ENDPOINTS (Routes)                    │
│                      (routes/web.php)                        │
│                                                               │
│  GET  /reports           → Show report page                 │
│  POST /reports/generate  → Generate report                  │
│  POST /reports/export    → Export report (CSV/JSON)         │
│  POST /search            → Semantic search                  │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    │ Route to Controller
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                  REPORT CONTROLLER                           │
│            (app/Http/Controllers/ReportController.php)       │
│                                                               │
│  - Validates input parameters                                │
│  - Handles authentication                                    │
│  - Coordinates services                                      │
│  - Formats responses                                         │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    │ Dependency Injection
                    ▼
┌─────────────────────────────────────────────────────────────┐
│              REPORT GENERATION SERVICE                       │
│          (app/Services/ReportGenerationService.php)          │
│                                                               │
│  - Builds filters from parameters                            │
│  - Converts month/year/quarter to date ranges               │
│  - Generates time series data                                │
│  - Exports to CSV/JSON                                       │
│                                                               │
│  Filter Types:                                               │
│  ├─ Month   → [date_from, date_to]                          │
│  ├─ Quarter → [date_from, date_to]                          │
│  ├─ Year    → [date_from, date_to]                          │
│  └─ Range   → [date_from, date_to]                          │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    │ Uses
                    ▼
┌─────────────────────────────────────────────────────────────┐
│              SEMANTIC SEARCH SERVICE                         │
│           (app/Services/SemanticSearchService.php)           │
│                                                               │
│  - Fetches procurement data                                  │
│  - Applies filters (status, stage, mode, category)          │
│  - Performs text search                                      │
│  - Filters by date range                                     │
│  - Calculates statistics                                     │
│  - Aggregates results                                        │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    │ Fetches Data
                    ▼
┌─────────────────────────────────────────────────────────────┐
│            PROCUREMENT DATA SERVICE                          │
│          (app/Services/ProcurementDataService.php)           │
│                                                               │
│  - Fetches procurement data from blockchain                  │
│  - Processes and formats data                                │
│  - Returns structured arrays                                 │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    │ Reads From
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                     BLOCKCHAIN LAYER                         │
│                    (MultiChain/Storage)                      │
│                                                               │
│  - Procurement records                                       │
│  - Document metadata                                         │
│  - Status history                                            │
│  - Event logs                                                │
└─────────────────────────────────────────────────────────────┘
```

## Data Flow Diagram

```
User Input
    ↓
┌───────────────────────────────────────┐
│ Filter Selection:                     │
│ - filter_type: "month"                │
│ - month: 1                            │
│ - year: 2025                          │
│ - query: "office supplies"            │
│ - status: "active"                    │
└───────────────┬───────────────────────┘
                │
                ▼
    POST /reports/generate
                │
                ▼
┌───────────────────────────────────────┐
│ ReportController::generate()          │
│ - Validates input                     │
│ - CSRF check                          │
│ - Authentication                      │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│ ReportGenerationService               │
│   ::generateReport()                  │
│                                       │
│ 1. buildFilters()                     │
│    month=1, year=2025                 │
│    → date_from: 2025-01-01           │
│    → date_to: 2025-01-31             │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│ SemanticSearchService::search()       │
│                                       │
│ 2. Fetch data                         │
│ 3. Apply filters:                     │
│    - Text: "office supplies"          │
│    - Status: "active"                 │
│    - Date: 2025-01-01 to 2025-01-31  │
│                                       │
│ 4. Calculate statistics               │
│    - Total count                      │
│    - By status/stage/mode/category    │
│    - Total ABC amount                 │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│ Results Processing                    │
│                                       │
│ 5. Generate time series               │
│    - Daily breakdown for month        │
│    - Monthly for year/quarter         │
│                                       │
│ 6. Build response                     │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│ JSON Response                         │
│ {                                     │
│   success: true,                      │
│   summary: {...},                     │
│   time_series: [...],                 │
│   data: [...]                         │
│ }                                     │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│ Frontend Visualization                │
│ - Statistics cards                    │
│ - Line charts                         │
│ - Distribution breakdowns             │
└───────────────────────────────────────┘
```

## Filter Processing Flow

```
┌─────────────┐
│ User Selects│
│ "Month"     │
└──────┬──────┘
       │
       ▼
┌────────────────────────┐
│ Input:                 │
│ - month: 1             │
│ - year: 2025           │
└──────┬─────────────────┘
       │
       ▼
┌────────────────────────┐
│ applyMonthFilter()     │
│                        │
│ startDate = Carbon     │
│   ::create(2025,1,1)   │
│   ->startOfMonth()     │
│                        │
│ endDate = Carbon       │
│   ::create(2025,1,1)   │
│   ->endOfMonth()       │
└──────┬─────────────────┘
       │
       ▼
┌────────────────────────┐
│ Result:                │
│ date_from: 2025-01-01  │
│ date_to: 2025-01-31    │
└──────┬─────────────────┘
       │
       ▼
┌────────────────────────┐
│ applyFilters()         │
│ - Filter by date range │
│ - Filter by status     │
│ - Filter by query      │
└──────┬─────────────────┘
       │
       ▼
┌────────────────────────┐
│ Filtered Results       │
└────────────────────────┘
```

## Export Flow

```
User Clicks "Export CSV"
    ↓
POST /reports/export
    ↓
┌───────────────────────┐
│ 1. Generate report    │
│    (same as above)    │
└──────────┬────────────┘
           │
           ▼
┌───────────────────────┐
│ 2. exportReport()     │
│    format: 'csv'      │
└──────────┬────────────┘
           │
           ▼
┌───────────────────────┐
│ 3. exportToCsv()      │
│    - Build headers    │
│    - Format rows      │
│    - Escape values    │
└──────────┬────────────┘
           │
           ▼
┌───────────────────────┐
│ 4. Stream download    │
│    Content-Type: csv  │
│    Filename: report-  │
│             YYYY-MM-DD│
└──────────┬────────────┘
           │
           ▼
    Browser Download
```

## Component Relationships

```
┌────────────────────────────────────────────┐
│         ReportController                   │
│                                            │
│  constructor(                              │
│    ReportGenerationService,                │
│    SemanticSearchService                   │
│  )                                         │
└────────┬─────────────────┬─────────────────┘
         │                 │
         │                 └──────────────┐
         │                                │
         ▼                                ▼
┌────────────────────┐      ┌───────────────────────┐
│ Report Generation  │      │ Semantic Search       │
│ Service            │──────│ Service               │
│                    │ uses │                       │
│ - generateReport() │      │ - search()            │
│ - exportReport()   │      │ - calculateStats()    │
│ - buildFilters()   │      │ - applyFilters()      │
└────────────────────┘      └──────────┬────────────┘
                                       │
                                       │ uses
                                       ▼
                            ┌───────────────────────┐
                            │ Procurement Data      │
                            │ Service               │
                            │                       │
                            │ - fetchAndProcess     │
                            │   Procurements()      │
                            └───────────────────────┘
```

## Technology Stack

```
┌─────────────────────────────────────────────┐
│              FRONTEND LAYER                 │
│                                             │
│  React 19.2.4                               │
│  TypeScript                                 │
│  Inertia.js v2.3.13                         │
│  Recharts (visualization)                   │
│  Tailwind CSS v4                            │
└─────────────────┬───────────────────────────┘
                  │
                  │ HTTP/JSON
                  │
┌─────────────────┴───────────────────────────┐
│              BACKEND LAYER                  │
│                                             │
│  Laravel 13.0.0                             │
│  PHP 8.4.12                                 │
│  Carbon (date library)                      │
└─────────────────┬───────────────────────────┘
                  │
                  │ Queries
                  │
┌─────────────────┴───────────────────────────┐
│              DATA LAYER                     │
│                                             │
│  MultiChain                                 │
│  Blockchain Storage                         │
└─────────────────────────────────────────────┘
```

## Key Design Patterns

1. **Dependency Injection**: Services injected via constructor
2. **Service Layer Pattern**: Business logic separated from controllers
3. **Repository Pattern**: Data access abstracted
4. **Strategy Pattern**: Different filter types handled uniformly
5. **Single Responsibility**: Each class has one clear purpose
6. **Open/Closed Principle**: Easy to add new filter types

## Security Layers

```
User Request
    ↓
┌────────────────┐
│ Authentication │  ← Required for all endpoints
└────────┬───────┘
         ↓
┌────────────────┐
│ CSRF Token     │  ← Verified on POST requests
└────────┬───────┘
         ↓
┌────────────────┐
│ Input          │  ← Laravel validation
│ Validation     │
└────────┬───────┘
         ↓
┌────────────────┐
│ Business Logic │  ← Service layer
└────────┬───────┘
         ↓
┌────────────────┐
│ Data Access    │  ← Repository pattern
└────────────────┘
```

---

This architecture provides:
- ✅ Separation of concerns
- ✅ Testability (each layer can be mocked)
- ✅ Maintainability (clear structure)
- ✅ Extensibility (easy to add features)
- ✅ Security (multiple validation layers)
