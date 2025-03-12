<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12pt;
            line-height: 1.2;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 16pt;
            text-transform: uppercase;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        .info-table td:nth-child(1),
        .info-table td:nth-child(3) {
            width: 20%;
            font-weight: bold;
        }

        .info-table td:nth-child(2),
        .info-table td:nth-child(4) {
            width: 30%;
        }

        .items-table th:nth-child(1),
        .items-table td:nth-child(1) {
            width: 5%;
            text-align: center;
        }

        .items-table th:nth-child(2),
        .items-table td:nth-child(2) {
            width: 10%;
        }

        .items-table th:nth-child(3),
        .items-table td:nth-child(3) {
            width: 45%;
        }

        .items-table th:nth-child(4),
        .items-table td:nth-child(4) {
            width: 10%;
            text-align: right;
        }

        .items-table th:nth-child(5),
        .items-table td:nth-child(5) {
            width: 15%;
            text-align: right;
        }

        .items-table th:nth-child(6),
        .items-table td:nth-child(6) {
            width: 15%;
            text-align: right;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .total {
            text-align: right;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .specifications {
            margin-top: 20px;
        }

        .specifications strong {
            display: block;
            margin-bottom: 5px;
        }

        .specifications ul {
            margin: 0;
            padding-left: 20px;
            list-style-type: disc;
        }

        .approval {
            margin-top: 40px;
        }

        .approval p {
            margin: 0 0 10px 0;
        }

        .signature-section {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-gap: 20px;
        }

        .signature-box {
            text-align: center;
            margin-bottom: 20px;
        }

        .signature-line {
            border-bottom: 1px solid black;
            width: 80%;
            margin: 40px auto 5px auto;
        }

        .signature-name {
            font-weight: bold;
            margin-bottom: 0;
        }

        .signature-title {
            font-style: italic;
            margin-top: 0;
        }

        .purpose-section {
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1>PURCHASE REQUEST</h1>
    </div>

    <!-- Organizational and Project Information -->
    <table class="info-table">
        <tr>
            <td>LGU:</td>
            <td>{{ $purchaseRequest['lgu'] ?? 'MUNICIPAL GOVERNMENT OF GLORIA' }}</td>
            <td>Fund:</td>
            <td>{{ $purchaseRequest['fund'] ?? 'SPECIAL EDUCATION FUND' }}</td>
        </tr>
        <tr>
            <td>Department:</td>
            <td>{{ $purchaseRequest['department'] ?? 'Special Education Fund' }}</td>
            <td>PR No.:</td>
            <td>{{ $purchaseRequest['pr_no'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Section:</td>
            <td>{{ isset($purchaseRequest['section']) ? $purchaseRequest['section'] : '' }}</td>
            <td>PR Date:</td>
            <td>{{ $purchaseRequest['pr_date'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Name of the Project/s:</td>
            <td>{{ $purchaseRequest['project_name'] ?? '' }}</td>
            <td>FPP:</td>
            <td>{{ $purchaseRequest['fpp'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Location of the Project/s:</td>
            <td>{{ $purchaseRequest['project_location'] ?? '' }}</td>
            <td>Project Reference No:</td>
            <td>{{ $purchaseRequest['project_reference'] ?? '' }}</td>
        </tr>
    </table>

    <!-- Purpose Section -->
    <div class="purpose-section">
        <strong>Purpose:</strong> {{ $purchaseRequest['purpose'] ?? 'For official use' }}
    </div>

    <!-- Itemized Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Item No.</th>
                <th>Unit of Measure</th>
                <th>Item Description</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($items) && is_array($items) && count($items) > 0)
                @foreach ($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['unit'] ?? 'unit' }}</td>
                        <td>{{ $item['description'] ?? '' }}</td>
                        <td>{{ $item['quantity'] ?? 1 }}</td>
                        <td>₱{{ number_format($item['unit_cost'] ?? 0, 2) }}</td>
                        <td>₱{{ number_format($item['total_cost'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="6" style="text-align: center;">No items found</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Total Amount -->
    <div class="total">
        Total: ₱{{ number_format($grandTotal ?? 0, 2) }}
    </div>

    <!-- Product Specifications -->
    <div class="specifications">
        <strong>PRODUCT SPECIFICATIONS</strong>
        <ul>
            <li>The supplier shall supply products with a visible On/Off switch</li>
            <li>The supplier shall supply the products in recyclable packages and shall provide a packaging take-back
                service</li>
            @if (isset($additional_specs) && is_array($additional_specs))
                @foreach ($additional_specs as $spec)
                    <li>{{ $spec }}</li>
                @endforeach
            @endif
        </ul>
    </div>

    <!-- Signatories Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <p class="signature-name">{{ $purchaseRequest['requested_by_name'] ?? '' }}</p>
            <p class="signature-title">{{ $purchaseRequest['requested_by_designation'] ?? 'Requestor' }}</p>
            <!-- Date line removed -->
        </div>

        <div class="signature-box">
            <div class="signature-line"></div>
            <p class="signature-name">{{ $purchaseRequest['approved_by_name'] ?? '' }}</p>
            <p class="signature-title">{{ $purchaseRequest['approved_by_designation'] ?? 'Approving Officer' }}</p>
            <!-- Date line removed -->
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <p>Budget Availability:</p>
            <div class="signature-line"></div>
            <p class="signature-name">{{ $purchaseRequest['budget_officer_name'] ?? '' }}</p>
            <p class="signature-title">{{ $purchaseRequest['budget_officer_designation'] ?? 'Budget Officer' }}</p>
            <!-- Date line removed -->
        </div>

        <div class="signature-box">
            <p>Cash Availability:</p>
            <div class="signature-line"></div>
            <p class="signature-name">{{ $purchaseRequest['treasurer_name'] ?? '' }}</p>
            <p class="signature-title">{{ $purchaseRequest['treasurer_designation'] ?? 'Treasurer' }}</p>
            <!-- Date line removed -->
        </div>
    </div>
</body>

</html>