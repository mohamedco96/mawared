<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>عرض سعر {{ $quotation->quotation_number }}</title>
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

        .quotation-box {
            background-color: #f8fafc;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }

        .quotation-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .quotation-info {
            font-size: 9pt;
            line-height: 1.8;
        }

        .quotation-info div {
            text-align: right;
        }

        .info-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            min-width: 45%;
            text-align: right;
        }

        /* Customer Info */
        .customer-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 8px;
            color: #334155;
        }

        .customer-info {
            font-size: 10pt;
            line-height: 1.7;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10pt;
        }

        .items-table thead {
            background-color: #1e40af;
            color: white;
        }

        .items-table thead th {
            padding: 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #1e3a8a;
        }

        .items-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .items-table tbody td {
            padding: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .items-table .text-right {
            text-align: right;
        }

        .items-table .text-left {
            text-align: left;
        }

        /* Totals Section */
        .totals-section {
            width: 45%;
            margin-right: auto;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }

        .totals-table tr {
            border-bottom: 1px dashed #cbd5e1;
        }

        .totals-table tr:last-child {
            border-bottom: none;
            background-color: #dbeafe;
            font-weight: bold;
            font-size: 11pt;
        }

        .totals-table td {
            padding: 6px 8px;
        }

        .totals-table .label {
            text-align: right;
            color: #475569;
            width: 50%;
        }

        .totals-table .amount {
            text-align: left;
            font-weight: 600;
            color: #1e293b;
            width: 50%;
        }

        /* Notes Section */
        .notes-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 3px;
        }

        .notes-section .section-title {
            color: #92400e;
            margin-bottom: 5px;
        }

        .notes-section p {
            font-size: 10pt;
            color: #78350f;
            white-space: pre-line;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
            font-size: 9pt;
            text-align: center;
            color: #64748b;
        }

        /* Utility Classes */
        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .mt-2 {
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <table>
            <tr>
                <td class="header-right">
                    <div class="company-name">{{ $company_name }}</div>
                    <div class="company-info">
                        <div>{{ $companySettings->company_phone }}</div>
                        <div>{{ $companySettings->company_email }}</div>
                        @if($companySettings->company_tax_number)
                            <div>الرقم الضريبي: {{ $companySettings->company_tax_number }}</div>
                        @endif
                    </div>
                </td>
                <td class="header-left">
                    <div class="quotation-box">
                        <div class="quotation-title">عرض سعر</div>
                        <div class="quotation-info">
                            <div>
                                <span class="info-label">رقم العرض:</span>
                                <span>{{ $quotation->quotation_number }}</span>
                            </div>
                            <div>
                                <span class="info-label">التاريخ:</span>
                                <span>{{ $quotation->created_at->format('Y-m-d') }}</span>
                            </div>
                            @if($quotation->valid_until)
                                <div>
                                    <span class="info-label">صالح حتى:</span>
                                    <span>{{ $quotation->valid_until->format('Y-m-d') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Customer Info -->
    <div class="customer-section">
        <div class="section-title">معلومات العميل</div>
        <div class="customer-info">
            <div><strong>الاسم:</strong> {{ $customer_name }}</div>
            @if($quotation->customer_phone)
                <div><strong>الهاتف:</strong> {{ $quotation->customer_phone }}</div>
            @endif
        </div>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">اسم الصنف</th>
                <th style="width: 15%;">الوحدة</th>
                <th style="width: 10%;">الكمية</th>
                <th style="width: 15%;">سعر الوحدة</th>
                <th style="width: 15%;">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-right">{{ $item['product_name'] }}</td>
                    <td>{{ $item['unit_name'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td class="text-left">{{ number_format($item['unit_price'], 2) }}</td>
                    <td class="text-left">{{ number_format($item['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="label">المجموع الفرعي:</td>
                <td class="amount">{{ number_format($quotation->subtotal, 2) }} {{ $companySettings->currency_symbol }}</td>
            </tr>
            @if($quotation->discount > 0)
                <tr>
                    <td class="label">الخصم:</td>
                    <td class="amount" style="color: #dc2626;">- {{ number_format($quotation->discount, 2) }} {{ $companySettings->currency_symbol }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">الإجمالي النهائي:</td>
                <td class="amount" style="color: #1e40af;">{{ number_format($quotation->total, 2) }} {{ $companySettings->currency_symbol }}</td>
            </tr>
        </table>
    </div>

    <!-- Notes -->
    @if($notes)
        <div class="notes-section">
            <div class="section-title">ملاحظات:</div>
            <p>{{ $notes }}</p>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>{{ $company_name }} - جميع الحقوق محفوظة © {{ date('Y') }}</p>
        @if($companySettings->company_tax_number)
            <p class="mt-2">الرقم الضريبي: {{ $companySettings->company_tax_number }}</p>
        @endif
    </div>
</body>

</html>
