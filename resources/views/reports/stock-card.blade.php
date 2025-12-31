<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>كارت الصنف</title>
    <style>
        @page { margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Amiri', 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11pt;
            line-height: 1.5;
            color: #1e293b;
        }

        /* Header */
        .header {
            width: 100%;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        .company-name {
            font-size: 15pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .company-info {
            font-size: 9pt;
            color: #475569;
        }

        /* Report Title */
        .report-title {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            color: #1e293b;
        }

        /* Product Info */
        .product-info {
            background-color: #f8fafc;
            padding: 10px 15px;
            margin: 12px 0;
            border-right: 3px solid #1e293b;
        }
        .product-info div {
            margin-bottom: 3px;
            font-size: 10pt;
        }
        .product-info strong {
            color: #1e293b;
        }

        /* Movements Table */
        .movements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .movements-table thead {
            background-color: #1e293b;
            color: #ffffff;
        }
        .movements-table th {
            padding: 8px 5px;
            font-size: 9pt;
            font-weight: bold;
            border: 1px solid #334155;
            text-align: center;
        }
        .movements-table td {
            padding: 7px 5px;
            font-size: 9pt;
            border: 1px solid #cbd5e1;
            text-align: right;
        }
        .movements-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .opening-stock {
            background-color: #fef3c7 !important;
            font-weight: bold;
        }
        .totals-row {
            background-color: #e2e8f0;
            font-weight: bold;
        }

        /* Text Alignment */
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }

        /* Colors */
        .positive { color: #059669; font-weight: bold; }
        .negative { color: #dc2626; font-weight: bold; }
        .in-qty { color: #059669; }
        .out-qty { color: #dc2626; }

        /* Footer */
        .footer {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <div class="company-name">{!! $processedData['company_name'] !!}</div>
        <div class="company-info">{!! $processedData['company_address'] !!}</div>
        <div class="company-info">{{ $companySettings->company_phone }}</div>
    </div>

    {{-- Report Title --}}
    <div class="report-title">{!! $labels['report_title'] !!}</div>

    {{-- Product Info --}}
    <div class="product-info">
        <div><strong>{!! $labels['product_name'] !!}</strong> {!! $processedData['product_name'] !!}</div>
        <div><strong>{!! $labels['product_code'] !!}</strong> {{ $processedData['product_sku'] }}</div>
        <div><strong>{!! $labels['warehouse'] !!}</strong> {!! $processedData['warehouse_name'] !!}</div>
        <div><strong>{!! $labels['from_date'] !!}</strong> {{ $reportData['from_date'] }}</div>
        <div><strong>{!! $labels['to_date'] !!}</strong> {{ $reportData['to_date'] }}</div>
    </div>

    {{-- Movements Table --}}
    <table class="movements-table">
        <thead>
            <tr>
                <th style="width: 11%;">{!! $labels['date'] !!}</th>
                <th style="width: 14%;">{!! $labels['type'] !!}</th>
                <th style="width: 13%;">{!! $labels['reference'] !!}</th>
                <th style="width: 14%;">{!! $labels['warehouse_col'] !!}</th>
                <th style="width: 12%;">{!! $labels['in'] !!}</th>
                <th style="width: 12%;">{!! $labels['out'] !!}</th>
                <th style="width: 12%;">{!! $labels['cost'] !!}</th>
                <th style="width: 12%;">{!! $labels['balance'] !!}</th>
            </tr>
        </thead>
        <tbody>
            {{-- Opening Stock --}}
            <tr class="opening-stock">
                <td colspan="4">{!! $labels['opening_stock'] !!}</td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="text-right {{ $reportData['opening_stock'] >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($reportData['opening_stock'], 0) }}
                </td>
            </tr>

            {{-- Stock Movements --}}
            @foreach ($reportData['movements'] as $movement)
                <tr>
                    <td class="text-center">{{ $movement['date']->format('Y-m-d') }}</td>
                    <td class="text-center">{!! $movement['type'] !!}</td>
                    <td class="text-center">{{ $movement['reference_number'] }}</td>
                    <td class="text-center">{!! $movement['warehouse'] !!}</td>
                    <td class="text-right in-qty">{{ $movement['in'] > 0 ? number_format($movement['in'], 0) : '-' }}</td>
                    <td class="text-right out-qty">{{ $movement['out'] > 0 ? number_format($movement['out'], 0) : '-' }}</td>
                    <td class="text-right">{{ number_format($movement['cost_at_time'], 2) }}</td>
                    <td class="text-right {{ $movement['balance'] >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($movement['balance'], 0) }}
                    </td>
                </tr>
            @endforeach

            {{-- Totals --}}
            <tr class="totals-row">
                <td colspan="4" class="text-center">{!! $labels['total'] !!}</td>
                <td class="text-right in-qty">{{ number_format($reportData['total_in'], 0) }}</td>
                <td class="text-right out-qty">{{ number_format($reportData['total_out'], 0) }}</td>
                <td class="text-center">-</td>
                <td class="text-right {{ $reportData['closing_stock'] >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($reportData['closing_stock'], 0) }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Footer --}}
    <div class="footer">
        {!! $labels['printed_at'] !!} {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
