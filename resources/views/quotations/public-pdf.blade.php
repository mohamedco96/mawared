@extends('layouts.print')

@section('title', 'عرض سعر ' . $quotation->quotation_number)

@section('header')
    <div class="invoice-header">
        <div class="header-grid">
            {{-- Company Section --}}
            <div class="company-section">
                <h1 class="company-name">{{ $companySettings->company_name }}</h1>
                <div class="company-details">
                    <p>{{ $companySettings->company_address }}</p>
                    <p>هاتف: {{ $companySettings->company_phone }}</p>
                    @if ($companySettings->company_email)
                        <p>بريد: {{ $companySettings->company_email }}</p>
                    @endif
                    @if ($companySettings->company_tax_number)
                        <p>الرقم الضريبي: {{ $companySettings->company_tax_number }}</p>
                    @endif
                </div>
            </div>

            {{-- Quotation Info Box --}}
            <div class="invoice-info-box">
                <h2 class="invoice-title">عرض سعر / QUOTATION</h2>
                <div class="invoice-meta">
                    <div><strong>رقم عرض السعر:</strong> {{ $quotation->quotation_number }}</div>
                    <div><strong>التاريخ:</strong> {{ $quotation->created_at->format('Y-m-d') }}</div>
                    @if ($quotation->valid_until)
                        <div><strong>صالح حتى:</strong> {{ $quotation->valid_until->format('Y-m-d') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    {{-- Customer Information --}}
    <div class="client-section">
        <h3 class="section-title">بيانات العميل</h3>
        <div class="client-details">
            <div><strong>اسم العميل:</strong> {{ $quotation->customer_name }}</div>
            @if ($quotation->customer_phone)
                <div><strong>الهاتف:</strong> {{ $quotation->customer_phone }}</div>
            @endif
        </div>
    </div>

    {{-- Items Table --}}
    <table class="invoice-table">
        <thead>
            <tr>
                <th class="col-serial">#</th>
                <th class="col-product">المنتج</th>
                <th class="col-unit">الوحدة</th>
                <th class="col-qty">الكمية</th>
                <th class="col-price">السعر</th>
                <th class="col-total">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $index => $item)
                <tr>
                    <td class="col-serial">{{ $index + 1 }}</td>
                    <td class="col-product">{{ $item->product_name }}</td>
                    <td class="col-unit">{{ $item->unit_name }}</td>
                    <td class="col-qty">{{ number_format($item->quantity, 0) }}</td>
                    <td class="col-price">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="col-total">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals Section --}}
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td class="totals-label">المجموع الفرعي:</td>
                <td class="totals-value">{{ number_format($quotation->subtotal, 2) }} {{ $companySettings->currency_symbol }}</td>
            </tr>
            @if ($quotation->discount > 0)
                <tr>
                    <td class="totals-label">الخصم:</td>
                    <td class="totals-value">{{ number_format($quotation->discount, 2) }} {{ $companySettings->currency_symbol }}</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td class="totals-label">الإجمالي النهائي:</td>
                <td class="totals-value">{{ number_format($quotation->total, 2) }} {{ $companySettings->currency_symbol }}</td>
            </tr>
        </table>
    </div>
@endsection

@section('footer')
    @if ($quotation->notes)
        <div class="notes-section">
            <h4 class="notes-title">ملاحظات:</h4>
            <p class="notes-text">{{ $quotation->notes }}</p>
        </div>
    @endif

    <div class="thank-you">شكراً لاهتمامكم</div>
    <div class="print-date">تم الطباعة بتاريخ: {{ now()->format('Y-m-d H:i') }}</div>

    {{-- Manual Print Button (hidden during actual print) --}}
    <button class="btn-print no-print">طباعة</button>
@endsection
