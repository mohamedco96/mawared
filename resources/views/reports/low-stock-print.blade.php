@extends('layouts.print')

@section('title', 'تقرير النواقص - Low Stock Alert')

@section('header')
    <div class="invoice-header">
        <div class="header-grid">
            {{-- Company Section --}}
            <div class="company-section">
                <h1 class="company-name">{{ $companySettings->company_name }}</h1>
                <div class="company-details">
                    <p>{{ $companySettings->company_address }}</p>
                    <p>هاتف: {{ $companySettings->company_phone }}</p>
                </div>
            </div>

            {{-- Report Title Box --}}
            <div class="invoice-info-box">
                <h2 class="invoice-title">تقرير تنبيهات النواقص</h2>
                <div class="invoice-meta">
                    <div><strong>تاريخ الطباعة:</strong> {{ now()->format('Y-m-d H:i') }}</div>
                    <div><strong>عدد الأصناف الناقصة:</strong> {{ $products->count() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    {{-- Low Stock Products Table --}}
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 35%;">اسم المنتج</th>
                <th style="width: 20%;">الباركود</th>
                <th style="width: 15%;">المخزون الحالي</th>
                <th style="width: 15%;">الحد الأدنى</th>
                <th style="width: 10%;">الوحدة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $index => $product)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $product->name }}</td>
                    <td style="text-align: center;">{{ $product->barcode }}</td>
                    <td style="text-align: center; color: #dc2626; font-weight: bold;">
                        {{ $product->current_stock }}
                    </td>
                    <td style="text-align: center; color: #f59e0b;">
                        {{ $product->min_stock }}
                    </td>
                    <td style="text-align: center;">{{ $product->smallUnit->name }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">
                        لا توجد أصناف ناقصة حالياً
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Summary Section --}}
    @if($products->count() > 0)
        <div style="margin-top: 30px; padding: 15px; background-color: #fef3c7; border-radius: 8px;">
            <p style="margin: 0; font-weight: bold; font-size: 14px;">
                📊 إجمالي الأصناف الناقصة: {{ $products->count() }}
            </p>
        </div>
    @endif
@endsection

@section('footer')
    <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px;">
        <p style="margin: 0;">Osool (أصول) - Low Stock Alert Report</p>
        <p style="margin: 5px 0 0 0;">Printed on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
@endsection
