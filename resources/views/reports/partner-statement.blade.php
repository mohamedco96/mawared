<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>كشف حساب عميل</title>
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

        /* Partner Info */
        .partner-info {
            background-color: #f8fafc;
            padding: 10px 15px;
            margin: 12px 0;
            border-right: 3px solid #1e293b;
        }
        .partner-info div {
            margin-bottom: 3px;
            font-size: 10pt;
        }
        .partner-info strong {
            color: #1e293b;
        }

        /* Transactions Table */
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .transactions-table thead {
            background-color: #1e293b;
            color: #ffffff;
        }
        .transactions-table th {
            padding: 8px 5px;
            font-size: 9pt;
            font-weight: bold;
            border: 1px solid #334155;
            text-align: center;
        }
        .transactions-table td {
            padding: 7px 5px;
            font-size: 9pt;
            border: 1px solid #cbd5e1;
            text-align: right;
        }
        .transactions-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .opening-balance {
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

    {{-- Partner Info --}}
    <div class="partner-info">
        <div><strong>{!! $labels['customer_name'] !!}</strong> {!! $processedData['partner_name'] !!}</div>
        <div><strong>{!! $labels['phone'] !!}</strong> {!! $processedData['partner_phone'] !!}</div>
        <div><strong>{!! $labels['from_date'] !!}</strong> {{ $reportData['from_date'] }}</div>
        <div><strong>{!! $labels['to_date'] !!}</strong> {{ $reportData['to_date'] }}</div>
    </div>

    {{-- Transactions Table --}}
    <table class="transactions-table">
        <thead>
            <tr>
                <th style="width: 12%;">{!! $labels['date'] !!}</th>
                <th style="width: 15%;">{!! $labels['reference'] !!}</th>
                <th style="width: 28%;">{!! $labels['description'] !!}</th>
                <th style="width: 15%;">{!! $labels['debit'] !!}</th>
                <th style="width: 15%;">{!! $labels['credit'] !!}</th>
                <th style="width: 15%;">{!! $labels['balance'] !!}</th>
            </tr>
        </thead>
        <tbody>
            {{-- Opening Balance --}}
            <tr class="opening-balance">
                <td colspan="3">{!! $labels['opening_balance'] !!}</td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="text-right {{ $reportData['opening_balance'] >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($reportData['opening_balance'], 2) }}
                </td>
            </tr>

            {{-- Transactions --}}
            @foreach ($reportData['transactions'] as $transaction)
                <tr>
                    <td class="text-center">{{ $transaction['date']->format('Y-m-d') }}</td>
                    <td class="text-center">{{ $transaction['reference_number'] }}</td>
                    <td>{!! $transaction['description'] !!}
                        @if(isset($transaction['warehouse']))
                            <span style="font-size: 8pt; color: #64748b;">({!! $transaction['warehouse'] !!})</span>
                        @endif
                    </td>
                    <td class="text-right">{{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}</td>
                    <td class="text-right">{{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}</td>
                    <td class="text-right {{ $transaction['balance'] >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($transaction['balance'], 2) }}
                    </td>
                </tr>
            @endforeach

            {{-- Totals --}}
            <tr class="totals-row">
                <td colspan="3" class="text-center">{!! $labels['total'] !!}</td>
                <td class="text-right">{{ number_format($reportData['total_debit'], 2) }}</td>
                <td class="text-right">{{ number_format($reportData['total_credit'], 2) }}</td>
                <td class="text-right {{ $reportData['closing_balance'] >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($reportData['closing_balance'], 2) }}
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
