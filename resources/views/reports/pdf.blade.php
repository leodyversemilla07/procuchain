<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #0d9488;
        }

        .header h1 {
            font-size: 20pt;
            color: #0d9488;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 12pt;
            color: #666;
        }

        .header .date {
            font-size: 9pt;
            color: #999;
            margin-top: 5px;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #0d9488;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }

        .filters {
            background-color: #f8fafc;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .filter-item {
            margin-bottom: 5px;
        }

        .filter-label {
            font-weight: bold;
            color: #666;
        }

        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .stats-row {
            display: table-row;
        }

        .stat-box {
            display: table-cell;
            width: 25%;
            padding: 12px;
            background-color: #f8fafc;
            border: 1px solid #e5e7eb;
            text-align: center;
        }

        .stat-value {
            font-size: 18pt;
            font-weight: bold;
            color: #0d9488;
        }

        .stat-label {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }

        .distribution-container {
            display: table;
            width: 100%;
        }

        .distribution-column {
            display: table-cell;
            width: 50%;
            padding: 0 10px;
        }

        .distribution-item {
            padding: 8px;
            margin-bottom: 5px;
            background-color: #f8fafc;
            border-left: 3px solid #0d9488;
        }

        .distribution-item-label {
            font-weight: bold;
            text-transform: capitalize;
        }

        .distribution-item-count {
            float: right;
            background-color: #0d9488;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9pt;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .data-table th {
            background-color: #0d9488;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
        }

        .data-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #0d9488;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Procurement Report</h1>
        <div class="subtitle">Procuchain - Blockchain-Based Procurement System</div>
        <div class="date">Generated: {{ $report['report_generated_at'] ?? now()->format('F j, Y g:i A') }}</div>
    </div>

    <div class="section">
        <div class="section-title">Report Filters</div>
        <div class="filters">
            @if(isset($filters['filter_type']))
                <div class="filter-item">
                    <span class="filter-label">Period:</span>
                    @if($filters['filter_type'] === 'month')
                        {{ \Carbon\Carbon::create($filters['year'], $filters['month'], 1)->format('F Y') }}
                    @elseif($filters['filter_type'] === 'quarter')
                        Q{{ $filters['quarter'] }} {{ $filters['year'] }}
                    @elseif($filters['filter_type'] === 'year')
                        {{ $filters['year'] }}
                    @elseif($filters['filter_type'] === 'date_range')
                        {{ \Carbon\Carbon::parse($filters['date_from'])->format('M j, Y') }} to {{ \Carbon\Carbon::parse($filters['date_to'])->format('M j, Y') }}
                    @endif
                </div>
            @endif
            @if(!empty($filters['query']))
                <div class="filter-item">
                    <span class="filter-label">Search Query:</span> {{ $filters['query'] }}
                </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Summary Statistics</div>
        <div class="stats-grid">
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value">{{ number_format($report['summary']['total_count']) }}</div>
                    <div class="stat-label">Total Procurements</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">₱{{ number_format($report['summary']['total_abc_amount'], 2) }}</div>
                    <div class="stat-label">Total ABC Amount</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ count($report['summary']['by_stage']) }}</div>
                    <div class="stat-label">Unique Stages</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ count($report['summary']['by_mode']) }}</div>
                    <div class="stat-label">Procurement Modes</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Distribution Analysis</div>
        <div class="distribution-container">
            <div class="distribution-column">
                <h4 style="margin-bottom: 10px;">By Status</h4>
                @forelse($report['summary']['by_status'] as $status => $count)
                    <div class="distribution-item">
                        <span class="distribution-item-label">{{ ucwords(str_replace('_', ' ', $status)) }}</span>
                        <span class="distribution-item-count">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <div class="no-data">No data available</div>
                @endforelse
            </div>

            <div class="distribution-column">
                <h4 style="margin-bottom: 10px;">By Procurement Mode</h4>
                @forelse($report['summary']['by_mode'] as $mode => $count)
                    <div class="distribution-item">
                        <span class="distribution-item-label">{{ ucwords(str_replace('_', ' ', $mode)) }}</span>
                        <span class="distribution-item-count">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <div class="no-data">No data available</div>
                @endforelse
            </div>
        </div>
    </div>

    @if(count($report['data']) > 0)
        <div class="section">
            <div class="section-title">Procurement Details</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>PR Number</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Stage</th>
                        <th>Mode</th>
                        <th>ABC Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['data'] as $procurement)
                        <tr>
                            <td>{{ strtoupper($procurement['id'] ?? 'N/A') }}</td>
                            <td>{{ $procurement['title'] ?? 'N/A' }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $procurement['current_status'] ?? 'N/A')) }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $procurement['stage'] ?? 'N/A')) }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $procurement['mode'] ?? 'N/A')) }}</td>
                            <td>₱{{ number_format($procurement['abc_amount'] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        <p>Procuchain - Blockchain-Powered Document Management for Bids and Awards Committee</p>
        <p>&copy; {{ date('Y') }} Procuchain. All rights reserved.</p>
    </div>
</body>
</html>
