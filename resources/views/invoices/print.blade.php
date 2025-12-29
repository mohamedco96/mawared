<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>فاتورة مبيعات {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Amiri', 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11pt;
            line-height: 1.5;
            color: #1e293b;
        }

        /* Header Section */
        .header {
            width: 100%;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-right {
            width: 60%;
            text-align: right;
            vertical-align: top;
            padding-left: 10px;
        }

        .header-left {
            width: 40%;
            text-align: left;
            vertical-align: top;
            padding-right: 10px;
        }

        .company-logo {
            max-height: 50px;
            margin-bottom: 5px;
        }

        .company-name {
            font-size: 15pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 9pt;
            color: #64748b;
            line-height: 1.6;
        }

        .invoice-box {
            background-color: #f8fafc;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }

        .invoice-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .invoice-info {
            font-size: 9pt;
            line-height: 1.8;
        }

        .invoice-info div {
            text-align: right;
        }

        .info-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            min-width: 45%;
            text-align: right;
        }

        .info-value {
            display: inline-block;
            text-align: left;
        }

        /* Client Section */
        .client-section {
            background-color: #f8fafc;
            padding: 10px 15px;
            margin: 12px 0;
            border-right: 3px solid #1e293b;
        }

        .client-title {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 5px;
            color: #1e293b;
        }

        .client-info {
            font-size: 9pt;
            color: #475569;
            line-height: 1.7;
        }

        .client-info div {
            text-align: right;
        }

        .client-info strong {
            display: inline-block;
            min-width: 25%;
            text-align: right;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .items-table thead {
            background-color: #1e293b;
            color: #ffffff;
        }

        .items-table th {
            padding: 8px 5px;
            font-size: 9pt;
            font-weight: bold;
            border: 1px solid #334155;
            text-align: center;
        }

        .items-table td {
            padding: 7px 5px;
            font-size: 9pt;
            border: 1px solid #cbd5e1;
            text-align: center;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* RTL Column Alignments - Columns are physically reversed */
        .col-serial {
            text-align: center !important;
        }

        .col-product {
            text-align: right !important;
            font-weight: bold;
            direction: rtl;
        }

        .col-unit {
            text-align: center !important;
            direction: rtl;
        }

        .col-qty {
            text-align: center !important;
        }

        .col-price,
        .col-discount,
        .col-total {
            text-align: right !important;
        }

        /* Payment History */
        .payment-section {
            background-color: #fef3c7;
            padding: 10px;
            margin: 12px 0;
            border: 1px solid #fcd34d;
        }

        .payment-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 6px;
            font-size: 10pt;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .payment-table th,
        .payment-table td {
            padding: 5px 8px;
            text-align: center;
            border: 1px solid #fbbf24;
            font-size: 9pt;
        }

        .payment-table thead {
            background-color: #fbbf24;
            color: #78350f;
        }

        .payment-table .payment-amount {
            text-align: right;
        }

        /* Totals Section */
        .totals-wrapper {
            width: 100%;
            margin-top: 20px;
        }

        .totals-section {
            width: 48%;
            float: left;
            border: 1px solid #cbd5e1;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .totals-table td {
            padding: 7px 10px;
            font-size: 9pt;
            text-align: center;
        }

        .totals-label {
            width: 45%;
            font-weight: bold;
            color: #475569;
            text-align: right;
            background-color: #f8fafc;
        }

        .totals-value {
            width: 55%;
            text-align: left;
            color: #1e293b;
        }

        .grand-total .totals-label,
        .grand-total .totals-value {
            background-color: #1e293b;
            color: #ffffff;
            font-size: 11pt;
            font-weight: bold;
            padding: 9px 10px;
            text-align: center;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 8pt;
            color: #ffffff;
        }

        .status-paid {
            background-color: #10b981;
        }

        .status-partial {
            background-color: #f59e0b;
        }

        .status-unpaid {
            background-color: #ef4444;
        }

        /* Footer */
        .footer {
            clear: both;
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px solid #cbd5e1;
        }

        .notes {
            background-color: #f8fafc;
            padding: 8px 12px;
            margin-bottom: 12px;
            border-right: 3px solid #64748b;
        }

        .notes-title {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 4px;
        }

        .notes-text {
            font-size: 9pt;
            color: #475569;
        }

        .thank-you {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin: 15px 0 8px 0;
        }

        .print-date {
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
        }

        .clearfix {
            clear: both;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <table>
            <tr>
                <td class="header-right">
                    @if ($companySettings->logo)
                        <img src="{{ public_path('storage/' . $companySettings->logo) }}" class="company-logo"
                            alt="Logo">
                    @endif
                    <div class="company-name">{!! $processedData['company_name'] !!}</div>
                    <div class="company-info">
                        <div>{!! $processedData['company_address'] !!}</div>
                        <div>هاتف: {{ $companySettings->company_phone }}</div>
                        @if ($companySettings->company_email)
                            <div>بريد: {{ $companySettings->company_email }}</div>
                        @endif
                        @if ($companySettings->company_tax_number)
                            <div>الرقم الضريبي: {{ $companySettings->company_tax_number }}</div>
                        @endif
                    </div>
                </td>
                <td class="header-left">
                    <div class="invoice-box">
                        <div class="invoice-title">{!! $labels['invoice_title'] !!}</div>
                        <div class="invoice-info">
                            <div><span class="info-label">{!! $labels['invoice_number'] !!}</span> <span
                                    class="info-value">{{ $invoice->invoice_number }}</span></div>
                            <div><span class="info-label">{!! $labels['date'] !!}</span> <span
                                    class="info-value">{{ $invoice->created_at->format('Y-m-d') }}</span></div>
                            <div><span class="info-label">{!! $labels['warehouse'] !!}</span> <span
                                    class="info-value">{!! $processedData['warehouse_name'] !!}</span></div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Client Information -->
    <div class="client-section">
        <div class="client-title">بيانات العميل</div>
        <div class="client-info">
            <div><strong>{!! $labels['customer_name'] !!}</strong> {!! $processedData['partner_name'] !!}</div>
            @if ($invoice->partner->phone)
                <div><strong>{!! $labels['phone'] !!}</strong> {!! $processedData['partner_phone'] !!}</div>
            @endif
            @if ($invoice->partner->address)
                <div><strong>العنوان:</strong> {!! $processedData['partner_address'] !!}</div>
            @endif
        </div>
    </div>

    <!-- Items Table (RTL: Total - Discount - Price - Qty - Unit - Product - #) -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 17%;" class="col-total">{!! $labels['total'] !!}</th>
                <th style="width: 12%;" class="col-discount">{!! $labels['discount'] !!}</th>
                <th style="width: 13%;" class="col-price">{!! $labels['price'] !!}</th>
                <th style="width: 10%;" class="col-qty">{!! $labels['quantity'] !!}</th>
                <th style="width: 10%;" class="col-unit">{!! $labels['unit'] !!}</th>
                <th style="width: 33%;" class="col-product">{!! $labels['product'] !!}</th>
                <th style="width: 5%;" class="col-serial">#</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($processedData['items'] as $index => $processedItem)
                <tr>
                    <td class="col-total">{{ number_format($processedItem['original']->total, 2) }}</td>
                    <td class="col-discount">{{ number_format($processedItem['original']->discount, 2) }}</td>
                    <td class="col-price">{{ number_format($processedItem['original']->unit_price, 2) }}</td>
                    <td class="col-qty">{{ number_format($processedItem['original']->quantity, 0) }}</td>
                    <td class="col-unit">
                        @if ($processedItem['original']->unit_type === 'small')
                            {!! $processedItem['small_unit_name'] !!}
                        @else
                            {!! $processedItem['large_unit_name'] !!}
                        @endif
                    </td>
                    <td class="col-product">{!! $processedItem['product_name'] !!}</td>
                    <td class="col-serial">{{ $index + 1 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Payment History -->
    @if ($invoice->payments->count() > 0)
        <div class="payment-section">
            <div class="payment-title">{!! $labels['payment_history'] !!}</div>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>{!! $labels['discount'] !!}</th>
                        <th>{!! $labels['paid'] !!}</th>
                        <th>{!! $labels['date'] !!}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $payment)
                        <tr>
                            <td class="payment-amount">{{ number_format($payment->discount, 2) }}
                                {{ $companySettings->currency_symbol }}</td>
                            <td class="payment-amount">{{ number_format($payment->amount, 2) }}
                                {{ $companySettings->currency_symbol }}</td>
                            <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Totals Section (Bottom Left) -->
    <div class="totals-wrapper">
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="totals-value">{{ number_format($invoice->subtotal, 2) }}
                        {{ $companySettings->currency_symbol }}</td>
                    <td class="totals-label">{!! $labels['subtotal'] !!}</td>
                </tr>
                <tr>
                    <td class="totals-value">{{ number_format($invoice->calculated_discount, 2) }}
                        {{ $companySettings->currency_symbol }}</td>
                    <td class="totals-label">{!! $labels['total_discount'] !!}</td>
                </tr>
                <tr class="grand-total">
                    <td class="totals-value">{{ number_format($invoice->total, 2) }}
                        {{ $companySettings->currency_symbol }}</td>
                    <td class="totals-label">{!! $labels['grand_total'] !!}</td>
                </tr>
                <tr>
                    <td class="totals-value">{{ number_format($invoice->total_paid, 2) }}
                        {{ $companySettings->currency_symbol }}</td>
                    <td class="totals-label">{!! $labels['paid'] !!}</td>
                </tr>
                <tr>
                    <td class="totals-value">{{ number_format($invoice->current_remaining, 2) }}
                        {{ $companySettings->currency_symbol }}</td>
                    <td class="totals-label">{!! $labels['remaining'] !!}</td>
                </tr>
                <tr>
                    <td class="totals-value">
                        @if ($invoice->isFullyPaid())
                            <span class="status-badge status-paid">{!! $labels['status_paid'] !!}</span>
                        @elseif($invoice->isPartiallyPaid())
                            <span class="status-badge status-partial">{!! $labels['status_partial'] !!}</span>
                        @else
                            <span class="status-badge status-unpaid">{!! $labels['status_unpaid'] !!}</span>
                        @endif
                    </td>
                    <td class="totals-label">{!! $labels['status'] !!}</td>
                </tr>
            </table>
        </div>
    </div>
    <div class="clearfix"></div>

    <!-- Footer -->
    <div class="footer">
        @if ($processedData['notes'])
            <div class="notes">
                <div class="notes-title">{!! $labels['notes_title'] !!}</div>
                <div class="notes-text">{!! $processedData['notes'] !!}</div>
            </div>
        @endif

        <div class="thank-you">{!! $labels['thank_you'] !!}</div>
        <div class="print-date">{!! $labels['printed_at'] !!} {{ now()->format('Y-m-d H:i') }}</div>
    </div>
</body>

</html>
