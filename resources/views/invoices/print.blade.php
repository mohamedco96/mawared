@extends('layouts.print')

@section('title', 'فاتورة مبيعات ' . $invoice->invoice_number)

@section('header')
    <div class="invoice-header">
        <div class="header-grid">
            {{-- Company Section --}}
            <div class="company-section">
                @if ($companySettings->logo)
                    <img src="{{ asset('storage/' . $companySettings->logo) }}"
                         alt="Logo" class="company-logo">
                @endif
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

            {{-- Invoice Info Box --}}
            <div class="invoice-info-box">
                <h2 class="invoice-title">فاتورة مبيعات / SALES INVOICE</h2>
                <div class="invoice-meta">
                    <div><strong>رقم الفاتورة:</strong> {{ $invoice->invoice_number }}</div>
                    <div><strong>التاريخ:</strong> {{ $invoice->created_at->format('Y-m-d') }}</div>
                    <div><strong>المخزن:</strong> {{ $invoice->warehouse->name }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    {{-- Client Information --}}
    <div class="client-section">
        <h3 class="section-title">بيانات العميل</h3>
        <div class="client-details">
            <div><strong>اسم العميل:</strong> {{ $invoice->partner->name }}</div>
            @if ($invoice->partner->phone)
                <div><strong>الهاتف:</strong> {{ $invoice->partner->phone }}</div>
            @endif
            @if ($invoice->partner->address)
                <div><strong>العنوان:</strong> {{ $invoice->partner->address }}</div>
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
                <th class="col-discount hide-on-thermal">الخصم</th>
                <th class="col-total">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $index => $item)
                <tr>
                    <td class="col-serial">{{ $index + 1 }}</td>
                    <td class="col-product">{{ $item->product->name }}</td>
                    <td class="col-unit">
                        @if ($item->unit_type === 'small')
                            {{ $item->product->smallUnit->name }}
                        @else
                            {{ $item->product->largeUnit->name }}
                        @endif
                    </td>
                    <td class="col-qty">{{ number_format($item->quantity, 0) }}</td>
                    <td class="col-price">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="col-discount hide-on-thermal">{{ number_format($item->discount, 2) }}</td>
                    <td class="col-total">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Payment History --}}
    @if ($invoice->payments->count() > 0)
        <div class="payment-section hide-on-thermal">
            <h3 class="section-title">سجل الدفعات اللاحقة</h3>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المدفوع</th>
                        <th>الخصم</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
                            <td>{{ number_format($payment->amount, 2) }}</td>
                            <td>{{ number_format($payment->discount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Totals Section --}}
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td class="totals-label">المجموع الفرعي:</td>
                <td class="totals-value">{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="totals-label">الخصم:</td>
                <td class="totals-value">{{ number_format($invoice->calculated_discount, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td class="totals-label">الإجمالي النهائي:</td>
                <td class="totals-value">{{ number_format($invoice->total, 2) }}</td>
            </tr>
            <tr>
                <td class="totals-label">المدفوع:</td>
                <td class="totals-value">{{ number_format($invoice->total_paid, 2) }}</td>
            </tr>
            <tr>
                <td class="totals-label">المتبقي:</td>
                <td class="totals-value">{{ number_format($invoice->current_remaining, 2) }}</td>
            </tr>
            <tr>
                <td class="totals-label">الحالة:</td>
                <td class="totals-value">
                    @if ($invoice->isFullyPaid())
                        <span class="status-badge status-paid">مدفوعة بالكامل</span>
                    @elseif($invoice->isPartiallyPaid())
                        <span class="status-badge status-partial">مدفوعة جزئياً</span>
                    @else
                        <span class="status-badge status-unpaid">غير مدفوعة</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
@endsection

@section('footer')
    @if ($invoice->notes)
        <div class="notes-section">
            <h4 class="notes-title">ملاحظات:</h4>
            <p class="notes-text">{{ $invoice->notes }}</p>
        </div>
    @endif

    <div class="thank-you">شكراً لتعاملكم معنا</div>
    <div class="print-date">تم الطباعة بتاريخ: {{ now()->format('Y-m-d H:i') }}</div>

    {{-- Manual Print Button (hidden during actual print) --}}
    <button class="btn-print no-print">طباعة</button>
@endsection
